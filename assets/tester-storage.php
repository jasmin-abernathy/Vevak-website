<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const VEVAK_TESTER_CONSENT_VERSION = 'google-play-tester-v2-2026-09-27';
const VEVAK_TESTER_RATE_LIMIT = 8;
const VEVAK_TESTER_RATE_WINDOW = 3600;
const VEVAK_FEEDBACK_CODE_TTL = 600;

function vv_private_dir(): string
{
    $override = trim((string) getenv('VEVAK_TESTERS_STORAGE_DIR'));
    if ($override !== '') {
        $dir = $override;
    } else {
        $home = trim((string) getenv('HOME'));
        if ($home === '') {
            $home = trim((string) ($_SERVER['HOME'] ?? ''));
        }
        if ($home === '' && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $entry = @posix_getpwuid(posix_geteuid());
            if (is_array($entry) && !empty($entry['dir'])) {
                $home = (string) $entry['dir'];
            }
        }
        if ($home === '' || $home[0] !== '/') {
            throw new RuntimeException('Private storage home is unavailable.');
        }
        $dir = rtrim($home, '/') . '/.vevak-private';
    }

    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create private tester storage.');
    }
    @chmod($dir, 0700);

    $publicRoot = realpath(dirname(__DIR__));
    $privateRoot = realpath($dir);
    if ($publicRoot !== false && $privateRoot !== false) {
        $publicPrefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $privatePrefix = rtrim($privateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($privatePrefix, $publicPrefix)) {
            throw new RuntimeException('Refusing to store tester data below the public document root.');
        }
    }

    return $dir;
}

function vv_data_file(): string
{
    return vv_private_dir() . '/testers.json';
}

function vv_rate_file(): string
{
    return vv_private_dir() . '/tester-rate-limits.json';
}

function vv_feedback_accounts_file(): string
{
    return vv_private_dir() . '/tester-feedback-accounts.json';
}

function vv_feedback_answers_file(): string
{
    return vv_private_dir() . '/tester-feedback-answers.json';
}

function vv_feedback_rate_file(): string
{
    return vv_private_dir() . '/tester-feedback-rate-limits.json';
}

function vv_rate_secret(): string
{
    $path = vv_private_dir() . '/tester-rate-secret';
    $handle = @fopen($path, 'c+');
    if ($handle === false) { throw new RuntimeException('Unable to open the rate-limit secret.'); }
    @chmod($path, 0600);
    try {
        if (!flock($handle, LOCK_EX)) { throw new RuntimeException('Unable to lock the rate-limit secret.'); }
        $secret = trim((string) stream_get_contents($handle));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            if (fwrite($handle, $secret) !== strlen($secret)) { throw new RuntimeException('Unable to initialise the rate-limit secret.'); }
            fflush($handle);
        }
        if (strlen($secret) < 32) { throw new RuntimeException('Invalid rate-limit secret.'); }
    } finally { fclose($handle); }
    return $secret;
}

function vv_with_json_file(string $path, array $default, bool $exclusive, callable $callback): mixed
{
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open private tester storage.');
    }
    @chmod($path, 0600);

    try {
        if (!flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
            throw new RuntimeException('Unable to lock private tester storage.');
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = $raw === '' ? $default : json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            $data = $default;
        }

        $result = $callback($data);
        if ($exclusive) {
            rewind($handle);
            if (!ftruncate($handle, 0)) {
                throw new RuntimeException('Unable to update private tester storage.');
            }
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $encoded . "\n") === false) {
                throw new RuntimeException('Unable to persist tester storage.');
            }
            fflush($handle);
        }
        flock($handle, LOCK_UN);
        return $result;
    } finally {
        fclose($handle);
    }
}

function vv_normalize_email_key(string $email): string
{
    $email = trim($email);
    $at = strrpos($email, '@');
    if ($at === false) {
        return $email;
    }
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    return $local . '@' . strtolower($domain);
}

