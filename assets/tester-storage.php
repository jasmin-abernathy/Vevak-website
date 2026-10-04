<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const VEVAK_TESTER_CONSENT_VERSION = 'google-play-tester-v2-2026-09-27';
const VEVAK_TESTER_RATE_LIMIT = 8;
const VEVAK_TESTER_RATE_WINDOW = 3600;
const VEVAK_FEEDBACK_INVITE_TTL = 14 * 24 * 3600;

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
    if (!is_file($path)) {
        $secret = bin2hex(random_bytes(32));
        if (@file_put_contents($path, $secret, LOCK_EX) === false) {
            throw new RuntimeException('Unable to initialise the rate-limit secret.');
        }
        @chmod($path, 0600);
    }
    $secret = trim((string) @file_get_contents($path));
    if (strlen($secret) < 32) {
        throw new RuntimeException('Invalid rate-limit secret.');
    }
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
            'feedback_consent_at' => $feedbackConsent ? $now : ($existing['feedback_consent_at'] ?? ''),
            'feedback_enabled' => (bool) ($existing['feedback_enabled'] ?? false),
            'created_at' => $existing['created_at'] ?? $now,
            'last_requested_at' => $now,
            'consent_version' => VEVAK_TESTER_CONSENT_VERSION,
        ];

        $created = $existing === [];
    });

    return ['created' => $created, 'timestamp' => $now];
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
            $data['testers'][$key]['feedback_invite_hash'] = '';
            $data['testers'][$key]['feedback_invite_created_at'] = 0;
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

function vv_feedback_issue_invite(string $key): ?string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $createdAt = time();
    $issued = false;

    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $hash, $createdAt, &$issued): void {
        $row = $data['testers'][$key] ?? null;
        if (
            !is_array($row)
            || empty($row['feedback_consent'])
            || empty($row['feedback_enabled'])
        ) {
            return;
        }

        $data['testers'][$key]['feedback_invite_hash'] = $hash;
        $data['testers'][$key]['feedback_invite_created_at'] = $createdAt;
        $issued = true;
    });

    return $issued ? $token : null;
}

function vv_feedback_invite_email(string $token): ?string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $candidate = hash('sha256', $token);
    $now = time();

    return vv_with_json_file(vv_data_file(), ['testers' => []], false, function ($data) use ($candidate, $now): ?string {
        foreach (($data['testers'] ?? []) as $row) {
            if (!is_array($row) || empty($row['email']) || empty($row['feedback_invite_hash'])) {
                continue;
            }
            $createdAt = (int) ($row['feedback_invite_created_at'] ?? 0);
            if ($createdAt <= 0 || ($createdAt + VEVAK_FEEDBACK_INVITE_TTL) < $now) {
                continue;
            }
            if (hash_equals((string) $row['feedback_invite_hash'], $candidate)) {
                return (string) $row['email'];
            }
        }
        return null;
    });
}

function vv_feedback_consume_invite(string $email, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $key = vv_normalize_email_key($email);
    $candidate = hash('sha256', $token);
    $consumed = false;
    $now = time();

    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $candidate, $now, &$consumed): void {
        $row = $data['testers'][$key] ?? null;
        if (
            !is_array($row)
            || empty($row['feedback_consent'])
            || empty($row['feedback_enabled'])
            || empty($row['feedback_invite_hash'])
        ) {
            return;
        }

        $createdAt = (int) ($row['feedback_invite_created_at'] ?? 0);
        if ($createdAt <= 0 || ($createdAt + VEVAK_FEEDBACK_INVITE_TTL) < $now) {
            return;
        }
        if (!hash_equals((string) $row['feedback_invite_hash'], $candidate)) {
            return;
        }

        $data['testers'][$key]['feedback_invite_hash'] = '';
        $data['testers'][$key]['feedback_invite_created_at'] = 0;
        $consumed = true;
    });

    return $consumed;
}

function vv_feedback_clear_invite(string $key): void
{
    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key): void {
        if (!isset($data['testers'][$key]) || !is_array($data['testers'][$key])) {
            return;
        }
        $data['testers'][$key]['feedback_invite_hash'] = '';
        $data['testers'][$key]['feedback_invite_created_at'] = 0;
    });
}

