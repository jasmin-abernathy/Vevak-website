<?php
declare(strict_types=1);

/**
 * VeVak administrator authentication.
 * One fixed, server-configured mailbox; password + email one-time code.
 * Secrets and challenges live outside the DocumentRoot.
 */
require_once dirname(__DIR__) . '/assets/tester-storage.php';

const VV_ADMIN_CODE_TTL = 600;
const VV_ADMIN_SESSION_IDLE = 1800;
const VV_ADMIN_SESSION_MAX = 43200;

function vv_admin_email(): string
{
    $configured = getenv('VEVAK_ADMIN_EMAIL');
    $email = strtolower(trim($configured === false ? 'contact@lepotager.org' : $configured));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $email)) {
        throw new RuntimeException('Adresse administrateur du serveur invalide.');
    }
    return $email;
}

function vv_admin_data_file(): string
{
    return vv_private_dir() . '/admin-auth.json';
}

function vv_admin_rate_file(): string
{
    return vv_private_dir() . '/admin-auth-rates.json';
}

function vv_admin_headers(): void
{
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function vv_admin_session_start(): void
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!$https && !in_array($ip, ['127.0.0.1', '::1'], true) && PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Connexion HTTPS obligatoire.');
    }
    vv_admin_headers();
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('vevak_admin_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/test/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
    if (empty($_SESSION['vv_admin_csrf'])) {
        $_SESSION['vv_admin_csrf'] = bin2hex(random_bytes(24));
    }
}

function vv_admin_csrf(): string
{
    return (string) ($_SESSION['vv_admin_csrf'] ?? '');
}

function vv_admin_validate_csrf(string $token): bool
{
    return $token !== '' && hash_equals(vv_admin_csrf(), $token);
}

function vv_admin_account(): array
{
    return vv_with_json_file(vv_admin_data_file(), [], false, static fn(array $data): array => $data);
}

function vv_admin_is_initialized(): bool
{
    return !empty(vv_admin_account()['password_hash']);
}

function vv_admin_is_authenticated(): bool
{
    if (empty($_SESSION['vv_admin_authenticated_at']) || empty($_SESSION['vv_admin_version'])) {
        return false;
    }
    $now = time();
    if ($now - (int) ($_SESSION['vv_admin_seen_at'] ?? 0) > VV_ADMIN_SESSION_IDLE
        || $now - (int) $_SESSION['vv_admin_authenticated_at'] > VV_ADMIN_SESSION_MAX) {
        unset($_SESSION['vv_admin_authenticated_at'], $_SESSION['vv_admin_version'], $_SESSION['vv_admin_seen_at']);
        return false;
    }
    $account = vv_admin_account();
    $version = (string) ($account['version'] ?? '');
    if ($version === '' || !hash_equals($version, (string) $_SESSION['vv_admin_version'])) {
        unset($_SESSION['vv_admin_authenticated_at'], $_SESSION['vv_admin_version'], $_SESSION['vv_admin_seen_at']);
        return false;
    }
    $_SESSION['vv_admin_seen_at'] = $now;
    return true;
}

function vv_admin_require(): void
{
    if (!vv_admin_is_authenticated()) {
        header('Location: /test/login.php', true, 303);
        exit;
    }
}

function vv_admin_logged_in(): void
{
    $version = (string) (vv_admin_account()['version'] ?? '');
    if ($version === '') {
        throw new RuntimeException('Compte administrateur indisponible.');
    }
    session_regenerate_id(true);
    $_SESSION['vv_admin_authenticated_at'] = time();
    $_SESSION['vv_admin_seen_at'] = time();
    $_SESSION['vv_admin_version'] = $version;
    $_SESSION['vv_admin_csrf'] = bin2hex(random_bytes(24));
    unset($_SESSION['vv_admin_flow'], $_SESSION['vv_admin_nonce']);
}

function vv_admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $params['path'],
            'secure' => $params['secure'], 'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function vv_admin_password_valid(string $password): bool
{
    $length = strlen($password);
    return $length >= 12 && $length <= 72 && !str_contains($password, "\0");
}

function vv_admin_check_password(string $password): bool
{
    $account = vv_admin_account();
    $hash = (string) ($account['password_hash'] ?? '');
    // Equalize absent-account failure without publishing an account existence oracle.
    if ($hash === '') {
        password_verify($password, '$2y$10$B2jVfwb.zHRRVnVTm.RVBe65boeHc3oTbAnMsqTpWgMoNdtyDoLvy');
        return false;
    }
    return password_verify($password, $hash);
}

/** Hash IPs, cap attempts in rolling fifteen-minute windows; no raw IP stored. */
function vv_admin_rate_limit(string $action, string $ip, int $maximum = 8): bool
{
    $key = hash_hmac('sha256', $action . '|' . $ip, vv_rate_secret());
    return vv_with_json_file(vv_admin_rate_file(), ['attempts' => []], true, static function (&$data) use ($key, $maximum): bool {
        $now = time();
        foreach (($data['attempts'] ?? []) as $k => $entry) {
            if ((int) ($entry['since'] ?? 0) <= $now - 900) {
                unset($data['attempts'][$k]);
            }
        }
        $entry = $data['attempts'][$key] ?? ['since' => $now, 'count' => 0];
        $entry['count']++;
        $data['attempts'][$key] = $entry;
        return $entry['count'] <= $maximum;
    });
}

