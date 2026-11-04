<?php
/**
 * Admin: user management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');

$where = "u.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter !== '') {
    $where .= " AND r.slug = ?";
    $params[] = $roleFilter;
}

$total = (int) db_val("SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE $where", $params);
$pagination = paginate($total, $perPage, $page);
$users = db_all(
    "SELECT u.*, r.slug AS role_slug, r.name AS role_name
     FROM users u LEFT JOIN roles r ON r.id = u.role_id
     WHERE $where
     ORDER BY u.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);
$roles = db_all("SELECT slug, name FROM roles ORDER BY name ASC");

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $userId = (int) post('user_id', 0);
    $target = $userId > 0 ? db_row('SELECT * FROM users WHERE id = ?', [$userId]) : null;

    if ($action === 'suspend' && $target) {
        if ((int) $target['is_super_admin'] === 1) {
            flash('danger', 'Cannot suspend a super administrator.');
        } else {
            db_update('users', ['status' => 'suspended'], 'id = :id', ['id' => $userId]);
            activity_log('admin.user_suspended', 'user', $userId, [], null);
            flash('success', 'User suspended.');
        }
        redirect('admin/users.php');
    }
    if ($action === 'activate' && $target) {
        db_update('users', ['status' => 'active'], 'id = :id', ['id' => $userId]);
        activity_log('admin.user_activated', 'user', $userId, [], null);
        flash('success', 'User activated.');
        redirect('admin/users.php');
    }
    if ($action === 'role' && $target) {
        $newRole = (string) post('role', '');
        $role = db_row("SELECT id FROM roles WHERE slug = ?", [$newRole]);
        if ($role) {
            db_update('users', ['role_id' => (int) $role['id']], 'id = :id', ['id' => $userId]);
            activity_log('admin.user_role_changed', 'user', $userId, ['role' => $newRole], null);
            flash('success', 'Role updated.');
        } else {
            flash('danger', 'Invalid role.');
        }
        redirect('admin/users.php');
    }
    if ($action === 'delete' && $target) {
        if ((int) $target['is_super_admin'] === 1) {
            flash('danger', 'Cannot delete a super administrator.');
        } elseif ($userId === (int) $user['id']) {
            flash('danger', 'You cannot delete your own account.');
        } else {
            db_update('users', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
            activity_log('admin.user_deleted', 'user', $userId, [], null);
            flash('success', 'User deleted.');
        }
        redirect('admin/users.php');
    }
}

$baseUrl = app_url('admin/users.php') . ($search !== '' || $roleFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'role' => $roleFilter])) : '');

admin_page_start($user, 'users', 'Users', 'Manage platform users');
?>
<div class="page-head">
  <div>
    <h1>Users</h1>
    <p>Registered accounts across the platform.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(app_url('auth/register.php')) ?>"><?= icon('plus', 'icon icon-sm') ?> Add user</a>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/users.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Name or email">
      </div>
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
          <option value="">All roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['slug']) ?>" <?= selected($roleFilter, $r['slug']) ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?> Filter</button>
      <?php if ($search !== '' || $roleFilter !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/users.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
admin_table(
    [
        ['key' => 'name', 'label' => 'User'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'role_slug', 'label' => 'Role'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'created_at', 'label' => 'Joined'],
    ],
    $users,
    [
        'pagination' => $pagination,
        'pagination_url' => $baseUrl,
        'actions' => [
            [
                'label' => 'Edit role',
                'icon' => 'edit',
                'url' => fn ($row) => 'users.php?edit_role=' . $row['id'],
                'condition' => fn ($row) => (int) $row['is_super_admin'] !== 1,
            ],
        ],
    ]
);
?>

<?php if (isset($_GET['edit_role'])): ?>
  <?php $editId = (int) $_GET['edit_role']; $editUser = db_row('SELECT * FROM users WHERE id = ?', [$editId]); ?>
  <?php if ($editUser): ?>
    <div class="modal-backdrop" id="role-modal" role="dialog" aria-modal="true" aria-labelledby="role-modal-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="role-modal-title" class="mt-0">Change role for <?= e($editUser['name']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/users.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/users.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="role">
          <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
          <div class="field">
            <label for="new-role">New role</label>
            <select id="new-role" name="role" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r['slug']) ?>" <?= selected($editUser['role_slug'], $r['slug']) ?>><?= e($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save role</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/users.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
admin_page_end(['scripts' => ['app.js']]);