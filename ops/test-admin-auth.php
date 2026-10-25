<?php
declare(strict_types=1);
require dirname(__DIR__) . '/test/auth.php';
if (PHP_SAPI !== 'cli' || !getenv('VEVAK_TESTERS_STORAGE_DIR') || vv_get_testers() !== []) {
    throw new RuntimeException('Admin tests require an empty private test directory.');
}
function assert_admin(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
function read_code(array $mail): string {
    if (!preg_match('/\b([0-9]{8})\b/', $mail[2], $m)) {
        throw new RuntimeException('No eight-digit code in fake message.');
    }
    return $m[1];
}
$sent = [];
$fakeMailer = static function (string $recipient, string $subject, string $body) use (&$sent): bool {
    $sent[] = [$recipient, $subject, $body];
    return true;
};
assert_admin(!vv_admin_is_initialized(), 'Admin account must not preexist in test directory');
$setupNonce = bin2hex(random_bytes(24));
assert_admin(vv_admin_request_code('setup', $setupNonce, 'initial-ip', $fakeMailer), 'Setup email not issued');
assert_admin(count($sent) === 1 && $sent[0][0] === vv_admin_email(), 'Admin mail recipient mismatch');
$setupCode = read_code($sent[0]);
assert_admin(!vv_admin_verify_code('setup', $setupNonce, '00000000', 'LongPrivateTestPassword2026!') || $setupCode === '00000000', 'Bad setup code accepted');
assert_admin(vv_admin_verify_code('setup', $setupNonce, $setupCode, 'LongPrivateTestPassword2026!'), 'Setup verification failed');
assert_admin(vv_admin_is_initialized(), 'Admin account not activated');
assert_admin(!vv_admin_verify_code('setup', $setupNonce, $setupCode, 'AnyVeryLongPassword!'), 'Setup code replayed');
assert_admin(!vv_admin_request_code('setup', bin2hex(random_bytes(24)), 'another-ip', $fakeMailer), 'Existing account could be reinitialized');
assert_admin(vv_admin_check_password('LongPrivateTestPassword2026!'), 'Setup password not hashed correctly');
assert_admin(!vv_admin_check_password('wrong'), 'Wrong password accepted');
assert_admin(!str_contains((string) file_get_contents(vv_admin_data_file()), 'LongPrivateTestPassword2026!'), 'Plaintext password stored');

$loginNonce = bin2hex(random_bytes(24));
assert_admin(vv_admin_request_code('login', $loginNonce, 'login-ip', $fakeMailer), 'Login code was not sent');
$loginCode = read_code(end($sent));
assert_admin(vv_admin_verify_code('login', $loginNonce, $loginCode), 'Valid login code refused');
assert_admin(!vv_admin_verify_code('login', $loginNonce, $loginCode), 'Login code replayed');

vv_admin_session_start();
assert_admin(!vv_admin_is_authenticated(), 'Unauthenticated session allowed');
vv_admin_logged_in();
assert_admin(vv_admin_is_authenticated(), 'Authenticated session refused');
$oldSessionVersion = (string) $_SESSION['vv_admin_version'];
assert_admin(!vv_admin_change_password('wrong', 'DifferentVeryLongPassword2026!'), 'Incorrect old password accepted');
assert_admin(vv_admin_change_password('LongPrivateTestPassword2026!', 'DifferentVeryLongPassword2026!'), 'Password update failed');
assert_admin(!vv_admin_is_authenticated(), 'Old session not invalidated after password change');
assert_admin($oldSessionVersion !== (string) vv_admin_account()['version'], 'Session version not rotated');
assert_admin(!vv_admin_check_password('LongPrivateTestPassword2026!'), 'Old password still works');
assert_admin(vv_admin_check_password('DifferentVeryLongPassword2026!'), 'New password does not work');

$resetNonce = bin2hex(random_bytes(24));
assert_admin(vv_admin_request_code('reset', $resetNonce, 'reset-ip', $fakeMailer), 'Reset code not issued');
$resetCode = read_code(end($sent));
assert_admin(vv_admin_verify_code('reset', $resetNonce, $resetCode, 'ResetPasswordLongEnough2026!'), 'Reset failed');
assert_admin(!vv_admin_verify_code('reset', $resetNonce, $resetCode, 'AnotherPasswordLongEnough2026!'), 'Reset code replayed');
assert_admin(vv_admin_check_password('ResetPasswordLongEnough2026!'), 'Password reset not applied');

$expiredNonce = bin2hex(random_bytes(24));
assert_admin(vv_admin_request_code('login', $expiredNonce, 'expiry-ip', $fakeMailer), 'Expiry test code absent');
$expiredCode = read_code(end($sent));
vv_with_json_file(vv_admin_data_file(), [], true, static function (&$data) use ($expiredNonce): void {
    $data['challenges'][$expiredNonce]['expires'] = time() - 1;
});
assert_admin(!vv_admin_verify_code('login', $expiredNonce, $expiredCode), 'Expired code accepted');

$limitNonce = bin2hex(random_bytes(24));
assert_admin(vv_admin_request_code('login', $limitNonce, 'limit-ip', $fakeMailer), 'Lockout test code absent');
$limitCode = read_code(end($sent));
$incorrect = $limitCode === '00000000' ? '11111111' : '00000000';
for ($i = 0; $i < 5; $i++) {
    assert_admin(!vv_admin_verify_code('login', $limitNonce, $incorrect), 'Incorrect code accepted');
}
assert_admin(!vv_admin_verify_code('login', $limitNonce, $limitCode), 'Valid code accepted after five failures');
assert_admin(!str_contains((string) file_get_contents(vv_admin_data_file()), $limitCode), 'Plaintext one-time code stored');
echo "VeVak administrator auth: setup, code, password, replay, reset, revoke, expiry and attempt limit OK.\n";
