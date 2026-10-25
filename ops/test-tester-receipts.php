<?php
declare(strict_types=1);
require dirname(__DIR__) . '/assets/tester-receipts.php';
if (PHP_SAPI !== 'cli' || !getenv('VEVAK_TESTERS_STORAGE_DIR') || vv_get_testers() !== []) {
    throw new RuntimeException('Use an empty private directory for the acknowledgement test.');
}
function receipt_check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}

$successEmail = 'successful-tester@example.org';
$failedEmail = 'failed-tester@example.org';
$secondEmail = 'second-tester@example.org';
$messages = [];
$sender = static function (string $to, string $subject, string $body) use (&$messages, $failedEmail): bool {
    $messages[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    return $to !== $failedEmail;
};

vv_register_tester($successEmail, 'Pixel 8a', 'Android 15', 'single', true);
vv_register_tester($failedEmail, 'Galaxy A54', 'Android 15', 'single', true);
vv_register_tester($secondEmail, 'Pixel 7', 'Android 14', 'dual', true);
receipt_check(vv_receipt_pending_count() === 3, 'Three eligible pending records expected');
receipt_check($messages === [], 'Registration must never send an email automatically');

$first = vv_receipt_send_batch('selected', [$successEmail, 'unknown@example.org', $successEmail], $sender);
receipt_check($first['accepted'] === 1 && $first['failed'] === 0, 'Selected recipient was not emailed exactly once');
receipt_check(count($messages) === 1 && $messages[0]['to'] === $successEmail, 'Selected mode sent to another person');
receipt_check(str_contains($messages[0]['body'], 'instructions') && str_contains($messages[0]['body'], 'télécharger'), 'Receipt lacks requested information');
receipt_check(!str_contains($messages[0]['body'], $failedEmail), 'Private recipient data leaked');
receipt_check(!str_contains($messages[0]['body'], $secondEmail), 'Other recipients leaked in email body');
receipt_check(!empty(vv_get_tester($successEmail)['receipt_sent_at']), 'Successful receipt timestamp not recorded');

vv_register_tester($successEmail, 'Pixel 8a', 'Android 15', 'single', true);
receipt_check(!empty(vv_get_tester($successEmail)['receipt_sent_at']), 'Repeat registration lost mail history');

$again = vv_receipt_send_batch('selected', [$successEmail], $sender);
receipt_check($again['accepted'] === 0 && count($messages) === 1, 'Previously sent mail was sent a second time');

$pending = vv_receipt_send_batch('pending', [], $sender);
receipt_check($pending['accepted'] === 1 && $pending['failed'] === 1, 'Pending batch did not handle a mail transport rejection');
receipt_check(empty(vv_get_tester($failedEmail)['receipt_sent_at']), 'Failed mail recorded as sent');
receipt_check(!empty(vv_get_tester($secondEmail)['receipt_sent_at']), 'Successful bulk email not recorded');
receipt_check(vv_receipt_pending_count() === 1, 'Only failed mail should remain pending');

$retry = vv_receipt_send_batch('pending', [], static fn(): bool => true);
receipt_check($retry['accepted'] === 1 && vv_receipt_pending_count() === 0, 'Failed message cannot be retried');

vv_with_json_file(vv_data_file(), ['testers' => []], true, static function (&$data): void {
    $data['testers']['no-consent@example.org'] = [
        'email' => 'no-consent@example.org',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'consent_version' => '',
    ];
});
$sentBefore = count($messages);
$skip = vv_receipt_send_batch('pending', [], $sender);
receipt_check($skip['accepted'] === 0 && count($messages) === $sentBefore, 'Unconsented address received an email');
receipt_check(vv_receipt_pending_count() === 0, 'Missing-consent record counted as pending');

$many = [];
for ($i = 0; $i < 23; $i++) {
    $email = 'bulk-' . $i . '@example.org';
    $many[] = $email;
    vv_register_tester($email, 'Test phone', 'Android 15', 'single', true);
}
receipt_check(vv_receipt_pending_count() === 23, 'Bulk setup invalid');
$bulkMessages = [];
$bulkSender = static function (string $to) use (&$bulkMessages): bool {
    $bulkMessages[] = $to;
    return true;
};
$batch = vv_receipt_send_batch('pending', [], $bulkSender);
receipt_check($batch['accepted'] === 20 && $batch['remaining'] === 3, 'Batch size cap not enforced');
receipt_check(count($bulkMessages) === 20 && count(array_unique($bulkMessages)) === 20, 'Bulk recipients duplicated');
$lastBatch = vv_receipt_send_batch('pending', [], $bulkSender);
receipt_check($lastBatch['accepted'] === 3 && $lastBatch['remaining'] === 0, 'Remaining recipients not processed');
receipt_check(count($bulkMessages) === 23, 'Unexpected final message count');

receipt_check(!vv_receipt_mail("invalid\r\nBcc:other@example.org", $sender), 'Invalid injected recipient accepted');
receipt_check(vv_delete_tester($successEmail), 'Tester deletion failed');

echo "VeVak signup acknowledgements: selection, privacy, duplicates, failure, retry, consent, 20-recipient cap and deletion OK.\n";
