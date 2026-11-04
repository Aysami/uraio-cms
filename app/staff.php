<?php
/**
 * Team management: invite staff, change roles and remove access.
 * Memberships are always scoped to the active business.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$isOwner = ($business['member_role'] ?? '') === 'owner';
$canManage = $isOwner || ($business['member_role'] ?? '') === 'manager' || is_admin_user($user);
$errors = [];

$limits = business_limits($business);
$seatLimit = max(1, (int) ($limits['staff'] ?? 3));
$seatCount = (int) db_val(
    "SELECT COUNT(*) FROM business_members WHERE business_id = ? AND role <> 'owner' AND status IN ('active','invited')",
    [$businessId]
);

function team_member(int $businessId, int $membershipId): ?array
{
    return db_row(
        'SELECT bm.*, u.name, u.email, u.avatar, u.last_login_at, u.status AS user_status
         FROM business_members bm JOIN users u ON u.id = bm.user_id
         WHERE bm.id = ? AND bm.business_id = ?',
        [$membershipId, $businessId]
    );
}

function send_team_invite(array $business, array $inviter, string $email, string $role, string $token): bool
{
    $url = app_url('auth/reset-password.php?token=' . rawurlencode($token));
    $body = email_layout(
        'Join ' . $business['name'] . ' on Uraio',
        '<p>Hello,</p><p><strong>' . e($inviter['name']) . '</strong> invited you to join <strong>' . e($business['name']) . '</strong> as ' . e($role) . '.</p>'
        . '<p>Set your password to activate your account. This link expires in 1 hour.</p>',
        'Set my password',
        $url
    );
    return send_mail($email, 'You are invited to ' . $business['name'] . ' on Uraio', $body);
}

if (is_post()) {
    verify_csrf();
    if (!$canManage) {
        flash('danger', 'Only an owner or manager can manage the team.');
        redirect('app/staff.php');
    }
    $action = (string) post('action', '');

    if ($action === 'invite') {
        $email = strtolower(trim((string) post('email', '')));
        $role = (string) post('role', 'staff');
        if (!in_array($role, ['manager', 'staff'], true)) {
            $errors['invite'] = 'Choose a valid role.';
        }
        if ($role === 'manager' && !$isOwner && !is_admin_user($user)) {
            $errors['invite'] = 'Only the business owner can invite a manager.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['invite'] = 'Enter a valid email address.';
        }
        if ($seatCount >= $seatLimit) {
            $errors['invite'] = 'Your plan includes ' . $seatLimit . ' team seat' . ($seatLimit === 1 ? '' : 's') . ' and they are all in use. Upgrade in Billing to add more.';
        }
        $existingUser = $email !== '' ? db_row('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]) : null;
        if ($existingUser) {
            $already = db_val('SELECT id FROM business_members WHERE business_id = ? AND user_id = ?', [$businessId, $existingUser['id']]);
            if ($already) {
                $errors['invite'] = 'That person is already on this team.';
            }
        }
        if (!$errors) {
            if ($existingUser) {
                $memberUserId = (int) $existingUser['id'];
                $membershipStatus = 'active';
            } else {
                $temporary = 'Tmp-' . bin2hex(random_bytes(10));
                $memberUserId = create_user([
                    'name' => ucwords(str_replace(['.', '_', '-'], ' ', strstr($email, '@', true) ?: 'Team member')),
                    'email' => $email,
                    'password' => $temporary,
                    'role_id' => db_val("SELECT id FROM roles WHERE slug = 'staff'"),
                ]);
                db_update('users', ['status' => 'pending'], 'id = :id', ['id' => $memberUserId]);
                $membershipStatus = 'invited';
            }
            db_insert('business_members', [
                'business_id' => $businessId,
                'user_id' => $memberUserId,
                'role' => $role,
                'status' => $membershipStatus,
            ]);
            $token = issue_password_reset($memberUserId);
            send_team_invite($business, $user, $email, $role, $token);
            if ($existingUser) {
                notify_user($memberUserId, 'Added to ' . $business['name'], $user['name'] . ' added you as ' . $role . '.', 'app/dashboard.php?business=' . $businessId, $businessId, 'team');
            }
            activity_log('team.invited', 'business_member', $memberUserId, ['email' => $email, 'role' => $role], $businessId);
            flash('success', 'Invitation sent to ' . $email . '. They will appear as active once they accept.');
            redirect('app/staff.php');
        }
    }

    if ($action === 'update') {
        $membershipId = (int) post('membership_id', 0);
        $member = team_member($businessId, $membershipId);
        $role = (string) post('role', 'staff');
        $status = (string) post('status', 'active');
        if (!$member) {
            $errors['form'] = 'Team member not found.';
        } elseif ($member['role'] === 'owner') {
            $errors['form'] = 'The business owner role cannot be changed here.';
        } elseif ((int) $member['user_id'] === (int) $user['id']) {
            $errors['form'] = 'You cannot change your own membership.';
        } elseif (!$isOwner && $member['role'] === 'manager' && !is_admin_user($user)) {
            $errors['form'] = 'Only the business owner can change a manager.';
        } elseif (!in_array($role, ['manager', 'staff'], true) || !in_array($status, ['active', 'disabled'], true)) {
            $errors['form'] = 'Choose a valid role and status.';
        } elseif ($role === 'manager' && !$isOwner && !is_admin_user($user)) {
            $errors['form'] = 'Only the business owner can assign the manager role.';
        } else {
            db_update('business_members', ['role' => $role, 'status' => $status], 'id = :id', ['id' => $membershipId]);
            activity_log('team.updated', 'business_member', $membershipId, ['role' => $role, 'status' => $status], $businessId);
            if ($status === 'disabled') {
                notify_user((int) $member['user_id'], 'Access changed', 'Your access to ' . $business['name'] . ' was disabled.', '', $businessId, 'team');
            }
            flash('success', 'Team member updated.');
            redirect('app/staff.php');
        }
    }

    if ($action === 'resend') {
        $membershipId = (int) post('membership_id', 0);
        $member = team_member($businessId, $membershipId);
        if (!$member || $member['status'] !== 'invited') {
            flash('warning', 'That invitation is no longer pending.');
        } else {
            $token = issue_password_reset((int) $member['user_id']);
            send_team_invite($business, $user, (string) $member['email'], (string) $member['role'], $token);
            flash('success', 'Invitation resent to ' . $member['email'] . '.');
        }
        redirect('app/staff.php');
    }

    if ($action === 'remove') {
        $membershipId = (int) post('membership_id', 0);
        $member = team_member($businessId, $membershipId);
        if (!$member) {
            flash('warning', 'Team member not found.');
        } elseif ($member['role'] === 'owner') {
            flash('danger', 'The business owner cannot be removed.');
        } elseif ((int) $member['user_id'] === (int) $user['id']) {
            flash('danger', 'You cannot remove yourself.');
        } elseif (!$isOwner && $member['role'] === 'manager' && !is_admin_user($user)) {
            flash('danger', 'Only the business owner can remove a manager.');
        } else {
            db_exec('DELETE FROM business_members WHERE id = ? AND business_id = ?', [$membershipId, $businessId]);
            notify_user((int) $member['user_id'], 'Removed from ' . $business['name'], 'Your access to this business was removed.', '', $businessId, 'team');
            activity_log('team.removed', 'business_member', $membershipId, ['email' => $member['email']], $businessId);
            flash('success', $member['name'] . ' was removed from the team. Their personal account is unchanged.');
        }
        redirect('app/staff.php');
    }
}

$members = db_all(
    "SELECT bm.*, u.name, u.email, u.avatar, u.last_login_at, u.status AS user_status
     FROM business_members bm JOIN users u ON u.id = bm.user_id
     WHERE bm.business_id = ?
     ORDER BY FIELD(bm.role, 'owner', 'manager', 'staff'), u.name ASC",
    [$businessId]
);
$roleLabels = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'];

layout_app_start($business, 'staff', 'Team', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Team</h1>
    <p>Give people access to <?= e($business['name']) ?> without sharing your password.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('app/billing.php')) ?>"><?= icon('credit-card', 'icon icon-sm') ?> Manage plan</a>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['invite'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['invite']) ?></div></div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><div class="stat-label">Team seats</div><div class="stat-value"><?= e((string) $seatCount) ?> / <?= e((string) $seatLimit) ?></div><div class="stat-note">Owner not counted</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('mail') ?></div><div class="stat-label">Pending invites</div><div class="stat-value"><?= e((string) count(array_filter($members, fn ($m) => $m['status'] === 'invited'))) ?></div><div class="stat-note">Waiting to set a password</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('shield') ?></div><div class="stat-label">Managers</div><div class="stat-value"><?= e((string) count(array_filter($members, fn ($m) => $m['role'] === 'manager'))) ?></div><div class="stat-note">Can manage most settings</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('user') ?></div><div class="stat-label">Active staff</div><div class="stat-value"><?= e((string) count(array_filter($members, fn ($m) => $m['role'] === 'staff' && $m['status'] === 'active'))) ?></div><div class="stat-note">Day-to-day access</div></div>
</div>

<div class="grid grid-2-1">
  <div>
    <div class="card">
      <div class="card-head"><h2 class="mt-0">People with access</h2></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($members as $member): ?>
              <?php $isSelf = (int) $member['user_id'] === (int) $user['id']; ?>
              <tr>
                <td>
                  <div class="flex">
                    <?= business_logo_html(['name' => $member['name'], 'logo' => $member['avatar']], 'avatar') ?>
                    <div>
                      <span class="cell-main"><?= e($member['name']) ?><?= $isSelf ? ' (you)' : '' ?></span>
                      <span class="cell-sub"><?= e($member['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($member['role'] === 'owner' || $isSelf || (!$isOwner && $member['role'] === 'manager' && !is_admin_user($user))): ?>
                    <span class="badge badge-info"><?= e($roleLabels[$member['role']] ?? $member['role']) ?></span>
                  <?php else: ?>
                    <form method="post" action="<?= e(app_url('app/staff.php')) ?>" class="flex">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="membership_id" value="<?= (int) $member['id'] ?>">
                      <select name="role" aria-label="Role for <?= e($member['name']) ?>" style="max-width:130px">
                        <option value="staff" <?= selected($member['role'], 'staff') ?>>Staff</option>
                        <?php if ($isOwner || is_admin_user($user)): ?><option value="manager" <?= selected($member['role'], 'manager') ?>>Manager</option><?php endif; ?>
                      </select>
                      <select name="status" aria-label="Status for <?= e($member['name']) ?>" style="max-width:130px">
                        <option value="active" <?= selected($member['status'], 'active') ?>>Active</option>
                        <option value="disabled" <?= selected($member['status'], 'disabled') ?>>Disabled</option>
                      </select>
                      <button class="btn btn-ghost btn-sm" type="submit">Save</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td><?= status_badge((string) ($member['status'] === 'invited' ? 'pending' : $member['status'])) ?></td>
                <td class="small muted"><?= $member['last_login_at'] ? e(time_ago($member['last_login_at'])) : 'Never' ?></td>
                <td>
                  <div class="table-actions">
                    <?php if ($member['status'] === 'invited'): ?>
                      <form method="post" action="<?= e(app_url('app/staff.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resend">
                        <input type="hidden" name="membership_id" value="<?= (int) $member['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit">Resend invite</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($member['role'] !== 'owner' && !$isSelf && ($isOwner || $member['role'] === 'staff' || is_admin_user($user))): ?>
                      <form method="post" action="<?= e(app_url('app/staff.php')) ?>" data-confirm="Remove <?= e($member['name']) ?> from this business?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="membership_id" value="<?= (int) $member['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" aria-label="Remove <?= e($member['name']) ?>"><?= icon('trash', 'icon icon-sm') ?></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <?php if ($canManage): ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Invite someone</h2></div>
        <div class="card-body">
          <?php if ($seatCount >= $seatLimit): ?>
            <div class="alert alert-warning"><?= icon('alert') ?><div>All <?= e((string) $seatLimit) ?> team seats are in use.</div></div>
            <a class="btn btn-primary btn-block" href="<?= e(app_url('app/billing.php')) ?>">Upgrade for more seats</a>
          <?php else: ?>
            <form method="post" action="<?= e(app_url('app/staff.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="invite">
              <div class="field">
                <label for="invite-email">Email address</label>
                <input type="email" id="invite-email" name="email" required autocomplete="off" placeholder="teammate@example.com">
              </div>
              <div class="field">
                <label for="invite-role">Role</label>
                <select id="invite-role" name="role">
                  <option value="staff">Staff — manage day-to-day records</option>
                  <?php if ($isOwner || is_admin_user($user)): ?><option value="manager">Manager — manage settings and team</option><?php endif; ?>
                </select>
              </div>
              <button class="btn btn-primary btn-block" type="submit"><?= icon('send', 'icon icon-sm') ?> Send invitation</button>
            </form>
            <p class="small muted" style="margin-bottom:0">If they do not have an account, we create one and email a secure link to set a password.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Roles</h2></div>
      <div class="card-body">
        <p class="small"><strong>Owner</strong><br><span class="muted">Full access, billing, domains and deleting the business.</span></p>
        <p class="small"><strong>Manager</strong><br><span class="muted">Website, records, team and business settings. Cannot change billing or the owner.</span></p>
        <p class="small" style="margin-bottom:0"><strong>Staff</strong><br><span class="muted">Day-to-day records. No billing, domains or team management.</span></p>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Security</h2></div>
      <div class="card-body">
        <ul class="check-list">
          <li>Everyone signs in with their own account.</li>
          <li>Access is checked on the server for every business.</li>
          <li>Removing someone does not delete their personal account.</li>
          <li>Disabled members keep their records but lose access.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
