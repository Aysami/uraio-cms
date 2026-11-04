<?php
/**
 * Request a password reset link.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_guest();

$sent = false;
$error = null;

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) post('email', '')));
    if (!rate_limit('forgot:' . client_ip(), 5, 3600)) {
        $error = 'Too many reset requests. Please try again later.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $user = db_row("SELECT * FROM users WHERE email = ? AND status = 'active' AND deleted_at IS NULL", [$email]);
        if ($user) {
            $token = issue_password_reset((int) $user['id']);
            send_email_template('password_reset', $email, [
                'name' => $user['name'],
                'reset_url' => app_url('auth/reset-password.php?token=' . urlencode($token)),
            ]);
            activity_log('auth.password_reset_requested', 'user', $user['id']);
        }
        $sent = true;
    }
}

auth_page_start('Reset your password', 'Enter the email address on your account and we will send a reset link.');
?>
<?php if ($sent): ?>
  <div class="alert alert-success"><?= icon('check') ?><div>If an account exists for that email, a reset link is on its way. The link expires in one hour.</div></div>
  <a class="btn btn-primary btn-block" href="<?= e(app_url('auth/login.php')) ?>">Back to sign in</a>
<?php else: ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= e(app_url('auth/forgot-password.php')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" value="<?= e((string) post('email', '')) ?>" autocomplete="email" required autofocus>
    </div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
  </form>
<?php endif; ?>
<?php
auth_page_end('Remembered it? <a class="link" href="' . e(app_url('auth/login.php')) . '">Sign in</a>');
