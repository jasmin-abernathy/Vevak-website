<?php
declare(strict_types=1);

require __DIR__ . '/tester-storage.php';

const VEVAK_TESTER_SUCCESS = 'Merci ! Ta demande a bien été prise en compte. Si cette adresse est déjà inscrite, elle sera conservée une seule fois. Les indications pour rejoindre le test seront envoyées après traitement des demandes.';
const VEVAK_TESTER_INVALID_EMAIL = 'Vérifie ton adresse e-mail avant de continuer.';
const VEVAK_TESTER_MISSING_CONSENT = 'Confirme que tu souhaites utiliser cette adresse pour participer aux tests.';
const VEVAK_TESTER_SERVER_ERROR = 'Ta demande n’a pas pu être enregistrée. Réessaie dans un instant.';

function vv_is_json_request(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

function vv_reply(string $message, int $status, bool $success, string $email = '', bool $consent = false): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    if (vv_is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $checked = $consent ? ' checked' : '';
    $title = $success ? 'Demande enregistrée' : 'Vérification nécessaire';
    $form = '';
    if (!$success) {
        $form = <<<HTML
        <form method="post" action="/assets/tester-submit.php" class="tester-fallback-form">
          <label for="tester-email">Adresse e-mail de ton compte Google Play</label>
          <input id="tester-email" name="email" type="email" autocomplete="email" autocapitalize="none" autocorrect="off" spellcheck="false" maxlength="254" required value="{$safeEmail}">
          <label class="tester-fallback-consent"><input type="checkbox" name="consent" value="1" required{$checked}> J’accepte que mon adresse soit utilisée pour gérer ma participation aux tests de VeVak et me contacter à ce sujet.</label>
          <div class="tester-trap" aria-hidden="true"><label>Site web <input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
          <button type="submit">Demander à rejoindre les tests</button>
        </form>
        HTML;
    }

    echo <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{$title} — VeVak</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <link rel="stylesheet" href="/assets/tester-form.css">
</head>
<body>
  <main class="tester-fallback-page">
    <section class="tester-card">
      <p class="kicker">VeVak · Google Play</p>
      <h1>{$title}</h1>
      <p class="tester-status" role="status">{$safeMessage}</p>
      {$form}
      <p><a href="/#devenir-testeur">Retour à l’inscription sur l’accueil</a></p>
      <p><a href="/confidentialite/">Confidentialité</a></p>
    </section>
  </main>
</body>
</html>
HTML;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    vv_reply(VEVAK_TESTER_SERVER_ERROR, 405, false);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 8192) {
    vv_reply(VEVAK_TESTER_SERVER_ERROR, 413, false);
}

$email = trim((string) ($_POST['email'] ?? ''));
$consent = (string) ($_POST['consent'] ?? '') === '1';
$honeypot = trim((string) ($_POST['website'] ?? ''));

try {
    if (!vv_rate_limit((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
        vv_reply(VEVAK_TESTER_SERVER_ERROR, 429, false, $email, $consent);
    }

    if ($honeypot !== '') {
        vv_reply(VEVAK_TESTER_SUCCESS, 200, true);
    }

    if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        vv_reply(VEVAK_TESTER_INVALID_EMAIL, 422, false, $email, $consent);
    }
    if (!$consent) {
        vv_reply(VEVAK_TESTER_MISSING_CONSENT, 422, false, $email, false);
    }

    vv_register_tester($email);
    vv_reply(VEVAK_TESTER_SUCCESS, 200, true);
} catch (Throwable $error) {
    error_log('VeVak tester registration storage error: ' . get_class($error));
    vv_reply(VEVAK_TESTER_SERVER_ERROR, 500, false, $email, $consent);
}
