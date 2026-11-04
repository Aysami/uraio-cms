<?php
/**
 * Personal account settings: profile, avatar, email verification, password
 * changes and signed-in devices.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = active_business();
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');

    if ($action === 'profile') {
        $name = trim((string) post('name', ''));
        $email = strtolower(trim((string) post('email', '')));
        $phone = trim((string) post('phone', ''));
        if ($name === '') {
            $errors['name'] = 'Your name is required.';
        } elseif (mb_strlen($name) > 160) {
            $errors['name'] = 'Name must be 160 characters or fewer.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (db_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            $errors['email'] = 'That email address is already in use.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $avatar = null;
        try {
            $avatar = upload_image('avatar', 'avatars', 3_145_728);
        } catch (Throwable $e) {
            $errors['avatar'] = $e->getMessage();
        }
        if (!$errors) {
            $emailChanged = $email !== strtolower((string) $user['email']);
            $data = [
                'name' => str_limit($name, 160),
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
            ];
            if ($avatar !== null) {
                $data['avatar'] = $avatar;
            }
            if ($emailChanged) {
                $data['email_verified_at'] = null;
            }
            db_update('users', $data, 'id = :id', ['id' => $user['id']]);
            if ($emailChanged) {
                $token = issue_verification_token((int) $user['id']);
                send_email_template('verify_email', $email, [
                    'name' => $name,
                    'verify_url' => app_url('auth/verify.php?token=' . rawurlencode($token)),
                ]);
                flash('info', 'Profile saved. Check ' . $email . ' for a link to verify your new address.');
            } else {
                flash('success', 'Profile saved.');
            }
            activity_log('account.profile_updated', 'user', $user['id'], ['email_changed' => $emailChanged]);
            redirect('app/profile.php');
        }
    }

    if ($action === 'password') {
        $current = (string) post('current_password', '');
        $new = (string) post('new_password', '');
        $confirm = (string) post('new_password_confirm', '');
        if (!password_verify_secure($current, (string) $user['password_hash'])) {
            $errors['password'] = 'Your current password is not correct.';
        }
        $policy = password_errors($new);
        if ($policy) {
            $errors['password'] = implode(' ', $policy);
        } elseif ($new !== $confirm) {
            $errors['password'] = 'The new passwords do not match.';
        } elseif (hash_equals($current, $new)) {
            $errors['password'] = 'Choose a password different from your current one.';
        }
        if (!$errors) {
            db_update('users', ['password_hash' => password_hash_secure($new)], 'id = :id', ['id' => $user['id']]);
            db_exec('DELETE FROM user_sessions WHERE user_id = ? AND token_hash <> ?', [$user['id'], hash('sha256', session_id())]);
            activity_log('account.password_changed', 'user', $user['id']);
            send_email_template('password_reset', (string) $user['email'], [
                'name' => $user['name'],
                'reset_url' => app_url('auth/forgot-password.php'),
            ]);
            flash('success', 'Password changed. Other devices have been signed out.');
            redirect('app/profile.php');
        }
    }

    if ($action === 'revoke_sessions') {
        $deleted = db_exec('DELETE FROM user_sessions WHERE user_id = ? AND token_hash <> ?', [$user['id'], hash('sha256', session_id())]);
        activity_log('account.sessions_revoked', 'user', $user['id'], ['count' => $deleted]);
        flash('success', 'Other devices signed out.');
        redirect('app/profile.php');
    }
}

$memberships = db_all(
    "SELECT b.id, b.name, b.slug, b.logo, bm.role, bm.status, c.name AS category_name
     FROM business_members bm JOIN businesses b ON b.id = bm.business_id
     LEFT JOIN categories c ON c.id = b.category_id
     WHERE bm.user_id = ? AND b.deleted_at IS NULL ORDER BY b.name ASC",
    [$user['id']]
);
$sessions = db_all(
    'SELECT * FROM user_sessions WHERE user_id = ? AND expires_at > NOW() ORDER BY last_activity DESC LIMIT 20',
    [$user['id']]
);
$activity = db_all(
    'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12',
    [$user['id']]
);

if ($business) {
    layout_app_start($business, 'profile', 'My account', (string) $user['name']);
} else {
    layout_head('My account', ['noindex' => true]);
    ?>
    <div class="app-shell" style="display:block">
      <header class="topbar"><div class="topbar-title">My account</div><div class="topbar-actions"><a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/setup.php')) ?>">Create a business</a></div></header>
      <main class="content" style="max-width:900px;margin:0 auto"><?php layout_flashes(); ?>
    <?php
}
?>
<div class="page-head">
  <div>
    <h1>My account</h1>
    <p>Your personal profile, password and signed-in devices.</p>
  </div>
  <div class="page-actions">
    <?php if ($business): ?><a class="btn btn-ghost" href="<?= e(app_url('app/dashboard.php')) ?>"><?= icon('grid', 'icon icon-sm') ?> Dashboard</a><?php endif; ?>
  </div>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e(reset($errors)) ?></div></div><?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Profile</h2>
          <p class="small muted" style="margin:4px 0 0">Used for sign-in and team notifications.</p>
        </div>
        <?php if (empty($user['email_verified_at'])): ?><span class="badge badge-warning">Email unverified</span><?php else: ?><span class="badge badge-success">Email verified</span><?php endif; ?>
      </div>
      <form method="post" action="<?= e(app_url('app/profile.php')) ?>" class="card-body" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="flex mb-2">
          <?= business_logo_html(['name' => $user['name'], 'logo' => $user['avatar']], 'avatar avatar-lg') ?>
          <div class="field grow">
            <label for="avatar">Profile photo</label>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
            <span class="field-help">JPG, PNG, WebP or GIF up to 3 MB.</span>
            <?= render_field_error($errors, 'avatar') ?>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" maxlength="160" required>
            <?= render_field_error($errors, 'name') ?>
          </div>
          <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
            <?= render_field_error($errors, 'email') ?>
            <span class="field-help">Changing this sends a new verification link.</span>
          </div>
          <div class="field">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
            <?= render_field_error($errors, 'phone') ?>
          </div>
          <div class="field">
            <label>Member since</label>
            <input type="text" value="<?= e(nice_date($user['created_at'])) ?>" readonly>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save profile</button>
        </div>
      </form>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Change password</h2></div>
      <form method="post" action="<?= e(app_url('app/profile.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="form-grid">
          <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="field">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
            <span class="field-help">At least 8 characters, including letters and numbers.</span>
          </div>
          <div class="field">
            <label for="new_password_confirm">Confirm new password</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('lock', 'icon icon-sm') ?> Update password</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Signed-in devices</h2>
          <p class="small muted" style="margin:4px 0 0">Sign out anything you do not recognise.</p>
        </div>
        <?php if (count($sessions) > 1): ?>
          <form method="post" action="<?= e(app_url('app/profile.php')) ?>" data-confirm="Sign out all other devices?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="revoke_sessions">
            <button class="btn btn-ghost btn-sm" type="submit">Sign out others</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Device</th><th>IP</th><th>Last active</th></tr></thead>
          <tbody>
            <?php foreach ($sessions as $session): ?>
              <tr>
                <td>
                  <span class="cell-main"><?= e(str_limit($session['user_agent'] ?: 'Unknown browser', 60)) ?></span>
                  <?php if (hash_equals((string) $session['token_hash'], hash('sha256', session_id()))): ?><span class="cell-sub">This device</span><?php endif; ?>
                </td>
                <td class="mono small"><?= e($session['ip'] ?: '—') ?></td>
                <td class="small muted"><?= e(time_ago($session['last_activity'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Your businesses</h2></div>
      <div class="card-body">
        <?php if (!$memberships): ?>
          <p class="small muted">You do not belong to a business yet.</p>
          <a class="btn btn-primary btn-block" href="<?= e(app_url('app/setup.php')) ?>">Create a business</a>
        <?php else: ?>
          <div class="side-nav">
            <?php foreach ($memberships as $membership): ?>
              <a class="side-link" href="<?= e(app_url('app/dashboard.php?business=' . $membership['id'])) ?>">
                <?= business_logo_html(['name' => $membership['name'], 'logo' => $membership['logo']], 'avatar') ?>
                <span style="min-width:0"><strong class="truncate" style="display:block"><?= e($membership['name']) ?></strong><span class="muted small"><?= e(ucfirst($membership['role'])) ?> · <?= e($membership['category_name'] ?? 'Business') ?></span></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Recent activity</h2></div>
      <div class="card-body">
        <?php if (!$activity): ?>
          <p class="small muted" style="margin:0">No account activity recorded yet.</p>
        <?php else: ?>
          <?php foreach ($activity as $event): ?>
            <div class="mb-2">
              <div class="small strong"><?= e(ucwords(str_replace(['.', '_'], [' · ', ' '], (string) $event['action']))) ?></div>
              <div class="small muted"><?= e(time_ago($event['created_at'])) ?><?= $event['ip'] ? ' · ' . e($event['ip']) : '' ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Account help</h2></div>
      <div class="card-body">
        <p class="small muted">Need to close your account or change an email you no longer control? Contact support.</p>
        <a class="btn btn-ghost btn-block" href="mailto:<?= e((string) setting('support_email', 'support@uraio.com')) ?>"><?= icon('mail', 'icon icon-sm') ?> Email support</a>
      </div>
    </div>
  </div>
</div>
<?php
if ($business) {
    layout_app_end(['scripts' => ['app.js']]);
} else {
    echo '</main></div>';
    layout_end(['scripts' => ['app.js']]);
}
