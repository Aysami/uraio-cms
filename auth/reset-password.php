<?php
/**
 * Complete a password reset with a single-use token.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_guest();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$valid = $token !== '' && (bool) db_row(
    'SELECT id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
    [hash('sha256', $token)]
);
$errors = [];

if (!$valid) {
    auth_page_start('Reset link expired', 'This password reset link is invalid or has expired.');
    echo '<a class="btn btn-primary btn-block" href="' . e(app_url('auth/forgot-password.php')) . '">Request a new link</a>';
    auth_page_end();
    exit;
}

if (is_post()) {
    verify_csrf();
    $password = (string) post('password', '');
    $confirm = (string) post('password_confirm', '');
    $pwErrors = password_errors($password);
    if ($pwErrors) {
        $errors['password'] = implode(' ', $pwErrors);
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (!$errors && reset_password_with_token($token, $password)) {
        flash('success', 'Your password has been updated. Sign in with your new password.');
        redirect('auth/login.php');
    }
    if (!$errors) {
        $errors['form'] = 'This reset link is no longer valid. Request a new one.';
    }
}

auth_page_start('Choose a new password', 'Use at least 8 characters, including letters and numbers.');
?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>
<form method="post" action="<?= e(app_url('auth/reset-password.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field">
    <label for="password">New password</label>
    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required autofocus>
    <?= render_field_error($errors, 'password') ?>
  </div>
  <div class="field">
    <label for="password_confirm">Confirm new password</label>
    <input id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
    <?= render_field_error($errors, 'password_confirm') ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Update password</button>
</form>
<?php
auth_page_end('Back to <a class="link" href="' . e(app_url('auth/login.php')) . '">sign in</a>');
