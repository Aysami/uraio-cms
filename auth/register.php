<?php
/**
 * Account registration.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_guest();

if ((string) setting('registration_open', '1') !== '1') {
    auth_page_start('Registration closed', 'New accounts are temporarily disabled. Contact support if you need access.');
    echo '<a class="btn btn-primary btn-block" href="' . e(app_url('auth/login.php')) . '">Sign in instead</a>';
    auth_page_end();
    exit;
}

$errors = [];
$values = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
];
$categorySlug = trim((string) ($_POST['category'] ?? $_GET['category'] ?? ''));
$planSlug = trim((string) ($_POST['plan'] ?? $_GET['plan'] ?? 'free'));

if (is_post()) {
    verify_csrf();
    if (!rate_limit('register:' . client_ip(), 6, 3600)) {
        $errors['form'] = 'Too many registration attempts. Please try again later.';
    }
    $values['name'] = trim((string) post('name', ''));
    $values['email'] = strtolower(trim((string) post('email', '')));
    $values['phone'] = trim((string) post('phone', ''));
    $password = (string) post('password', '');
    $confirm = (string) post('password_confirm', '');

    $errors += validate($values, [
        'name' => 'required|min:2|max:160',
        'email' => 'required|email|max:190',
        'phone' => 'phone',
    ]);
    if ($values['email'] !== '' && db_val('SELECT id FROM users WHERE email = ?', [$values['email']])) {
        $errors['email'] = 'An account with this email already exists.';
    }
    $pwErrors = password_errors($password);
    if ($pwErrors) {
        $errors['password'] = implode(' ', $pwErrors);
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the terms to continue.';
    }

    if (!$errors) {
        try {
            $userId = create_user([
                'name' => $values['name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'password' => $password,
            ]);
            $user = db_row('SELECT u.*, r.slug AS role_slug FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$userId]);
            login_user($user);

            $token = issue_verification_token($userId, 'email');
            $verifyUrl = app_url('auth/verify.php?token=' . urlencode($token));
            send_email_template('verify_email', $values['email'], [
                'name' => $values['name'],
                'verify_url' => $verifyUrl,
            ]);
            send_email_template('welcome', $values['email'], ['name' => $values['name']]);
            activity_log('auth.registered', 'user', $userId);

            $next = 'app/setup.php';
            if ($categorySlug !== '') {
                $next .= '?category=' . urlencode($categorySlug);
                if ($planSlug !== '') {
                    $next .= '&plan=' . urlencode($planSlug);
                }
            }
            flash('success', 'Welcome to Uraio. Let’s set up your first business.');
            redirect($next);
        } catch (Throwable $e) {
            $errors['form'] = 'We could not create your account. Please try again.';
            error_log('registration failed: ' . $e->getMessage());
        }
    }
}

auth_page_start('Create your account', 'Free to start. Your first business and website are included.');
?>
<form method="post" action="<?= e(app_url('auth/register.php')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
  <input type="hidden" name="plan" value="<?= e($planSlug) ?>">
  <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

  <div class="field">
    <label for="name">Full name</label>
    <input id="name" name="name" value="<?= e($values['name']) ?>" autocomplete="name" required autofocus>
    <?= render_field_error($errors, 'name') ?>
  </div>
  <div class="field">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" value="<?= e($values['email']) ?>" autocomplete="email" required>
    <?= render_field_error($errors, 'email') ?>
  </div>
  <div class="field">
    <label for="phone">Phone <span class="muted small">(optional)</span></label>
    <input id="phone" name="phone" type="tel" value="<?= e($values['phone']) ?>" autocomplete="tel">
    <?= render_field_error($errors, 'phone') ?>
  </div>
  <div class="form-grid">
    <div class="field field-col-6">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
      <?= render_field_error($errors, 'password') ?>
    </div>
    <div class="field field-col-6">
      <label for="password_confirm">Confirm password</label>
      <input id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
      <?= render_field_error($errors, 'password_confirm') ?>
    </div>
  </div>
  <small class="field-help" style="margin-top:-8px;display:block">At least 8 characters, including letters and numbers.</small>

  <div class="field field-check mt-2">
    <label class="switch-label">
      <input type="checkbox" name="terms" value="1" <?= checked(!empty($_POST['terms'])) ?>>
      <span>I agree to the terms of service and privacy policy.</span>
    </label>
  </div>
  <?= render_field_error($errors, 'terms') ?>

  <button class="btn btn-primary btn-lg btn-block" type="submit">Create my account <?= icon('arrow-right', 'icon icon-sm') ?></button>
</form>
<?php
auth_page_end('Already have an account? <a class="link" href="' . e(app_url('auth/login.php')) . '">Sign in</a>');
