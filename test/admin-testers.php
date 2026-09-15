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
        $lines = ['email'];
        foreach ($rows as $row) {
            $safe = vv_spreadsheet_safe_email((string) $row['email']);
            $lines[] = '"' . str_replace('"', '""', $safe) . '"';
        }
        vv_admin_csv_download('vevak-testers-tableur.csv', $lines);
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
            $notice = vv_delete_tester($key) ? 'Inscription supprimée.' : 'Inscription déjà absente.';
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
    .admin-wrap{width:min(1100px,calc(100% - 2rem));margin:2rem auto 4rem}.admin-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin:1rem 0 1.5rem}.admin-table-wrap{overflow-x:auto;border:1px solid #d8e3de;border-radius:1rem;background:#fff}.admin-table{width:100%;border-collapse:collapse;min-width:760px}.admin-table th,.admin-table td{padding:.8rem;border-bottom:1px solid #e6ece9;text-align:left;vertical-align:top}.admin-table th{background:#eef5f1;color:#17332c}.admin-table code{font-size:.82rem}.danger{border-color:#b24b43!important;color:#7a211b!important}.admin-notice{padding:.8rem 1rem;background:#eef5f1;border-radius:.7rem}.admin-meta{color:#526760}.delete-form{margin:0}
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
    <a class="button secondary" href="../#devenir-testeur">Voir le formulaire public</a>
  </div>
  <p class="admin-meta"><strong>Export Google Play :</strong> un e-mail par ligne, aucun en-tête, aucune virgule et aucun BOM UTF-8. L’export « tableur » protège les adresses commençant par un caractère interprétable comme formule et ne doit donc pas être importé dans Play Console.</p>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>E-mail</th><th>Créée</th><th>Dernière demande</th><th>Accord</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5">Aucune demande enregistrée.</td></tr>
      <?php else: foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['email']) ?></td>
          <td><?= e((string) ($row['created_at'] ?? '')) ?></td>
          <td><?= e((string) ($row['last_requested_at'] ?? '')) ?></td>
          <td><code><?= e((string) ($row['consent_version'] ?? '')) ?></code></td>
          <td>
            <form method="post" class="delete-form" onsubmit="return confirm('Supprimer cette inscription ?');">
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
