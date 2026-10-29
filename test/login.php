<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
vv_admin_session_start();

if (vv_admin_is_authenticated()) {
    header('Location: /test/admin-testers.php', true, 303);
    exit;
}

$notice = '';
$mode = (string) ($_SESSION['vv_admin_flow'] ?? '');
$initialized = false;

try {
    $initialized = vv_admin_is_initialized();
    if (isset($_GET['cancel'])) {
        unset($_SESSION['vv_admin_flow'], $_SESSION['vv_admin_nonce']);
        $mode = '';
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!vv_admin_validate_csrf((string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('La session a expiré. Recharge cette page.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if ($action === 'setup_send' && !$initialized) {
            $nonce = bin2hex(random_bytes(24));
            if (!vv_admin_request_code('setup', $nonce, $ip)) {
                throw new RuntimeException('Le code n’a pas pu être envoyé. Vérifie la messagerie du serveur.');
            }
            $_SESSION['vv_admin_flow'] = 'setup';
            $_SESSION['vv_admin_nonce'] = $nonce;
            $notice = 'Code envoyé sur la boîte administrateur. Il est valable dix minutes.';
        } elseif ($action === 'login_password' && $initialized) {
            if (!vv_admin_rate_limit('password', $ip, 10)) {
                throw new RuntimeException('Trop de tentatives. Réessaie dans quinze minutes.');
            }
            if (!vv_admin_check_password((string) ($_POST['password'] ?? ''))) {
                throw new RuntimeException('Mot de passe incorrect.');
            }
            $nonce = bin2hex(random_bytes(24));
            if (!vv_admin_request_code('login', $nonce, $ip)) {
                throw new RuntimeException('Le code n’a pas pu être envoyé. Vérifie la messagerie du serveur.');
            }
            $_SESSION['vv_admin_flow'] = 'login';
            $_SESSION['vv_admin_nonce'] = $nonce;
            $notice = 'Mot de passe validé. Saisis le code reçu par e-mail.';
        } elseif ($action === 'reset_send' && $initialized) {
            $nonce = bin2hex(random_bytes(24));
            if (!vv_admin_request_code('reset', $nonce, $ip)) {
                throw new RuntimeException('Le code n’a pas pu être envoyé. Réessaie plus tard.');
            }
            $_SESSION['vv_admin_flow'] = 'reset';
            $_SESSION['vv_admin_nonce'] = $nonce;
            $notice = 'Si la messagerie fonctionne, un code de réinitialisation vient d’être envoyé.';
        } elseif ($action === 'verify') {
            $mode = (string) ($_SESSION['vv_admin_flow'] ?? '');
            $nonce = (string) ($_SESSION['vv_admin_nonce'] ?? '');
            if (!in_array($mode, ['setup', 'login', 'reset'], true) || $nonce === '') {
                throw new RuntimeException('Demande expirée. Recommence la connexion.');
            }
            $newPassword = null;
            if ($mode !== 'login') {
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if (!vv_admin_password_valid($newPassword)) {
                    throw new RuntimeException('Choisis un mot de passe de 12 à 72 caractères (octets UTF-8).');
                }
                if (!hash_equals($newPassword, (string) ($_POST['confirm_password'] ?? ''))) {
                    throw new RuntimeException('Les deux mots de passe sont différents.');
                }
            }
            if (!vv_admin_rate_limit('verify', $ip, 20) ||
                !vv_admin_verify_code($mode, $nonce, trim((string) ($_POST['code'] ?? '')), $newPassword)) {
                throw new RuntimeException('Code incorrect, expiré ou trop de tentatives. Recommence si nécessaire.');
            }
            vv_admin_logged_in();
            header('Location: /test/admin-testers.php', true, 303);
            exit;
        } else {
            throw new RuntimeException('Action indisponible. Recharge la page.');
        }
    }
} catch (Throwable $error) {
    $notice = $error instanceof RuntimeException ? $error->getMessage() : 'Une erreur est survenue. Réessaie.';
    if (!($error instanceof RuntimeException)) {
        error_log('VeVak admin auth: ' . get_class($error));
    }
}

$mode = (string) ($_SESSION['vv_admin_flow'] ?? '');
$showCode = in_array($mode, ['setup', 'login', 'reset'], true);
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Connexion administrateur — VeVak</title>
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../assets/styles.css?v=20261029-eye">
  <style>
    .auth-page{min-height:100vh;display:grid;place-items:center;padding:2rem 1rem;background:#f3f8f5;color:#17332c}
    .auth-card{width:min(100%,480px);padding:clamp(1.5rem,5vw,2.5rem);border:1px solid #d4e6df;border-radius:1.2rem;background:white;box-shadow:0 14px 40px rgba(13,53,36,.07)}
    .auth-card h1{font-size:clamp(1.7rem,4vw,2.25rem);line-height:1.15;margin:.4rem 0 1rem}
    .auth-card p{line-height:1.5}.auth-card label{display:block;font-weight:700;margin:1rem 0 .4rem}
    .auth-card input{width:100%;box-sizing:border-box;border:1px solid #a8c3b7;border-radius:.6rem;padding:.8rem;font:inherit}
    .auth-card input:focus-visible{outline:3px solid #9ed7b9;outline-offset:2px}
    .auth-card button{margin-top:1.2rem;width:100%;cursor:pointer}.auth-note{padding:.8rem 1rem;border:1px solid #b7d7c4;background:#f0faf3;border-radius:.7rem}
    .auth-small{font-size:.9rem;color:#456258}.auth-links{display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem}
    .auth-links a{color:#185f48;text-decoration:underline;text-underline-offset:3px}
  </style>
</head>
<body>
<main class="auth-page">
  <section class="auth-card" aria-labelledby="auth-title">
    <p class="kicker">VeVak · Administration</p>
    <h1 id="auth-title"><?= $showCode ? 'Vérification par e-mail' : ($initialized ? 'Se connecter' : 'Activer mon accès') ?></h1>
    <?php if ($notice !== ''): ?><p class="auth-note" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($showCode): ?>
      <p>Saisis le code à huit chiffres reçu sur la boîte administrateur. Il expire au bout de 10 minutes.</p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= h(vv_admin_csrf()) ?>">
        <input type="hidden" name="action" value="verify">
        <label for="otp">Code reçu par e-mail</label>
        <input id="otp" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{8}" maxlength="8" required autofocus>
        <?php if ($mode === 'setup' || $mode === 'reset'): ?>
          <label for="new-password">Nouveau mot de passe</label>
          <input id="new-password" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
          <label for="confirm-password">Confirmer le mot de passe</label>
          <input id="confirm-password" name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
          <p class="auth-small">12 caractères minimum, 72 octets maximum. Utilise de préférence un gestionnaire de mots de passe.</p>
        <?php endif; ?>
        <button class="button primary" type="submit"><?= $mode === 'setup' ? 'Activer le compte' : ($mode === 'reset' ? 'Réinitialiser et se connecter' : 'Valider la connexion') ?></button>
      </form>
      <div class="auth-links"><a href="?cancel=1">Recommencer</a></div>
    <?php elseif (!$initialized): ?>
      <p>Ce compte ne peut être activé qu’en recevant un code sur l’adresse administrateur configurée côté serveur.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(vv_admin_csrf()) ?>">
        <input type="hidden" name="action" value="setup_send">
        <button class="button primary" type="submit">Recevoir mon code d’activation</button>
      </form>
    <?php else: ?>
      <p>Ton mot de passe est la première étape. Un code temporaire sera ensuite envoyé par e-mail.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(vv_admin_csrf()) ?>">
        <input type="hidden" name="action" value="login_password">
        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
        <button class="button primary" type="submit">Continuer</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(vv_admin_csrf()) ?>">
        <input type="hidden" name="action" value="reset_send">
        <button class="button secondary" type="submit">Mot de passe oublié ?</button>
      </form>
    <?php endif; ?>
    <div class="auth-links"><a href="../">Retour au site VeVak</a></div>
  </section>
</main>
</body>
</html>
