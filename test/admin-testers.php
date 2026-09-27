<?php
declare(strict_types=1);

$authenticatedUser = trim((string) ($_SERVER['REMOTE_USER'] ?? $_SERVER['PHP_AUTH_USER'] ?? ''));
if ($authenticatedUser === '') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Accès administrateur refusé. La protection cPanel du dossier /test/ doit être active.\n";
    exit;
}

require dirname(__DIR__) . '/assets/tester-storage.php';

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

if (empty($_SESSION['vevak_testers_csrf'])) {
    $_SESSION['vevak_testers_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string) $_SESSION['vevak_testers_csrf'];

function vv_admin_csv_download(string $filename, array $lines): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo implode("\n", $lines) . "\n";
    exit;
}

try {
    $rows = vv_get_testers();

    $export = (string) ($_GET['export'] ?? '');
    if ($export === 'play') {
        $emails = array_map(static fn(array $row): string => (string) $row['email'], $rows);
        // Google Play Console attend un e-mail par ligne, sans virgule et sans BOM UTF-8.
        vv_admin_csv_download('vevak-google-play-testers.csv', $emails);
    }
    if ($export === 'spreadsheet') {
        $lines = ['email;device_model;android_version;sim_setup;feedback_consent;feedback_enabled'];
        foreach ($rows as $row) {
            $values = [
                vv_spreadsheet_safe_email((string) $row['email']),
                (string) ($row['device_model'] ?? ''),
                (string) ($row['android_version'] ?? ''),
                (string) ($row['sim_setup'] ?? ''),
                !empty($row['feedback_consent']) ? 'oui' : 'non',
                !empty($row['feedback_enabled']) ? 'oui' : 'non',
            ];
            $lines[] = implode(';', array_map(
                static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"',
                $values
            ));
        }
        vv_admin_csv_download('vevak-testers-tableur.csv', $lines);
    }

    if ($export === 'feedback') {
        $answerKeys = [
            'q1_launch','q2_permissions','q3_contact','q4_sms','q5_location','q6_locked',
            'q7_reboot','q8_revoke','q9_manual','q10_notifications','q11_trust','q12_missing'
        ];
        $commentKeys = array_map(static fn(string $key): string => 'comment_' . $key, $answerKeys);
        $headers = array_merge(
            ['email','updated_at','submitted_at','device_model','android_version','sim_setup'],
            $answerKeys,
            $commentKeys
        );
        $lines = [implode(';', $headers)];

        foreach (vv_get_feedback_responses() as $response) {
            $email = (string) ($response['email'] ?? '');
            $tester = vv_get_tester($email) ?? [];
            $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
            $values = [
                vv_spreadsheet_safe_email($email),
                (string) ($response['updated_at'] ?? ''),
                (string) ($response['submitted_at'] ?? ''),
                (string) ($tester['device_model'] ?? ''),
                (string) ($tester['android_version'] ?? ''),
                (string) ($tester['sim_setup'] ?? ''),
            ];
            foreach (array_merge($answerKeys, $commentKeys) as $key) {
                $values[] = (string) ($answers[$key] ?? '');
            }
            $lines[] = implode(';', array_map(
                static fn(string $value): string => '"' . str_replace('"', '""', $value) . '"',
                $values
            ));
        }

        vv_admin_csv_download('vevak-retours-test.csv', $lines);
    }

    $notice = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals($csrf, $token)) {
            http_response_code(403);
            throw new RuntimeException('Jeton de sécurité invalide. Recharge la page puis réessaie.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $key = (string) ($_POST['key'] ?? '');
            $notice = vv_delete_tester($key)
                ? 'Inscription, compte de retours et réponses associées supprimés.'
                : 'Inscription déjà absente.';
            $rows = vv_get_testers();
        } elseif ($action === 'enable_feedback' || $action === 'disable_feedback') {
            $key = (string) ($_POST['key'] ?? '');
            $enabled = $action === 'enable_feedback';
            $target = null;
            foreach ($rows as $row) {
                if (hash_equals((string) ($row['key'] ?? ''), $key)) {
                    $target = $row;
                    break;
                }
            }
            if ($target === null) {
                $notice = 'Testeur introuvable.';
            } elseif ($enabled && empty($target['feedback_consent'])) {
                $notice = 'Ce testeur n’a pas accepté de participer au questionnaire.';
            } else {
                vv_set_feedback_enabled($key, $enabled);
                $notice = $enabled
                    ? 'Accès au questionnaire autorisé. Le testeur peut maintenant créer son mot de passe.'
                    : 'Accès au questionnaire suspendu.';
            }
            $rows = vv_get_testers();
        } elseif ($action === 'reset_feedback_password') {
            $key = (string) ($_POST['key'] ?? '');
            $targetEmail = '';
            foreach ($rows as $row) {
                if (hash_equals((string) ($row['key'] ?? ''), $key)) {
                    $targetEmail = (string) ($row['email'] ?? '');
                    break;
                }
            }
            $notice = $targetEmail !== '' && vv_feedback_reset_password($targetEmail)
                ? 'Mot de passe de l’espace retours réinitialisé. Le testeur pourra en créer un nouveau.'
                : 'Aucun mot de passe actif à réinitialiser.';
            $rows = vv_get_testers();
        }
    }
} catch (Throwable $error) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    $rows = [];
    $notice = $error->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Testeurs Google Play — VeVak</title>
  <link rel="stylesheet" href="../assets/styles.css">
  <style>
    .admin-wrap{width:min(1100px,calc(100% - 2rem));margin:2rem auto 4rem}.admin-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin:1rem 0 1.5rem}.admin-table-wrap{overflow-x:auto;border:1px solid #d8e3de;border-radius:1rem;background:#fff}.admin-table{width:100%;border-collapse:collapse;min-width:760px}.admin-table th,.admin-table td{padding:.8rem;border-bottom:1px solid #e6ece9;text-align:left;vertical-align:top}.admin-table th{background:#eef5f1;color:#17332c}.admin-table code{font-size:.82rem}.danger{border-color:#b24b43!important;color:#7a211b!important}.admin-notice{padding:.8rem 1rem;background:#eef5f1;border-radius:.7rem}.admin-meta{color:#526760}.delete-form{margin:0 0 .45rem}.admin-table td .button{white-space:nowrap}
  </style>
</head>
<body>
<main class="admin-wrap">
  <p class="kicker">VeVak · administration privée</p>
  <h1>Demandes de test Google Play</h1>
  <p class="admin-meta">Connecté via la protection du dossier <code>/test/</code> : <?= e($authenticatedUser) ?>. <?= count($rows) ?> inscription(s).</p>
  <?php if ($notice !== ''): ?><p class="admin-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
  <div class="admin-actions">
    <a class="button primary" href="?export=play">Exporter pour Google Play</a>
    <a class="button secondary" href="?export=spreadsheet">Exporter pour tableur</a>
    <a class="button secondary" href="?export=feedback">Exporter les réponses</a>
    <a class="button secondary" href="../#devenir-testeur">Voir le formulaire public</a>
    <a class="button secondary" href="../retours/">Voir l’espace retours</a>
  </div>
  <p class="admin-meta"><strong>Export Google Play :</strong> un e-mail par ligne, aucun en-tête, aucune virgule et aucun BOM UTF-8. L’export « tableur » ajoute le téléphone, Android, la SIM et l’état du questionnaire ; il ne doit pas être importé dans Play Console.</p>
  <p class="admin-meta"><strong>Espace retours :</strong> une inscription publique n’ouvre pas automatiquement l’accès. Active « Autoriser les retours » uniquement pour les personnes réellement retenues. Elles créeront ensuite elles-mêmes leur mot de passe sur <code>/retours/</code>.</p>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>E-mail</th><th>Appareil</th><th>Android / SIM</th><th>Retours</th><th>Dates</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6">Aucune demande enregistrée.</td></tr>
      <?php else: foreach ($rows as $row): ?>
        <?php
          $feedbackConsent = !empty($row['feedback_consent']);
          $feedbackEnabled = !empty($row['feedback_enabled']);
          $feedbackAccount = vv_feedback_account_exists((string) $row['email']);
          $feedbackData = vv_feedback_get_answers((string) $row['email']);
          $feedbackSubmittedAt = (string) ($feedbackData['submitted_at'] ?? '');
          $feedbackUpdatedAt = (string) ($feedbackData['updated_at'] ?? '');
        ?>
        <tr>
          <td><?= e((string) $row['email']) ?><br><small><code><?= e((string) ($row['consent_version'] ?? '')) ?></code></small></td>
          <td><?= e((string) ($row['device_model'] ?? '—')) ?></td>
          <td><?= e((string) ($row['android_version'] ?? '—')) ?><br><small><?= e((string) ($row['sim_setup'] ?? '—')) ?></small></td>
          <td>
            <strong><?= $feedbackConsent ? 'Accord ✓' : 'Pas d’accord' ?></strong><br>
            <small><?= $feedbackEnabled ? 'Accès autorisé' : 'Accès non autorisé' ?> · <?= $feedbackAccount ? 'mot de passe créé' : 'pas encore de mot de passe' ?></small><br>
            <small><?php if ($feedbackSubmittedAt !== ''): ?>questionnaire envoyé le <?= e($feedbackSubmittedAt) ?><?php elseif ($feedbackUpdatedAt !== ''): ?>brouillon sauvegardé le <?= e($feedbackUpdatedAt) ?><?php else: ?>aucune réponse pour l’instant<?php endif; ?></small>
          </td>
          <td><small>Créée : <?= e((string) ($row['created_at'] ?? '')) ?><br>Dernière demande : <?= e((string) ($row['last_requested_at'] ?? '')) ?></small></td>
          <td>
            <?php if ($feedbackConsent): ?>
              <form method="post" class="delete-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="<?= $feedbackEnabled ? 'disable_feedback' : 'enable_feedback' ?>">
                <input type="hidden" name="key" value="<?= e((string) $row['key']) ?>">
                <button class="button secondary" type="submit"><?= $feedbackEnabled ? 'Suspendre les retours' : 'Autoriser les retours' ?></button>
              </form>
            <?php endif; ?>
            <?php if ($feedbackAccount): ?>
              <form method="post" class="delete-form" onsubmit="return confirm('Réinitialiser le mot de passe de ce testeur ?');">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="reset_feedback_password">
                <input type="hidden" name="key" value="<?= e((string) $row['key']) ?>">
                <button class="button secondary" type="submit">Réinitialiser le mot de passe</button>
              </form>
            <?php endif; ?>
            <form method="post" class="delete-form" onsubmit="return confirm('Supprimer cette inscription et ses retours ?');">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="key" value="<?= e((string) $row['key']) ?>">
              <button class="button secondary danger" type="submit">Supprimer</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
