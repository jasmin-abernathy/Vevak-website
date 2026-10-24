<?php
declare(strict_types=1);
require dirname(__DIR__) . '/assets/tester-storage.php';
if (PHP_SAPI !== 'cli' || !getenv('VEVAK_TESTERS_STORAGE_DIR') || vv_get_testers() !== []) {
    throw new RuntimeException('Use an empty temporary private directory for this test.');
}
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$email = 'feedback-selftest@example.org';
$sent = [];
$send = function (string $to, string $code) use (&$sent): bool { $sent[] = [$to, $code]; return true; };
function fresh_window(string $email): void {
    vv_with_json_file(vv_feedback_rate_file(), [], true, function (&$data): void { $data = ['clients' => []]; });
    vv_with_json_file(vv_data_file(), [], true, function (&$data) use ($email): void {
        if (isset($data['testers'][$email]['feedback_code'])) { $data['testers'][$email]['feedback_code']['sent_at'] = time() - 61; }
    });
}
vv_feedback_request_code('unknown@example.org', 'test-ip', $send);
check($sent === [], 'Unknown address received a code');
vv_register_tester($email, 'Pixel', 'Android', 'single', true);
vv_register_tester($email, 'Pixel', 'Android', 'single', true);
check(count(vv_get_testers()) === 1, 'Duplicate registration');
vv_feedback_request_code($email, 'test-ip', $send);
check($sent === [], 'Unapproved address received a code');
vv_set_feedback_enabled($email, true);
vv_feedback_request_code($email, 'test-ip', $send);
check(count($sent) === 1 && $sent[0][0] === $email, 'Missing or misdirected mail');
$code = $sent[0][1];
check(!str_contains((string) file_get_contents(vv_data_file()), $code), 'Code stored in clear');
vv_feedback_request_code($email, 'test-ip', $send);
check(count($sent) === 1, 'Resend cooldown missing');
check(!vv_feedback_verify_code('unknown@example.org', $code, 'test-ip'), 'Wrong address accepted');
check(vv_feedback_verify_code($email, $code, 'test-ip'), 'Correct code rejected');
check(!vv_feedback_verify_code($email, $code, 'test-ip'), 'Replay accepted');
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$old = end($sent)[1];
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$new = end($sent)[1];
if ($old !== $new) { check(!vv_feedback_verify_code($email, $old, 'test-ip'), 'Replaced code accepted'); }
check(vv_feedback_verify_code($email, $new, 'test-ip'), 'Replacement code rejected');
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$code = end($sent)[1];
vv_with_json_file(vv_data_file(), [], true, function (&$data) use ($email): void { $data['testers'][$email]['feedback_code']['expires_at'] = time() - 1; });
check(!vv_feedback_verify_code($email, $code, 'test-ip'), 'Expired code accepted');
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$code = end($sent)[1];
for ($i = 0; $i < 5; $i++) { check(!vv_feedback_verify_code($email, 'bad', 'rotating-' . $i), 'Bad code accepted'); }
check(!vv_feedback_verify_code($email, $code, 'another-ip'), 'Attempts reset by IP change');
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$code = end($sent)[1];
vv_set_feedback_enabled($email, false);
check(!vv_feedback_verify_code($email, $code, 'test-ip'), 'Suspended tester accepted');
vv_set_feedback_enabled($email, true);
check(!vv_feedback_verify_code($email, $code, 'test-ip'), 'Revoked code restored');
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', $send);
$code = end($sent)[1];
fresh_window($email);
vv_feedback_request_code($email, 'test-ip', static fn(): bool => false);
check(vv_feedback_verify_code($email, $code, 'test-ip'), 'Transport failure destroyed previous code');
fresh_window($email);
for ($i = 0; $i < 5; $i++) { vv_feedback_request_code($email, 'rotating-' . $i, $send); }
$limited = false;
try { vv_feedback_request_code($email, 'yet-another-ip', $send); } catch (RuntimeException $error) { $limited = true; }
check($limited, 'Address send limit missing');
fresh_window($email);
for ($i = 0; $i < 20; $i++) { vv_feedback_request_code('unknown-' . $i . '@example.org', 'same-ip', $send); }
$limited = false;
try { vv_feedback_request_code('unknown-last@example.org', 'same-ip', $send); } catch (RuntimeException $error) { $limited = true; }
check($limited, 'IP send limit missing');
vv_feedback_save_answers($email, ['q1_launch' => 'very_clear', 'tested_variant' => 'foss', 'tested_version' => '0.3.15', 'tested_revision' => 'a1b2c3d4e5f6'], true);
$storedAnswers = vv_feedback_get_answers($email)['answers'] ?? [];
check(($storedAnswers['q1_launch'] ?? '') === 'very_clear', 'Answers not saved');
check(($storedAnswers['tested_variant'] ?? '') === 'foss', 'FOSS variant not saved');
check(($storedAnswers['tested_version'] ?? '') === '0.3.15', 'Tested version not saved');
check(($storedAnswers['tested_revision'] ?? '') === 'a1b2c3d4e5f6', 'Build revision not saved');
check(vv_delete_tester($email), 'Delete failed');
check(vv_get_testers() === [] && vv_feedback_get_answers($email) === [], 'Private data not deleted');
echo "Feedback codes: authorization, delivery stub, replay, expiry, resend, attempts, limits, revocation, answers OK.\n";
