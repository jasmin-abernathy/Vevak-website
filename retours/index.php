<?php
declare(strict_types=1);

require dirname(__DIR__) . '/assets/tester-storage.php';

function vf_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start(['use_strict_mode' => true]);
}

function vf_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vf_csrf(): string
{
    vf_session_start();
    if (empty($_SESSION['vevak_feedback_csrf'])) {
        $_SESSION['vevak_feedback_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['vevak_feedback_csrf'];
}

function vf_check_csrf(): void
{
    $posted = (string) ($_POST['_csrf'] ?? '');
    if ($posted === '' || !hash_equals(vf_csrf(), $posted)) {
        http_response_code(419);
        throw new RuntimeException('La session a expiré. Recharge la page puis réessaie.');
    }
}

function vf_json_request(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

function vf_json_reply(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        ['success' => $success, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function vf_logged_email(): string
{
    vf_session_start();
    return trim((string) ($_SESSION['vevak_feedback_email'] ?? ''));
}

function vf_logout(): void
{
    vf_session_start();
    unset($_SESSION['vevak_feedback_email']);
    session_regenerate_id(true);
}

function vf_mark_logged(string $email): void
{
    vf_session_start();
    session_regenerate_id(true);
    $_SESSION['vevak_feedback_email'] = vv_normalize_email_key($email);
}

function vf_question(
    int $number,
    int $total,
    string $answerName,
    string $commentName,
    string $title,
    string $intro,
    array $options,
    array $answers
): void {
    $answer = (string) ($answers[$answerName] ?? '');
    $comment = (string) ($answers[$commentName] ?? '');
    ?>
    <section class="feedback-question" data-feedback-step data-answer-name="<?= vf_e($answerName) ?>" <?= $number === 1 ? '' : 'hidden' ?>>
      <div class="feedback-question-head">
        <div class="feedback-number"><?= $number ?></div>
        <div>
          <p class="kicker">Question <?= $number ?> sur <?= $total ?></p>
          <h2><?= vf_e($title) ?></h2>
          <p><?= vf_e($intro) ?></p>
        </div>
      </div>

      <fieldset>
        <legend>Ta réponse</legend>
        <div class="feedback-choices">
          <?php foreach ($options as $value => $label): ?>
            <?php $id = $answerName . '-' . substr(hash('sha256', (string) $value), 0, 8); ?>
            <div class="feedback-choice">
              <input
                id="<?= vf_e($id) ?>"
                type="radio"
                name="<?= vf_e($answerName) ?>"
                value="<?= vf_e((string) $value) ?>"
                <?= hash_equals($answer, (string) $value) ? 'checked' : '' ?>
              >
              <label for="<?= vf_e($id) ?>"><?= vf_e((string) $label) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="feedback-comment">
        <label for="<?= vf_e($commentName) ?>">Commentaire libre sur cette question</label>
        <p>Facultatif. Décris ce que tu as vu, ce qui t’a surpris ou ce qui pourrait être amélioré.</p>
        <textarea id="<?= vf_e($commentName) ?>" name="<?= vf_e($commentName) ?>" maxlength="3000"><?= vf_e($comment) ?></textarea>
      </div>

      <div class="feedback-actions">
        <?php if ($number > 1): ?>
          <button class="button secondary" type="button" data-feedback-prev>← Question précédente</button>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <div class="right">
          <?php if ($number < $total): ?>
            <button class="button primary" type="button" data-feedback-next>Question suivante →</button>
          <?php else: ?>
            <button class="button primary" type="button" data-feedback-finish>Envoyer mon retour</button>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php
}

vf_session_start();

$error = '';
$notice = '';
$action = (string) ($_POST['action'] ?? '');
$requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$inviteFromUrl = trim((string) ($_GET['invite'] ?? ''));
if ($requestMethod === 'GET' && $inviteFromUrl !== '') {
    $inviteEmail = vv_feedback_invite_email($inviteFromUrl);
    if ($inviteEmail === null) {
        unset($_SESSION['vevak_feedback_invite'], $_SESSION['vevak_feedback_invite_email']);
        $error = 'Ce lien d’activation est invalide, expiré ou a déjà été remplacé.';
    } else {
        $_SESSION['vevak_feedback_invite'] = $inviteFromUrl;
        $_SESSION['vevak_feedback_invite_email'] = vv_normalize_email_key($inviteEmail);
        header('Location: ./?activate=1');
        exit;
    }
}

if ($requestMethod === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
    http_response_code(413);
    if (vf_json_request()) {
        vf_json_reply(false, 'La requête est trop volumineuse.', 413);
    }
    exit('Requête trop volumineuse.');
}

try {
    if ($requestMethod === 'POST' && $action === 'logout') {
        vf_check_csrf();
        vf_logout();
        header('Location: ./');
        exit;
    }

    if ($requestMethod === 'POST' && $action === 'create_account') {
        vf_check_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $inviteToken = (string) ($_SESSION['vevak_feedback_invite'] ?? '');
        $inviteEmail = (string) ($_SESSION['vevak_feedback_invite_email'] ?? '');

        if (!vv_feedback_rate_limit((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $email)) {
            throw new RuntimeException('Trop de tentatives. Réessaie dans quelques minutes.');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Vérifie ton adresse e-mail.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('Les deux mots de passe ne correspondent pas.');
        }
        if (strlen($password) > 256) {
            throw new RuntimeException('Le mot de passe est trop long.');
        }
        if (
            $inviteToken === ''
            || $inviteEmail === ''
            || !hash_equals($inviteEmail, vv_normalize_email_key($email))
        ) {
            throw new RuntimeException('Ce lien d’activation ne correspond pas à cette adresse ou a expiré.');
        }

        $result = vv_feedback_create_account($email, $password, $inviteToken);
        if (!empty($result['ok'])) {
            unset($_SESSION['vevak_feedback_invite'], $_SESSION['vevak_feedback_invite_email']);
            vf_mark_logged($email);
            header('Location: ./');
            exit;
        }

        $reason = (string) ($result['reason'] ?? '');
        $error = match ($reason) {
            'weak' => 'Choisis un mot de passe d’au moins 12 caractères.',
            'too_long' => 'Le mot de passe est trop long.',
            'exists' => 'Un mot de passe existe déjà pour cette adresse. Utilise la connexion.',
            'invite' => 'Ce lien d’activation est invalide, expiré ou ne correspond pas à cette adresse.',
            default => 'Cette adresse n’est pas autorisée pour l’espace de retours.',
        };
    }

    if ($requestMethod === 'POST' && $action === 'login') {
        vf_check_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (strlen($password) > 256) {
            throw new RuntimeException('Adresse ou mot de passe incorrect.');
        }

        if (!vv_feedback_rate_limit((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $email)) {
            throw new RuntimeException('Trop de tentatives. Réessaie dans quelques minutes.');
        }

        if ($email !== '' && vv_feedback_verify_password($email, $password)) {
            vf_mark_logged($email);
            header('Location: ./');
            exit;
        }

        $error = 'Adresse ou mot de passe incorrect.';
    }

    $loggedEmail = vf_logged_email();
    $tester = $loggedEmail !== '' ? vv_get_tester($loggedEmail) : null;

    if (
        $loggedEmail !== ''
        && (
            $tester === null
            || empty($tester['feedback_consent'])
            || empty($tester['feedback_enabled'])
        )
    ) {
        vf_logout();
        $loggedEmail = '';
        $tester = null;
        $error = 'Ton accès au questionnaire n’est pas actif.';
    }

    if ($requestMethod === 'POST' && $action === 'save') {
        vf_check_csrf();

        if ($loggedEmail === '' || $tester === null) {
            if (vf_json_request()) {
                vf_json_reply(false, 'Ta session a expiré. Reconnecte-toi.', 401);
            }
            throw new RuntimeException('Ta session a expiré. Reconnecte-toi.');
        }

        $allowedAnswers = [
            'q1_launch' => ['very_clear', 'mostly_clear', 'confusing', 'blocked'],
            'q2_permissions' => ['yes', 'mostly', 'no', 'not_sure'],
            'q3_contact' => ['easy', 'hard', 'no', 'not_tested'],
            'q4_sms' => ['yes', 'slow', 'no', 'not_tested'],
            'q5_location' => ['yes', 'approximate', 'wrong', 'none'],
            'q6_locked' => ['yes', 'delayed', 'no', 'not_tested'],
            'q7_reboot' => ['yes', 'partial', 'no', 'not_tested'],
            'q8_revoke' => ['blocked', 'weird', 'failed', 'not_tested'],
            'q9_manual' => ['yes', 'mostly', 'no', 'not_tested'],
            'q10_notifications' => ['yes', 'partial', 'no', 'not_tested'],
            'q11_trust' => ['yes', 'mostly_yes', 'mostly_no', 'no'],
            'q12_missing' => ['nothing', 'explanations', 'reliability', 'settings'],
        ];
        $answerKeys = array_keys($allowedAnswers);
        $commentKeys = array_map(
            static fn(string $key): string => 'comment_' . $key,
            $answerKeys
        );

        $answers = [];
        foreach ($answerKeys as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($value !== '' && !in_array($value, $allowedAnswers[$key], true)) {
                throw new RuntimeException('Une réponse n’est pas reconnue.');
            }
            $answers[$key] = $value;
        }
        foreach ($commentKeys as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if (strlen($value) > 3000) {
                throw new RuntimeException('Un commentaire est trop long.');
            }
            $answers[$key] = $value;
        }

        $submitted = (string) ($_POST['submitted'] ?? '') === '1';
        vv_feedback_save_answers($loggedEmail, $answers, $submitted);

        if (vf_json_request()) {
            vf_json_reply(true, $submitted ? 'Questionnaire enregistré.' : 'Réponses sauvegardées.');
        }

        $notice = $submitted
            ? 'Merci, ton questionnaire a bien été enregistré.'
            : 'Tes réponses ont été sauvegardées.';
    }
} catch (Throwable $exception) {
    if (vf_json_request()) {
        vf_json_reply(false, $exception->getMessage(), http_response_code() >= 400 ? http_response_code() : 422);
    }
    $error = $exception->getMessage();
}

$loggedEmail = vf_logged_email();
$tester = $loggedEmail !== '' ? vv_get_tester($loggedEmail) : null;
$stored = $loggedEmail !== '' ? vv_feedback_get_answers($loggedEmail) : [];
$activationEmail = (string) ($_SESSION['vevak_feedback_invite_email'] ?? '');
$activationPending = $activationEmail !== '' && !empty($_SESSION['vevak_feedback_invite']);
$answers = is_array($stored['answers'] ?? null) ? $stored['answers'] : [];
$submittedAt = (string) ($stored['submitted_at'] ?? '');

$questions = [
    ['Lancement', 'Premier lancement'],
    ['Permissions', 'Permissions Android'],
    ['Contact', 'Contact de confiance'],
    ['SMS', 'Demande par SMS'],
    ['Position', 'Position reçue'],
    ['Verrouillage', 'Écran verrouillé'],
    ['Redémarrage', 'Après redémarrage'],
    ['Révocation', 'Révocation'],
    ['Partage', 'Partage manuel'],
    ['Alertes', 'Notifications'],
    ['Confiance', 'Confiance générale'],
    ['Manques', 'Ce qui manque'],
];
$totalQuestions = count($questions);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#17332c">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <title>Retours testeurs — VeVak</title>
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../assets/styles.css?v=20260918-a11y-v2">
  <link rel="stylesheet" href="./retours.css?v=20260927">
  <script defer src="../assets/site.js?v=20260918-a11y-v2"></script>
  <?php if ($loggedEmail !== ''): ?><script defer src="./retours.js?v=20260927"></script><?php endif; ?>
</head>
<body class="feedback-page">
  <a class="skip-link" href="#main">Aller au contenu</a>

  <header class="site-header" data-site-header>
    <div class="wrap header-inner">
      <a class="brand" href="../" aria-label="VeVak, accueil">
        <span class="brand-mark" aria-hidden="true">V</span>
        <span class="brand-copy"><strong>VeVak</strong><small>espace de retours</small></span>
      </a>
      <div class="header-actions">
        <nav class="nav" id="vevak-navigation" data-site-nav data-open="false" aria-label="Navigation"><a href="../">Site VeVak</a><a href="../confidentialite/">Confidentialité</a></nav>
        <button class="a11y-toggle" type="button" data-vevak-accessibility-toggle aria-pressed="false">Version accessible</button>
        <button class="menu-toggle" type="button" data-site-menu-toggle aria-expanded="false" aria-controls="vevak-navigation" aria-label="Ouvrir le menu"><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span></button>
      </div>
    </div>
  </header>

  <main id="main">
  <?php if ($loggedEmail === '' || $tester === null): ?>
    <section class="feedback-auth-shell">
      <div class="feedback-auth-card">
        <p class="eyebrow"><span class="status-dot"></span> Espace privé · testeurs VeVak</p>
        <h1>Questionnaire de retour</h1>
        <p class="lead-small">Cet espace est réservé aux testeurs retenus. Une fois ton adresse autorisée, tu crées toi-même ton mot de passe. Tes réponses restent enregistrées pour pouvoir reprendre le questionnaire plus tard.</p>

        <?php if ($error !== ''): ?><p class="feedback-notice error" role="alert"><?= vf_e($error) ?></p><?php endif; ?>
        <?php if ($notice !== ''): ?><p class="feedback-notice success" role="status"><?= vf_e($notice) ?></p><?php endif; ?>

        <div class="feedback-auth-grid">
          <section class="feedback-auth-box">
            <h2>J’ai déjà un mot de passe</h2>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= vf_e(vf_csrf()) ?>">
              <input type="hidden" name="action" value="login">
              <label>E-mail utilisé pour le test
                <input type="email" name="email" autocomplete="username" required>
              </label>
              <label>Mot de passe
                <input type="password" name="password" autocomplete="current-password" maxlength="256" required>
              </label>
              <button class="button primary" type="submit">Se connecter</button>
            </form>
          </section>

          <section class="feedback-auth-box">
            <h2>Première connexion</h2>
            <?php if ($activationPending): ?>
              <p>Ton lien d’activation est reconnu. Confirme ton adresse puis choisis ton mot de passe.</p>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= vf_e(vf_csrf()) ?>">
                <input type="hidden" name="action" value="create_account">
                <label>E-mail utilisé pour le test
                  <input type="email" name="email" autocomplete="username" required value="<?= vf_e($activationEmail) ?>">
                </label>
                <label>Choisir un mot de passe
                  <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="256" required>
                </label>
                <label>Confirmer le mot de passe
                  <input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="256" required>
                </label>
                <button class="button secondary" type="submit">Créer mon mot de passe</button>
              </form>
            <?php else: ?>
              <p>Pour créer ton mot de passe, ouvre le lien d’activation individuel transmis après validation de ta participation.</p>
              <p><strong>Tu as été retenu mais tu n’as plus le lien ?</strong> Demande simplement qu’un nouveau lien soit généré.</p>
            <?php endif; ?>
          </section>
        </div>

        <p class="feedback-warning"><strong>Mot de passe oublié ?</strong> Demande une réinitialisation à <a href="mailto:contact@lepotager.org">contact@lepotager.org</a>. Aucun mot de passe n’est envoyé ou conservé en clair.</p>
      </div>
    </section>
  <?php else: ?>
    <section class="feedback-intro">
      <div class="feedback-shell feedback-intro-grid">
        <div>
          <p class="eyebrow"><span class="status-dot"></span> Test privé · questionnaire VeVak</p>
          <h1>Ce qui compte, c’est ce qui s’est vraiment passé.</h1>
          <p class="lead">Il n’y a pas de bonne réponse. Un blocage, une hésitation ou une fonction incomprise sont des retours utiles. Tu peux quitter cette page et revenir plus tard : les réponses déjà saisies sont conservées.</p>
          <ul class="feedback-meta">
            <li><?= $totalQuestions ?> questions courtes</li>
            <li>1 question par écran</li>
            <li>Commentaire libre à chaque question</li>
            <li>Sauvegarde automatique</li>
          </ul>
        </div>
        <aside class="feedback-profile">
          <strong><?= vf_e($loggedEmail) ?></strong>
          <small><?= vf_e((string) ($tester['device_model'] ?? 'Téléphone non renseigné')) ?></small>
          <small><?= vf_e((string) ($tester['android_version'] ?? 'Android non renseigné')) ?> · <?= vf_e((string) ($tester['sim_setup'] ?? 'SIM non renseignée')) ?></small>
          <?php if ($submittedAt !== ''): ?><small>Dernier envoi : <?= vf_e($submittedAt) ?></small><?php endif; ?>
          <div class="feedback-account-actions">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= vf_e(vf_csrf()) ?>">
              <input type="hidden" name="action" value="logout">
              <button class="button secondary" type="submit">Déconnexion</button>
            </form>
          </div>
        </aside>
      </div>
    </section>

    <section class="section compact">
      <div class="feedback-shell feedback-layout" data-feedback-app>
        <aside class="feedback-nav" aria-label="Progression du questionnaire">
          <div class="feedback-progress-head"><span>Progression</span><strong data-feedback-progress-text>1 / <?= $totalQuestions ?></strong></div>
          <div class="feedback-progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?= $totalQuestions ?>" aria-valuenow="1"><span data-feedback-progress></span></div>
          <ol>
            <?php foreach ($questions as $index => $question): ?>
              <li><button type="button" data-feedback-go="<?= $index ?>" <?= $index === 0 ? 'aria-current="step"' : '' ?>><?= ($index + 1) ?> · <?= vf_e($question[0]) ?></button></li>
            <?php endforeach; ?>
          </ol>
          <p class="feedback-save-state" data-feedback-save-state>Les réponses enregistrées sur le serveur sont liées uniquement à ton compte de testeur.</p>
        </aside>

        <div class="feedback-panel">
          <?php if ($error !== ''): ?><p class="feedback-notice error" role="alert"><?= vf_e($error) ?></p><?php endif; ?>
          <?php if ($notice !== ''): ?><p class="feedback-notice success" role="status"><?= vf_e($notice) ?></p><?php endif; ?>

          <form method="post" data-feedback-form novalidate>
            <input type="hidden" name="_csrf" value="<?= vf_e(vf_csrf()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="submitted" value="0">

            <?php
            vf_question(1, $totalQuestions, 'q1_launch', 'comment_q1_launch',
                'Le premier lancement t’a-t-il paru clair ?',
                'Pense aux premiers écrans, aux explications et au moment où tu as compris ce que VeVak allait faire.',
                ['very_clear' => 'Très clair', 'mostly_clear' => 'Plutôt clair', 'confusing' => 'Confus', 'blocked' => 'Je me suis retrouvé bloqué'],
                $answers);

            vf_question(2, $totalQuestions, 'q2_permissions', 'comment_q2_permissions',
                'Les permissions Android étaient-elles compréhensibles ?',
                'Il doit être possible de comprendre pourquoi VeVak demande un accès sans avoir à faire confiance aveuglément.',
                ['yes' => 'Oui', 'mostly' => 'Plutôt', 'no' => 'Non', 'not_sure' => 'Je ne sais pas'],
                $answers);

            vf_question(3, $totalQuestions, 'q3_contact', 'comment_q3_contact',
                'As-tu réussi à configurer un contact de confiance ?',
                'Pense au choix du contact, à la phrase prévue et à la durée d’autorisation.',
                ['easy' => 'Oui, facilement', 'hard' => 'Oui, avec difficulté', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(4, $totalQuestions, 'q4_sms', 'comment_q4_sms',
                'Une demande de position par SMS a-t-elle reçu la réponse attendue ?',
                'Fais le test avec un contact réellement autorisé et la phrase configurée.',
                ['yes' => 'Oui', 'slow' => 'Oui, mais lentement', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(5, $totalQuestions, 'q5_location', 'comment_q5_location',
                'La position reçue était-elle cohérente avec la situation réelle ?',
                'Une position approximative n’est pas forcément une erreur : indique surtout si le résultat était compréhensible.',
                ['yes' => 'Oui', 'approximate' => 'Approximative', 'wrong' => 'Non', 'none' => 'Aucune position reçue'],
                $answers);

            vf_question(6, $totalQuestions, 'q6_locked', 'comment_q6_locked',
                'Écran verrouillé et téléphone laissé tranquille : la demande a-t-elle fonctionné ?',
                'Laisse le téléphone verrouillé quelques minutes avant le test.',
                ['yes' => 'Oui', 'delayed' => 'Oui, avec délai', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(7, $totalQuestions, 'q7_reboot', 'comment_q7_reboot',
                'Après un redémarrage, VeVak s’est-il comporté comme prévu ?',
                'On cherche surtout les fonctions qui cessent silencieusement de marcher après un redémarrage.',
                ['yes' => 'Oui', 'partial' => 'Partiellement', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(8, $totalQuestions, 'q8_revoke', 'comment_q8_revoke',
                'Après révocation d’un contact, une nouvelle demande a-t-elle bien été bloquée ?',
                'Une position ne doit plus être envoyée à un contact dont l’autorisation a été retirée.',
                ['blocked' => 'Oui, accès coupé', 'weird' => 'Comportement étrange', 'failed' => 'Non, une position est partie', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(9, $totalQuestions, 'q9_manual', 'comment_q9_manual',
                'Le partage manuel d’une position était-il simple à comprendre ?',
                'Il doit rester évident qu’il s’agit d’un envoi volontaire et ponctuel.',
                ['yes' => 'Oui', 'mostly' => 'Plutôt', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(10, $totalQuestions, 'q10_notifications', 'comment_q10_notifications',
                'Notifications et vibrations correspondaient-elles à tes réglages ?',
                'Signale tout comportement trop visible, absent ou surprenant.',
                ['yes' => 'Oui', 'partial' => 'Partiellement', 'no' => 'Non', 'not_tested' => 'Non testé'],
                $answers);

            vf_question(11, $totalQuestions, 'q11_trust', 'comment_q11_trust',
                'Te sentirais-tu en confiance pour garder VeVak installé au quotidien ?',
                'Réponds selon ton ressenti après le test, pas selon l’idée du projet.',
                ['yes' => 'Oui', 'mostly_yes' => 'Plutôt oui', 'mostly_no' => 'Plutôt non', 'no' => 'Non'],
                $answers);

            vf_question(12, $totalQuestions, 'q12_missing', 'comment_q12_missing',
                'Qu’est-ce qui manque le plus aujourd’hui ?',
                'Choisis le point principal ; utilise le commentaire pour préciser ou proposer autre chose.',
                ['nothing' => 'Rien d’important', 'explanations' => 'Des explications', 'reliability' => 'Plus de fiabilité', 'settings' => 'Plus de réglages'],
                $answers);
            ?>

            <div class="feedback-warning">
              <strong>Ne mets pas de données sensibles dans les commentaires :</strong>
              pas de coordonnées GPS, numéro de téléphone, phrase-clé, SSID/BSSID ou identité d’un contact.
            </div>
          </form>

          <section class="feedback-done" data-feedback-done hidden>
            <p class="kicker">Merci</p>
            <h2>Ton retour est enregistré.</h2>
            <p>Tu peux revenir plus tard avec le même compte si tu veux corriger ou compléter une réponse.</p>
            <button class="button secondary" type="button" onclick="window.location.reload()">Revoir mes réponses</button>
          </section>
        </div>
      </div>
    </section>
  <?php endif; ?>
  </main>

  <footer class="site-footer">
    <div class="wrap footer-grid">
      <div><a class="brand footer-brand" href="../"><span class="brand-mark" aria-hidden="true">V</span><span>VeVak</span></a><p>Espace privé des testeurs.</p></div>
      <div class="footer-links"><a href="../confidentialite/testeurs-google-play.html">Confidentialité du panel</a><a href="mailto:contact@lepotager.org">Contact</a></div>
    </div>
  </footer>
</body>
</html>