function vv_register_tester(
    string $email,
    string $deviceModel = '',
    string $androidVersion = '',
    string $simSetup = '',
    bool $feedbackConsent = false
): array {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $key = vv_normalize_email_key($email);
    $created = false;

    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use (
        $key,
        $email,
        $deviceModel,
        $androidVersion,
        $simSetup,
        $feedbackConsent,
        $now,
        &$created
    ): void {
        if (!isset($data['testers']) || !is_array($data['testers'])) {
            $data['testers'] = [];
        }

        $existing = isset($data['testers'][$key]) && is_array($data['testers'][$key])
            ? $data['testers'][$key]
            : [];

        $data['testers'][$key] = [
            'email' => $email,
            'device_model' => $deviceModel,
            'android_version' => $androidVersion,
            'sim_setup' => $simSetup,
            'feedback_consent' => $feedbackConsent,
            'feedback_consent_at' => $feedbackConsent
                ? ($existing['feedback_consent_at'] ?? $now)
                : ($existing['feedback_consent_at'] ?? ''),
            'feedback_enabled' => (bool) ($existing['feedback_enabled'] ?? false),
            'feedback_enabled_at' => $existing['feedback_enabled_at'] ?? '',
            'feedback_code' => $existing['feedback_code'] ?? [],
            'created_at' => $existing['created_at'] ?? $now,
            'last_requested_at' => $now,
            'consent_version' => VEVAK_TESTER_CONSENT_VERSION,
        ];

        $created = $existing === [];
    });

    return ['created' => $created, 'timestamp' => $now];
}

/** Warn the site owner about a new signup without emailing tester personal data. */
function vv_notify_new_tester(?callable $sender = null): bool
{
    // Set to 1 only for CLI/CI tests that must never send a real message.
    if (getenv('VEVAK_TESTERS_NOTIFY_DISABLED') === '1') {
        return true;
    }

    $configured = getenv('VEVAK_TESTERS_NOTIFY_TO');
    $recipient = trim($configured === false ? 'contact@lepotager.org' : $configured);
    if ($recipient === '' || strlen($recipient) > 254
        || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
        || preg_match('/[\r\n]/', $recipient)) {
        error_log('VeVak tester signup notification: invalid recipient configuration.');
        return false;
    }

    $subject = 'VeVak - nouvelle inscription aux tests';
    $body = "Une nouvelle candidature au test Google Play de VeVak a ete enregistree.\n\n"
        . "Pour consulter les demandes et exporter les adresses :\n"
        . "https://vevak.lepotager.org/test/admin-testers.php\n\n"
        . "Cet e-mail ne contient aucune donnee personnelle de testeur.\n"
        . "VeVak - Le Potager du Web\n";

    // Injectable mailer is limited to CLI tests; public requests always use sendmail.
    if ($sender !== null) {
        return PHP_SAPI === 'cli' && (bool) $sender($recipient, $subject, $body);
    }

    return @mail($recipient, $subject, $body, [
        'From' => 'VeVak <contact@lepotager.org>',
        'Reply-To' => 'contact@lepotager.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ], '-fcontact@lepotager.org');
}

function vv_get_tester(string $email): ?array
{
    $key = vv_normalize_email_key($email);
    return vv_with_json_file(vv_data_file(), ['testers' => []], false, function ($data) use ($key): ?array {
        $row = $data['testers'][$key] ?? null;
        if (!is_array($row) || empty($row['email'])) {
            return null;
        }
        $row['key'] = $key;
        return $row;
    });
}

function vv_set_feedback_enabled(string $key, bool $enabled): bool
{
    $updated = false;
    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $enabled, &$updated): void {
        if (!isset($data['testers'][$key]) || !is_array($data['testers'][$key])) {
            return;
        }
        $data['testers'][$key]['feedback_enabled'] = $enabled;
        $data['testers'][$key]['feedback_enabled_at'] = $enabled ? gmdate('Y-m-d\TH:i:s\Z') : '';
        if (!$enabled) {
            unset($data['testers'][$key]['feedback_code']);
        }
        $updated = true;
    });
    return $updated;
}

function vv_get_testers(): array
{
    return vv_with_json_file(vv_data_file(), ['testers' => []], false, function ($data): array {
        $rows = [];
        foreach (($data['testers'] ?? []) as $key => $row) {
            if (!is_array($row) || empty($row['email'])) {
                continue;
            }
            $row['key'] = (string) $key;
            $rows[] = $row;
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $rows;
    });
}

function vv_delete_tester(string $key): bool
{
    $deleted = false;
    $email = '';

    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, &$deleted, &$email): void {
        if (isset($data['testers'][$key])) {
            $row = $data['testers'][$key];
            $email = is_array($row) ? (string) ($row['email'] ?? '') : '';
            unset($data['testers'][$key]);
            $deleted = true;
        }
    });

    if ($deleted && $email !== '') {
        vv_delete_feedback_data($email);
    }

    return $deleted;
}

