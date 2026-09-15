<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const VEVAK_TESTER_CONSENT_VERSION = 'google-play-tester-v1-2026-09-15';
const VEVAK_TESTER_RATE_LIMIT = 8;
const VEVAK_TESTER_RATE_WINDOW = 3600;

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

function vv_register_tester(string $email): array
{
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $key = vv_normalize_email_key($email);
    $created = false;

    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, $email, $now, &$created): void {
        if (!isset($data['testers']) || !is_array($data['testers'])) {
            $data['testers'] = [];
        }
        if (isset($data['testers'][$key]) && is_array($data['testers'][$key])) {
            $data['testers'][$key]['last_requested_at'] = $now;
            $data['testers'][$key]['consent_version'] = VEVAK_TESTER_CONSENT_VERSION;
            return;
        }
        $data['testers'][$key] = [
            'email' => $email,
            'created_at' => $now,
            'last_requested_at' => $now,
            'consent_version' => VEVAK_TESTER_CONSENT_VERSION,
        ];
        $created = true;
    });

    return ['created' => $created, 'timestamp' => $now];
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
    vv_with_json_file(vv_data_file(), ['testers' => []], true, function (&$data) use ($key, &$deleted): void {
        if (isset($data['testers'][$key])) {
            unset($data['testers'][$key]);
            $deleted = true;
        }
    });
    return $deleted;
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