function vv_admin_send_mail(string $code, string $purpose, ?callable $sender = null): bool
{
    $labels = ['setup' => 'activation', 'login' => 'connexion', 'reset' => 'réinitialisation'];
    if (!isset($labels[$purpose])) {
        return false;
    }
    $body = "Ton code de " . $labels[$purpose] . " VeVak : " . $code
        . "\n\nIl expire dans 10 minutes et ne peut etre utilise qu'une fois."
        . "\nSi tu n'as rien demande, ignore ce message."
        . "\n\nhttps://vevak.lepotager.org/test/\n\nVeVak - Le Potager du Web\n";
    $to = vv_admin_email();
    if ($sender !== null) {
        return PHP_SAPI === 'cli' && (bool) $sender($to, 'VeVak - code administrateur', $body);
    }
    return @mail($to, 'VeVak - code administrateur', $body, [
        'From' => 'VeVak <contact@lepotager.org>',
        'Reply-To' => 'contact@lepotager.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ], '-fcontact@lepotager.org');
}

/** Challenge belongs to the same session as the password verification. */
function vv_admin_request_code(string $purpose, string $nonce, string $ip, ?callable $sender = null): bool
{
    if (!in_array($purpose, ['setup', 'login', 'reset'], true) || !preg_match('/^[a-f0-9]{48}$/', $nonce)) {
        return false;
    }
    if (!vv_admin_rate_limit('send-ip', $ip, 5)
        || !vv_admin_rate_limit('send-global', 'all', 20)) {
        throw new RuntimeException('Trop de demandes. Réessaie dans quinze minutes.');
    }
    $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $hashed = hash_hmac('sha256', $purpose . '|' . $nonce . '|' . $code, vv_rate_secret());
    return vv_with_json_file(vv_admin_data_file(), ['challenges' => []], true, static function (&$data) use ($purpose, $nonce, $code, $hashed, $sender): bool {
        $initialized = !empty($data['password_hash']);
        if (($purpose === 'setup') !== !$initialized) {
            return false;
        }
        $now = time();
        foreach (($data['challenges'] ?? []) as $id => $entry) {
            if (($entry['expires'] ?? 0) < $now) {
                unset($data['challenges'][$id]);
            }
        }
        if (count($data['challenges'] ?? []) >= 30) {
            throw new RuntimeException('Trop de codes en attente. Réessaie plus tard.');
        }
        $existing = $data['challenges'][$nonce] ?? null;
        if (is_array($existing) && $now - (int) ($existing['sent_at'] ?? 0) < 60) {
            throw new RuntimeException('Attends une minute avant de demander un nouveau code.');
        }
        if (!vv_admin_send_mail($code, $purpose, $sender)) {
            error_log('VeVak admin auth: mail transport failed.');
            return false;
        }
        $data['challenges'][$nonce] = [
            'hash' => $hashed, 'purpose' => $purpose,
            'expires' => $now + VV_ADMIN_CODE_TTL, 'sent_at' => $now, 'attempts' => 0,
        ];
        return true;
    });
}

/** On setup/reset the email code also authorizes choosing a new password. */
function vv_admin_verify_code(string $purpose, string $nonce, string $code, ?string $newPassword = null): bool
{
    if (!preg_match('/^\d{8}$/D', $code) || !preg_match('/^[a-f0-9]{48}$/', $nonce)) {
        return false;
    }
    if (($purpose === 'setup' || $purpose === 'reset') && ($newPassword === null || !vv_admin_password_valid($newPassword))) {
        return false;
    }
    $expected = hash_hmac('sha256', $purpose . '|' . $nonce . '|' . $code, vv_rate_secret());
    return vv_with_json_file(vv_admin_data_file(), [], true, static function (&$data) use ($purpose, $nonce, $expected, $newPassword): bool {
        $entry = $data['challenges'][$nonce] ?? null;
        if (!is_array($entry) || (string) ($entry['purpose'] ?? '') !== $purpose) {
            return false;
        }
        if ((int) ($entry['expires'] ?? 0) < time() || (int) ($entry['attempts'] ?? 0) >= 5) {
            unset($data['challenges'][$nonce]);
            return false;
        }
        $data['challenges'][$nonce]['attempts']++;
        if (!hash_equals((string) $entry['hash'], $expected)) {
            return false;
        }
        if ($purpose === 'setup' && !empty($data['password_hash'])) {
            unset($data['challenges'][$nonce]);
            return false;
        }
        if ($purpose === 'reset' && empty($data['password_hash'])) {
            unset($data['challenges'][$nonce]);
            return false;
        }
        if ($newPassword !== null) {
            $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $data['version'] = bin2hex(random_bytes(16));
        }
        // Revoke every outstanding challenge after any successful use.
        $data['challenges'] = [];
        return true;
    });
}

function vv_admin_change_password(string $currentPassword, string $newPassword): bool
{
    if (!vv_admin_password_valid($newPassword)) {
        return false;
    }
    return vv_with_json_file(vv_admin_data_file(), [], true, static function (&$data) use ($currentPassword, $newPassword): bool {
        $hash = (string) ($data['password_hash'] ?? '');
        if ($hash === '' || !password_verify($currentPassword, $hash)
            || password_verify($newPassword, $hash)) {
            return false;
        }
        $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $data['version'] = bin2hex(random_bytes(16));
        $data['challenges'] = [];
        return true;
    });
}