/** Limits are independent per IP and address, including unknown addresses. */
function vv_feedback_code_rate_limit(string $ip, string $email, string $action): bool
{
    $secret = vv_rate_secret();
    $keys = [
        hash_hmac('sha256', $action . '|ip|' . $ip, $secret) => $action === 'send' ? 20 : 40,
        hash_hmac('sha256', $action . '|email|' . vv_normalize_email_key($email), $secret) => $action === 'send' ? 5 : 10,
    ];
    return vv_with_json_file(vv_feedback_rate_file(), ['clients' => []], true, function (&$data) use ($keys): bool {
        $now = time();
        foreach (($data['clients'] ?? []) as $key => $entry) {
            if ((int) ($entry['window_start'] ?? 0) <= $now - 900) {
                unset($data['clients'][$key]);
            }
        }
        $allowed = true;
        foreach ($keys as $key => $limit) {
            $entry = $data['clients'][$key] ?? ['window_start' => $now, 'hits' => 0];
            $entry['hits']++;
            if ($entry['hits'] > $limit) { $allowed = false; }
            $data['clients'][$key] = $entry;
        }
        return $allowed;
    });
}

function vv_feedback_mail_code(string $email, string $code): bool
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $email)) {
        return false;
    }
    $body = "Bonjour,\n\nTon code de connexion VeVak est : " . $code
        . "\n\nIl est valable 10 minutes et ne peut être utilisé qu’une fois."
        . "\nSaisis-le sur https://vevak.lepotager.org/retours/"
        . "\n\nNe communique ce code à personne. Si tu n’as pas demandé ce code, ignore ce message.\n\nVeVak — Le Potager du Web\n";
    return mail($email, 'VeVak - ton code de connexion', $body, [
        'From' => 'VeVak <contact@lepotager.org>',
        'Reply-To' => 'contact@lepotager.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ], '-fcontact@lepotager.org');
}

/** No code or membership status is returned to the browser. Sender injection is for CLI tests only. */
function vv_feedback_request_code(string $email, string $ip, ?callable $sender = null): void
{
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Vérifie ton adresse e-mail.');
    }
    if (!vv_feedback_code_rate_limit($ip, $email, 'send')) {
        throw new RuntimeException('Trop de demandes. Attends 15 minutes avant de réessayer.');
    }
    $key = vv_normalize_email_key($email);
    $secret = vv_rate_secret();
    $send = $sender ?? 'vv_feedback_mail_code';
    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $secret, $send): void {
        $row = $data['testers'][$key] ?? null;
        if (!is_array($row) || empty($row['feedback_consent']) || empty($row['feedback_enabled'])) { return; }
        $now = time();
        if ((int) ($row['feedback_code']['sent_at'] ?? 0) > $now - 60) { return; }
        $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        // Keep the previous code if the mail server refuses this message.
        try { $sent = $send((string) $row['email'], $code); }
        catch (Throwable $error) { $sent = false; }
        if (!$sent) {
            error_log('VeVak: login code mail transport failed.');
            return;
        }
        $data['testers'][$key]['feedback_code'] = [
            'hash' => hash_hmac('sha256', $key . '|' . $code, $secret),
            'expires_at' => $now + VEVAK_FEEDBACK_CODE_TTL,
            'sent_at' => $now,
            'attempts' => 0,
        ];
    });
}

