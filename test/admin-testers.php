<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/assets/tester-receipts.php';
vv_admin_session_start();
vv_admin_require();
$authenticatedUser = vv_admin_email();
$csrf = vv_admin_csrf();

function vv_admin_spreadsheet_safe_cell(string $value): string
{
    return preg_match('/^[=+\\-@]/u', $value) === 1 ? "'" . $value : $value;
}

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
                vv_admin_spreadsheet_safe_cell((string) $row['email']),
                vv_admin_spreadsheet_safe_cell((string) ($row['device_model'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($row['android_version'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($row['sim_setup'] ?? '')),
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
            ['email','updated_at','submitted_at','device_model','android_version','sim_setup','tested_variant','tested_version','tested_revision'],
            $answerKeys,
            $commentKeys
        );
        $lines = [implode(';', $headers)];

        foreach (vv_get_feedback_responses() as $response) {
            $email = (string) ($response['email'] ?? '');
            $tester = vv_get_tester($email) ?? [];
            $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
            $values = [
                vv_admin_spreadsheet_safe_cell($email),
                vv_admin_spreadsheet_safe_cell((string) ($response['updated_at'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($response['submitted_at'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($tester['device_model'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($tester['android_version'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($tester['sim_setup'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($answers['tested_variant'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($answers['tested_version'] ?? '')),
                vv_admin_spreadsheet_safe_cell((string) ($answers['tested_revision'] ?? '')),
            ];
            foreach (array_merge($answerKeys, $commentKeys) as $key) {
                $values[] = vv_admin_spreadsheet_safe_cell((string) ($answers[$key] ?? ''));
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
        if ($action === 'logout') {
            vv_admin_logout();
            header('Location: /test/login.php', true, 303);
            exit;
        } elseif ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $next = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if (!vv_admin_password_valid($next) || !hash_equals($next, $confirm)) {
                $notice = 'Le nouveau mot de passe doit compter 12 à 72 octets et être confirmé.';
            } elseif (!vv_admin_change_password($current, $next)) {
                $notice = 'Mot de passe actuel incorrect ou nouveau mot de passe identique.';
            } else {
                vv_admin_logged_in();
                $csrf = vv_admin_csrf();
                $notice = 'Mot de passe changé. Les autres sessions et codes en attente ont été révoqués.';
            }
        } elseif ($action === 'send_receipts') {
            if (($_POST['confirm_receipts'] ?? '') !== '1') {
                $notice = 'Confirme explicitement l’envoi des e-mails avant de continuer.';
            } else {
                $scope = (string) ($_POST['receipt_scope'] ?? '');
                $postedKeys = $_POST['recipient_keys'] ?? [];
                if (!is_array($postedKeys)) {
                    throw new InvalidArgumentException('Sélection de candidatures invalide.');
                }
                $results = vv_receipt_send_batch($scope, $postedKeys);
                $notice = 'Confirmations confiées au serveur de messagerie : ' . $results['accepted']
                    . ' ; échecs : ' . $results['failed']
                    . ' ; déjà traitées ou ignorées : ' . $results['skipped']
                    . ' ; candidatures encore à confirmer : ' . $results['remaining']
                    . '. La réception finale des messages ne peut pas être garantie.';
                $rows = vv_get_testers();
            }
        } elseif ($action === 'delete') {
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
                    ? 'Accès autorisé. Le testeur peut recevoir son code par e-mail sur https://vevak.lepotager.org/retours/.'
                    : 'Accès au questionnaire suspendu.';
            }
            $rows = vv_get_testers();
        }
    }
} catch (Throwable $error) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    $rows = [];
    $notice = $error->getMessage();
}

$receiptPending = count(array_filter($rows, 'vv_receipt_is_pending'));

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
    .admin-wrap{width:min(1100px,calc(100% - 2rem));margin:2rem auto 4rem}.admin-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin:1rem 0 1.5rem}.admin-invite{display:grid;gap:.45rem;margin:1rem 0;padding:1rem;border:1px solid #9fcab6;border-radius:.8rem;background:#eef8f3}.admin-invite input{width:100%;box-sizing:border-box;padding:.7rem;border:1px solid #c8d8d1;border-radius:.55rem;font:inherit}.admin-table-wrap{overflow-x:auto;border:1px solid #d8e3de;border-radius:1rem;background:#fff}.admin-table{width:100%;border-collapse:collapse;min-width:760px}.admin-table th,.admin-table td{padding:.8rem;border-bottom:1px solid #e6ece9;text-align:left;vertical-align:top}.admin-table th{background:#eef5f1;color:#17332c}.admin-table code{font-size:.82rem}.danger{border-color:#b24b43!important;color:#7a211b!important}.admin-notice{padding:.8rem 1rem;background:#eef5f1;border-radius:.7rem}.admin-meta{color:#526760}.delete-form{margin:0 0 .45rem}.admin-table td .button{white-space:nowrap}.receipt-preview{margin:.75rem 0 1rem}.receipt-preview summary{cursor:pointer;font-weight:700}.receipt-preview pre{overflow-wrap:anywhere;white-space:pre-wrap;font:inherit;padding:1rem;background:#fff;border:1px solid #d8e3de;border-radius:.65rem;line-height:1.55}.receipt-send-form{display:flex;align-items:center;flex-wrap:wrap;gap:.8rem;margin-top:.6rem}.receipt-send-form .button{cursor:pointer}.receipt-consent{display:flex;align-items:flex-start;gap:.5rem;flex-basis:100%;font-weight:650}.receipt-consent input{margin-top:.27rem}.receipt-selection{display:block;white-space:nowrap}.receipt-selection input{margin-right:.35rem}.receipt-status{font-size:.83rem;color:#456258}.receipt-status.sent{color:#176349}
  </style>
</head>
<body>
<main class="admin-wrap">
  <p class="kicker">VeVak · administration privée</p>
  <h1>Demandes de test Google Play</h1>
  <p class="admin-meta">Connecté avec mot de passe et code e-mail : <?= e($authenticatedUser) ?>. <?= count($rows) ?> inscription(s).</p>
  <?php if ($notice !== ''): ?><p class="admin-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
  <div class="admin-actions">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="logout">
      <button class="button secondary" type="submit">Se déconnecter</button>
    </form>
    <a class="button primary" href="?export=play">Exporter pour Google Play</a>
    <a class="button secondary" href="?export=spreadsheet">Exporter pour tableur</a>
    <a class="button secondary" href="?export=feedback">Exporter les réponses</a>
    <a class="button secondary" href="../#devenir-testeur">Voir le formulaire public</a>
    <a class="button secondary" href="../retours/">Voir l’espace retours</a>
  </div>
  <p class="admin-meta"><strong>Export Google Play :</strong> un e-mail par ligne, aucun en-tête, aucune virgule et aucun BOM UTF-8. L’export « tableur » ajoute le téléphone, Android, la SIM et l’état du questionnaire ; il ne doit pas être importé dans Play Console.</p>
  <p class="admin-meta"><strong>Espace retours :</strong> une inscription publique n’ouvre pas automatiquement l’accès. Active « Autoriser les retours » uniquement pour les personnes réellement retenues. Elles recevront un code à usage unique par e-mail pour se connecter sur <code>/retours/</code>.</p>

  <section class="admin-invite" id="confirmer-inscriptions" aria-labelledby="receipt-title">
    <h2 id="receipt-title">Confirmer la réception des inscriptions 📩</h2>
    <p><strong><?= $receiptPending ?> candidature(s)</strong> n’ont pas encore reçu d’accusé de réception par e-mail.</p>
    <p>Tu peux contacter toutes les candidatures en attente ou seulement certaines. Le message confirme leur inscription et annonce l’arrivée prochaine des instructions pour télécharger et tester VeVak. Il ne donne pas encore accès au test fermé.</p>
    <details class="receipt-preview">
      <summary>Prévisualiser l’e-mail envoyé</summary>
      <p><strong>Objet :</strong> <?= e(vv_receipt_subject()) ?></p>
      <pre><?= e(vv_receipt_body()) ?></pre>
    </details>
    <form id="receipt-send-form" method="post" class="receipt-send-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="send_receipts">
      <label class="receipt-consent">
        <input type="checkbox" name="confirm_receipts" value="1" required>
        Je confirme vouloir envoyer ces e-mails aux personnes concernées.
      </label>
      <button class="button primary" name="receipt_scope" value="pending" type="submit" <?= $receiptPending < 1 ? 'disabled' : '' ?>>Envoyer aux inscriptions en attente (20 max.)</button>
      <button class="button secondary" name="receipt_scope" value="selected" type="submit" <?= $receiptPending < 1 ? 'disabled' : '' ?>>Envoyer aux personnes sélectionnées</button>
    </form>
    <p class="admin-meta">Un message individuel est envoyé à chaque adresse. Le statut « envoyé » signifie accepté par le serveur mail, pas nécessairement livré en boîte de réception. Les échecs peuvent être réessayés et les envois déjà acceptés sont exclus automatiquement. Pour plus de 20 candidatures, relance l’opération sur les inscriptions restantes.</p>
  </section>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>E-mail</th><th>Confirmation</th><th>Appareil</th><th>Android / SIM</th><th>Retours</th><th>Dates</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7">Aucune demande enregistrée.</td></tr>
      <?php else: foreach ($rows as $row): ?>
        <?php
          $feedbackConsent = !empty($row['feedback_consent']);
          $feedbackEnabled = !empty($row['feedback_enabled']);
          $feedbackData = vv_feedback_get_answers((string) $row['email']);
          $feedbackSubmittedAt = (string) ($feedbackData['submitted_at'] ?? '');
          $feedbackUpdatedAt = (string) ($feedbackData['updated_at'] ?? '');
        ?>
        <tr>
          <td><?= e((string) $row['email']) ?><br><small><code><?= e((string) ($row['consent_version'] ?? '')) ?></code></small></td>
          <td>
            <?php if (vv_receipt_is_pending($row)): ?>
              <label class="receipt-selection"><input form="receipt-send-form" type="checkbox" name="recipient_keys[]" value="<?= e((string) $row['key']) ?>">Sélectionner</label>
              <small class="receipt-status">En attente</small>
            <?php elseif (!empty($row['receipt_sent_at'])): ?>
              <span class="receipt-status sent">✓ Accepté par le serveur mail</span>
              <small><?= e((string) $row['receipt_sent_at']) ?> (UTC)</small>
            <?php else: ?>
              <span class="receipt-status">Envoi non disponible (consentement ou adresse à vérifier)</span>
            <?php endif; ?>
          </td>
          <td><?= e((string) ($row['device_model'] ?? '—')) ?></td>
          <td><?= e((string) ($row['android_version'] ?? '—')) ?><br><small><?= e((string) ($row['sim_setup'] ?? '—')) ?></small></td>
          <td>
            <strong><?= $feedbackConsent ? 'Accord ✓' : 'Pas d’accord' ?></strong><br>
            <small><?= $feedbackEnabled ? 'Accès autorisé' : 'Accès non autorisé' ?> · connexion par code e-mail</small><br>
            <small>Version testée : <?= e(match ((string) ($feedbackData['answers']['tested_variant'] ?? '')) { 'foss' => 'FOSS', 'play' => 'Google Play', 'unknown' => 'Non identifiée', default => 'Non renseignée' }) ?><?= !empty($feedbackData['answers']['tested_version']) ? ' · ' . e((string) $feedbackData['answers']['tested_version']) : '' ?></small><br>
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
  <section class="admin-invite" aria-labelledby="password-heading" style="max-width:600px;margin-top:2rem">
    <h2 id="password-heading">Modifier mon mot de passe</h2>
    <p>Ton nouveau mot de passe doit contenir au moins 12 caractères (72 octets maximum). Les autres sessions sont révoquées après modification.</p>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="change_password">
      <label for="current_password">Mot de passe actuel</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      <label for="new_password">Nouveau mot de passe</label>
      <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="12" required>
      <label for="confirm_password">Confirmer le nouveau mot de passe</label>
      <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="12" required>
      <button class="button primary" type="submit">Enregistrer mon nouveau mot de passe</button>
    </form>
  </section>
</main>
</body>
</html>
