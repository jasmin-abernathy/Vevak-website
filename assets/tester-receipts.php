<?php
declare(strict_types=1);

/**
 * Manual acknowledgement of VeVak tester applications.
 * This is a library only; only the authenticated administrator may invoke it.
 * Never send automatically from the public registration form.
 */
require_once __DIR__ . '/tester-storage.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

const VEVAK_RECEIPT_BATCH_MAX = 20;
const VEVAK_RECEIPT_VERSION = 'tester-received-2026-10';

function vv_receipt_subject(): string
{
    return 'VeVak - confirmation de ton inscription aux tests';
}

function vv_receipt_body(): string
{
    return "Bonjour,\n\n"
        . "Merci de t'être inscrit·e pour participer aux tests de VeVak ! 😊\n\n"
        . "Nous avons bien reçu et enregistré ta candidature.\n"
        . "Tu recevras prochainement à cette adresse les instructions pour accéder au test, "
        . "télécharger l'application et l'essayer sur ton téléphone Android, "
        . "dès que ton accès sera prêt.\n\n"
        . "Pour l'instant, tu n'as rien à installer : cette confirmation ne donne "
        . "pas encore accès au programme de test fermé Google Play.\n\n"
        . "Si tu souhaites finalement ne plus participer, réponds simplement "
        . "à ce message pour demander la suppression de ton inscription.\n\n"
        . "Merci encore pour ton aide et ton intérêt pour un outil libre et respectueux "
        . "de la vie privée.\n\n"
        . "L'équipe VeVak — Le Potager du Web\n"
        . "https://vevak.lepotager.org/\n";
}

/**
 * A stub sender may only be injected in CLI tests; a web request always uses
 * the server's own transport. Never put multiple recipients into To/Cc/Bcc.
 */
function vv_receipt_mail(string $email, ?callable $sender = null): bool
{
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || preg_match('/[\r\n]/', $email)) {
        return false;
    }
    $subject = vv_receipt_subject();
    $body = vv_receipt_body();
    if ($sender !== null) {
        return PHP_SAPI === 'cli' && (bool) $sender($email, $subject, $body);
    }
    return @mail($email, $subject, $body, [
        'From' => 'VeVak <contact@lepotager.org>',
        'Reply-To' => 'contact@lepotager.org',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ], '-fcontact@lepotager.org');
}

function vv_receipt_is_pending(array $row): bool
{
    return !empty($row['email'])
        && filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL) !== false
        && !empty($row['consent_version'])
        && empty($row['receipt_sent_at']);
}

function vv_receipt_pending_count(): int
{
    return count(array_filter(vv_get_testers(), 'vv_receipt_is_pending'));
}

/**
 * Send up to 20 individually addressed acknowledgements.
 *
 * $scope = 'pending': earliest unacknowledged registrations; ignores $keys.
 * $scope = 'selected': only exact keys from the private server-side list.
 *
 * Each message is handled under the registration-file lock and persisted before
 * the next message. A duplicate browser POST will skip already-accepted sends.
 * "sent" here means accepted by PHP/sendmail, NOT delivered to the inbox.
 */
function vv_receipt_send_batch(string $scope, array $keys = [], ?callable $sender = null): array
{
    if (!in_array($scope, ['pending', 'selected'], true)) {
        throw new InvalidArgumentException('Sélection de destinataires invalide.');
    }
    if ($scope === 'selected' && count($keys) > 500) {
        throw new InvalidArgumentException('Trop de destinataires sélectionnés.');
    }

    $allRows = vv_get_testers();
    usort($allRows, static fn(array $a, array $b): int =>
        strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

    if ($scope === 'selected') {
        $allowed = [];
        foreach ($keys as $key) {
            if (is_string($key)) {
                $allowed[$key] = true;
            }
        }
        $allRows = array_values(array_filter($allRows, static fn(array $row): bool =>
            isset($allowed[(string) ($row['key'] ?? '')])));
    }

    $candidates = array_values(array_filter($allRows, 'vv_receipt_is_pending'));
    $toAttempt = array_slice($candidates, 0, VEVAK_RECEIPT_BATCH_MAX);
    $stats = ['accepted' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0];
    foreach ($toAttempt as $candidate) {
        $key = (string) $candidate['key'];
        try {
            $result = vv_with_json_file(vv_data_file(), ['testers' => []], true,
                static function (&$data) use ($key, $sender): string {
                    $row = $data['testers'][$key] ?? null;
                    if (!is_array($row) || !vv_receipt_is_pending($row)) {
                        return 'skipped';
                    }
                    try {
                        $ok = vv_receipt_mail((string) $row['email'], $sender);
                    } catch (Throwable $mailError) {
                        $ok = false;
                    }
                    if (!$ok) {
                        error_log('VeVak tester acknowledgement: mail transport rejected a message.');
                        return 'failed';
                    }
                    $data['testers'][$key]['receipt_sent_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    $data['testers'][$key]['receipt_version'] = VEVAK_RECEIPT_VERSION;
                    return 'accepted';
                });
            $stats[$result]++;
        } catch (Throwable $error) {
            error_log('VeVak tester acknowledgement: ' . get_class($error));
            $stats['failed']++;
        }
    }
    $stats['remaining'] = vv_receipt_pending_count();
    return $stats;
}