function vv_feedback_verify_code(string $email, string $code, string $ip): bool
{
    if (!vv_feedback_code_rate_limit($ip, $email, 'verify')) { return false; }
    $key = vv_normalize_email_key($email);
    $secret = vv_rate_secret();
    return vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $code, $secret): bool {
        $row = $data['testers'][$key] ?? null;
        if (!is_array($row) || empty($row['feedback_consent']) || empty($row['feedback_enabled'])) { return false; }
        $challenge = $row['feedback_code'] ?? [];
        if (empty($challenge['hash']) || (int) ($challenge['expires_at'] ?? 0) <= time()
            || (int) ($challenge['attempts'] ?? 0) >= 5) {
            unset($data['testers'][$key]['feedback_code']);
            return false;
        }
        $data['testers'][$key]['feedback_code']['attempts']++;
        if (!preg_match('/^[0-9]{8}$/D', $code)
            || !hash_equals($challenge['hash'], hash_hmac('sha256', $key . '|' . $code, $secret))) {
            return false;
        }
        // Verification and consumption share the same exclusive lock (no replay race).
        unset($data['testers'][$key]['feedback_code']);
        return true;
    });
}

function vv_feedback_get_answers(string $email): array
{
    $key = vv_normalize_email_key($email);
    return vv_with_json_file(vv_feedback_answers_file(), ['responses' => []], false, function ($data) use ($key): array {
        $row = $data['responses'][$key] ?? [];
        return is_array($row) ? $row : [];
    });
}

function vv_get_feedback_responses(): array
{
    return vv_with_json_file(vv_feedback_answers_file(), ['responses' => []], false, function ($data): array {
        $rows = [];
        foreach (($data['responses'] ?? []) as $emailKey => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['email'] = (string) $emailKey;
            $rows[] = $row;
        }
        usort(
            $rows,
            static fn(array $a, array $b): int => strcmp(
                (string) ($b['updated_at'] ?? ''),
                (string) ($a['updated_at'] ?? '')
            )
        );
        return $rows;
    });
}

function vv_feedback_save_answers(string $email, array $answers, bool $submitted = false): void
{
    $key = vv_normalize_email_key($email);
    $now = gmdate('Y-m-d\TH:i:s\Z');

    vv_with_json_file(vv_feedback_answers_file(), ['responses' => []], true, function (&$data) use ($key, $answers, $submitted, $now): void {
        if (!isset($data['responses']) || !is_array($data['responses'])) {
            $data['responses'] = [];
        }

        $existing = isset($data['responses'][$key]) && is_array($data['responses'][$key])
            ? $data['responses'][$key]
            : [];

        $data['responses'][$key] = [
            'answers' => $answers,
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
            'submitted_at' => $submitted ? $now : ($existing['submitted_at'] ?? ''),
        ];
    });
}

function vv_delete_feedback_data(string $email): void
{
    $key = vv_normalize_email_key($email);

    vv_with_json_file(vv_feedback_accounts_file(), ['accounts' => []], true, function (&$data) use ($key): void {
        if (isset($data['accounts'][$key])) {
            unset($data['accounts'][$key]);
        }
    });

    vv_with_json_file(vv_feedback_answers_file(), ['responses' => []], true, function (&$data) use ($key): void {
        if (isset($data['responses'][$key])) {
            unset($data['responses'][$key]);
        }
    });
}

function vv_rate_limit(string $ip): bool
{
    if ($ip === '') {
        return true;
    }
    $fingerprint = hash_hmac('sha256', $ip, vv_rate_secret());
    $now = time();
    $allowed = true;

    vv_with_json_file(vv_rate_file(), ['clients' => []], true, function (&$data) use ($fingerprint, $now, &$allowed): void {
        if (!isset($data['clients']) || !is_array($data['clients'])) {
            $data['clients'] = [];
        }
        foreach ($data['clients'] as $hash => $entry) {
            $start = (int) ($entry['window_start'] ?? 0);
            if ($start < $now - (VEVAK_TESTER_RATE_WINDOW * 2)) {
                unset($data['clients'][$hash]);
            }
        }

        $entry = $data['clients'][$fingerprint] ?? ['window_start' => $now, 'hits' => 0];
        if ((int) $entry['window_start'] <= $now - VEVAK_TESTER_RATE_WINDOW) {
            $entry = ['window_start' => $now, 'hits' => 0];
        }
        $entry['hits'] = (int) $entry['hits'] + 1;
        $allowed = $entry['hits'] <= VEVAK_TESTER_RATE_LIMIT;
        $data['clients'][$fingerprint] = $entry;
    });

    return $allowed;
}

function vv_spreadsheet_safe_email(string $email): string
{
    return preg_match('/^[=+\-@]/', $email) === 1 ? "'" . $email : $email;
}
