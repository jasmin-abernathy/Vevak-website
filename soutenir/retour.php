<?php
declare(strict_types=1);

define('CONTRIBUTION_SESSION_NAME', 'vevak_contribution');
define('CONTRIBUTION_SITE_ID', 'VeVak');
define('CONTRIBUTION_SITE_LABEL', 'VeVak');
define('CONTRIBUTION_SITE_HOST', 'vevak.lepotager.org');
define('CONTRIBUTION_RETURN_URL', 'https://vevak.lepotager.org/soutenir/retour.php');
require __DIR__ . '/stancer-contribution.php';

contribution_start_session();
contribution_headers();

$state = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['state'] ?? ''))) ?? '';
$record = $state !== '' ? ($_SESSION['contribution_records'][$state] ?? null) : null;
$error = $_SESSION['contribution_error'] ?? null;
unset($_SESSION['contribution_error']);
$lang = is_array($record) ? (string)($record['lang'] ?? 'fr') : (is_array($error) ? (string)($error['lang'] ?? 'fr') : 'fr');
$details = ['kind'=>'pending','label'=>$lang === 'en' ? 'Verification in progress' : 'Vérification en cours'];

if (is_array($record) && !empty($record['payment_intent_id'])) {
    try {
        $intent = contribution_request('GET', '/payment_intents/' . rawurlencode((string)$record['payment_intent_id']));
        $payment = null;
        $paymentId = (string)($intent['payment'] ?? '');
        if (preg_match('/^paym_[A-Za-z0-9]{24}$/', $paymentId)) {
            $payment = contribution_request('GET', '/payments/' . rawurlencode($paymentId));
        }
        $details = contribution_status($intent, $payment);
    } catch (Throwable $e) {
        error_log('[VeVak contribution return] ' . $e->getMessage());
    }
}
if (is_array($error)) $details = ['kind'=>'error','label'=>$lang === 'en' ? 'Not started' : 'Non démarrée'];

$isEn = $lang === 'en';
$title = $details['kind'] === 'success'
    ? ($isEn ? 'Thank you for your contribution' : 'Merci pour ta contribution')
    : ($details['kind'] === 'error' ? ($isEn ? 'The contribution did not start' : 'La contribution n’a pas démarré') : ($isEn ? 'Contribution being verified' : 'Contribution en cours de vérification'));
$message = $details['kind'] === 'success'
    ? ($isEn ? 'Stancer confirms that the payment was completed. Thank you for helping VeVak move forward.' : 'Stancer confirme que le paiement a abouti. Merci d’aider VeVak à avancer.')
    : ($details['kind'] === 'error' ? ($isEn ? 'No payment is shown as confirmed from this attempt.' : 'Aucun paiement n’est indiqué comme confirmé pour cette tentative.') : ($isEn ? 'Stancer or your bank may still be finalising the operation. Avoid immediately starting a second payment.' : 'Stancer ou ta banque peut encore finaliser l’opération. Évite de relancer immédiatement un second paiement.'));
?>
<!doctype html>
<html lang="<?= $isEn ? 'en' : 'fr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= contribution_escape($title) ?> — VeVak</title>
  <link rel="stylesheet" href="../assets/styles.css">
  <link rel="stylesheet" href="../assets/support.css">
</head>
<body>
<main id="main">
  <section class="support-hero"><div class="wrap statement"><p class="eyebrow"><?= $isEn ? 'Stancer return' : 'Retour Stancer' ?></p><h1><?= contribution_escape($title) ?>.</h1><p class="lead"><?= contribution_escape($message) ?></p></div></section>
  <section class="section compact"><div class="wrap"><article class="support-promise"><h2><?= $isEn ? 'Status' : 'État' ?> : <?= contribution_escape((string)$details['label']) ?></h2>
  <?php if(is_array($record)):?><p><?= $isEn ? 'Amount' : 'Montant' ?> : <strong><?= contribution_escape(number_format(((int)$record['amount_cents'])/100,2,',',' ')) ?> €</strong></p><?php endif;?>
  <p><a class="button primary" href="<?= $isEn ? '../en/support/' : './' ?>"><?= $isEn ? 'Back to support page' : 'Retour à la page de soutien' ?></a></p></article></div></section>
</main>
</body>
</html>