function vv_feedback_account_exists(string $email): bool
{
    $key = vv_normalize_email_key($email);
    return vv_with_json_file(vv_feedback_accounts_file(), ['accounts' => []], false, function ($data) use ($key): bool {
        $row = $data['accounts'][$key] ?? null;
        return is_array($row) && !empty($row['password_hash']);
    });
}

function vv_feedback_create_account(string $email, string $password, string $inviteToken): array
{
    $tester = vv_get_tester($email);
    if (
        $tester === null
        || empty($tester['feedback_consent'])
        || empty($tester['feedback_enabled'])
    ) {
        return ['ok' => false, 'reason' => 'not_allowed'];
    }

    if (strlen($password) < 12) {
        return ['ok' => false, 'reason' => 'weak'];
    }
    if (strlen($password) > 256) {
        return ['ok' => false, 'reason' => 'too_long'];
    }

    $expectedEmail = vv_feedback_invite_email($inviteToken);
    if ($expectedEmail === null || !hash_equals(vv_normalize_email_key($expectedEmail), vv_normalize_email_key($email))) {
        return ['ok' => false, 'reason' => 'invite'];
    }

    $key = vv_normalize_email_key($email);
    $created = false;

    vv_with_json_file(vv_feedback_accounts_file(), ['accounts' => []], true, function (&$data) use ($key, $password, &$created): void {
        if (!isset($data['accounts']) || !is_array($data['accounts'])) {
            $data['accounts'] = [];
        }
        if (isset($data['accounts'][$key]) && is_array($data['accounts'][$key]) && !empty($data['accounts'][$key]['password_hash'])) {
            return;
        }

        $data['accounts'][$key] = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $created = true;
    });

    if (!$created) {
        return ['ok' => false, 'reason' => 'exists'];
    }

    if (!vv_feedback_consume_invite($email, $inviteToken)) {
        vv_feedback_reset_password($email);
        return ['ok' => false, 'reason' => 'invite'];
    }

    return ['ok' => true, 'reason' => null];
}

function vv_feedback_verify_password(string $email, string $password): bool
{
    $tester = vv_get_tester($email);
    if (
        $tester === null
        || empty($tester['feedback_consent'])
        || empty($tester['feedback_enabled'])
    ) {
        return false;
    }

    $key = vv_normalize_email_key($email);
    return vv_with_json_file(vv_feedback_accounts_file(), ['accounts' => []], false, function ($data) use ($key, $password): bool {
        $row = $data['accounts'][$key] ?? null;
        if (!is_array($row) || empty($row['password_hash'])) {
            return false;
        }
        return password_verify($password, (string) $row['password_hash']);
    });
}

function vv_feedback_reset_password(string $email): bool
{
    $key = vv_normalize_email_key($email);
    $deleted = false;
    vv_with_json_file(vv_feedback_accounts_file(), ['accounts' => []], true, function (&$data) use ($key, &$deleted): void {
        if (isset($data['accounts'][$key])) {
            unset($data['accounts'][$key]);
            $deleted = true;
        }
    });
    return $deleted;
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

function vv_feedback_rate_limit(string $ip, string $email): bool
{
    $identity = strtolower(trim($ip)) . '|' . vv_normalize_email_key($email);
    $fingerprint = hash_hmac('sha256', $identity, vv_rate_secret());
    $now = time();
    $allowed = true;

    vv_with_json_file(vv_feedback_rate_file(), ['clients' => []], true, function (&$data) use ($fingerprint, $now, &$allowed): void {
        if (!isset($data['clients']) || !is_array($data['clients'])) {
            $data['clients'] = [];
        }
        foreach ($data['clients'] as $hash => $entry) {
            $start = (int) ($entry['window_start'] ?? 0);
            if ($start < $now - 1800) {
                unset($data['clients'][$hash]);
            }
        }

        $entry = $data['clients'][$fingerprint] ?? ['window_start' => $now, 'hits' => 0];
        if ((int) $entry['window_start'] <= $now - 900) {
            $entry = ['window_start' => $now, 'hits' => 0];
        }
        $entry['hits'] = (int) $entry['hits'] + 1;
        $allowed = $entry['hits'] <= 10;
        $data['clients'][$fingerprint] = $entry;
    });

    return $allowed;
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
