<?php
/**
 * Admin login page.
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin.php';

if (is_admin_user()) {
    redirect('admin/index.php');
}

$errors = [];

if (is_post()) {
    verify_csrf();
    $email = strtolower(trim((string) post('email', '')));
    $password = (string) post('password', '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Enter your password.';
    } else {
        $result = attempt_login($email, $password);
        if (!empty($result['ok']) && is_admin_user($result['user'])) {
            $intended = (string) ($_SESSION['_intended'] ?? '');
            unset($_SESSION['_intended']);
            if ($intended !== '' && str_contains($intended, '/admin/')) {
                redirect($intended);
            }
            redirect('admin/index.php');
        }
        if (!empty($result['ok']) && !is_admin_user($result['user'])) {
            logout_user();
            $errors['form'] = 'Your account does not have admin access.';
        } else {
            $errors['form'] = $result['error'] ?? 'Invalid email or password.';
        }
    }
}

admin_layout_head('Sign in', ['body_class' => 'admin-auth-body']);
?>
<div class="admin-auth-split">
  <aside class="admin-auth-aside">
    <div>
      <a class="brand" href="<?= e(app_url('index.php')) ?>">
        <span class="brand-mark"><?= icon('sparkles') ?></span>
        <span>Uraio<span class="muted" style="font-weight:500"> Business OS</span></span>
      </a>
      <h2>Administration panel</h2>
      <p>Manage users, businesses, categories, templates, subscriptions and system settings.</p>
      <ul class="check-list mt-3">
        <li>Role-based access control</li>
        <li>Multi-tenant business management</li>
        <li>Category and module registry</li>
        <li>Website template administration</li>
        <li>Subscription and payment oversight</li>
        <li>Activity and audit logs</li>
      </ul>
    </div>
    <p class="small" style="color:rgba(255,255,255,.6)">© <?= date('Y') ?> Uraio Business OS</p>
  </aside>
  <div class="admin-auth-form-side">
    <a class="brand" href="<?= e(app_url('index.php')) ?>">
      <span class="brand-mark brand-mark-sm"><?= icon('sparkles') ?></span>
      <span>Uraio Admin</span>
    </a>
    <h1>Sign in to admin</h1>
    <p class="auth-sub">Use your administrator credentials.</p>
    <?php admin_flashes(); ?>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>
    <form method="post" action="<?= e(app_url('admin/login.php')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e(old('email', '')) ?>" required autocomplete="email">
        <?= render_field_error($errors, 'email') ?>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <?= render_field_error($errors, 'password') ?>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><?= icon('lock', 'icon icon-sm') ?> Sign in</button>
    </form>
    <div class="auth-foot">
      <a href="<?= e(app_url('auth/login.php')) ?>">Back to Business OS</a>
    </div>
  </div>
</div>
<?php
admin_layout_end(['scripts' => ['app.js']]);