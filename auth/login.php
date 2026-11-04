<?php
/**
 * Sign in.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_guest();

$email = trim((string) ($_POST['email'] ?? ''));
$error = null;

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) post('email', '')));
    $password = (string) post('password', '');
    if (!rate_limit('login:' . client_ip(), 20, 900)) {
        $error = 'Too many attempts from this device. Try again in a few minutes.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok']) {
            $user = $result['user'];
            if ((int) ($user['is_super_admin'] ?? 0) === 1 && empty($_SESSION['_intended'])) {
                flash('success', 'Welcome back, ' . $user['name'] . '.');
                redirect('admin/index.php');
            }
            flash('success', 'Welcome back, ' . explode(' ', (string) $user['name'])[0] . '.');
            if (user_businesses((int) $user['id'])) {
                redirect(safe_intended_path('app/dashboard.php'));
            }
            redirect('app/setup.php');
        }
        $error = $result['error'];
    }
}

auth_page_start('Welcome back', 'Sign in to manage your business, website and team.');
?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= icon('alert') ?><div><?= e($error) ?></div></div>
<?php endif; ?>
<form method="post" action="<?= e(app_url('auth/login.php')) ?>">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <div class="flex-between mb-2">
    <span class="small muted">Secure sign-in with Argon2id</span>
    <a class="small link" href="<?= e(app_url('auth/forgot-password.php')) ?>">Forgot password?</a>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in <?= icon('arrow-right', 'icon icon-sm') ?></button>
</form>
<?php
auth_page_end('New to Uraio? <a class="link" href="' . e(app_url('auth/register.php')) . '">Create an account</a>');
