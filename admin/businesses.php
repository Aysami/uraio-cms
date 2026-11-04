<?php
/**
 * Admin: business management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');

$where = "b.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $where .= " AND (b.name LIKE ? OR b.slug LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where .= " AND b.status = ?";
    $params[] = $statusFilter;
}

$total = (int) db_val("
    SELECT COUNT(*) FROM businesses b JOIN users u ON u.id = b.owner_id WHERE $where
", $params);
$pagination = paginate($total, $perPage, $page);
$businesses = db_all(
    "SELECT b.*, u.name AS owner_name, u.email AS owner_email, c.name AS category_name,
            w.status AS website_status, w.template_id
     FROM businesses b JOIN users u ON u.id = b.owner_id
     LEFT JOIN categories c ON c.id = b.category_id
     LEFT JOIN business_websites w ON w.business_id = b.id
     WHERE $where
     ORDER BY b.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);

$statuses = ['active', 'pending', 'suspended', 'closed'];

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $businessId = (int) post('business_id', 0);
    $business = $businessId > 0 ? db_row('SELECT * FROM businesses WHERE id = ?', [$businessId]) : null;

    if ($action === 'status' && $business) {
        $newStatus = (string) post('status', '');
        if (in_array($newStatus, $statuses, true)) {
            db_update('businesses', ['status' => $newStatus], 'id = :id', ['id' => $businessId]);
            activity_log('admin.business_status_changed', 'business', $businessId, ['status' => $newStatus], null);
            flash('success', 'Business status updated.');
        }
        redirect('admin/businesses.php');
    }
    if ($action === 'delete' && $business) {
        db_update('businesses', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $businessId]);
        activity_log('admin.business_deleted', 'business', $businessId, [], null);
        flash('success', 'Business deleted.');
        redirect('admin/businesses.php');
    }
}

$baseUrl = app_url('admin/businesses.php') . ($search !== '' || $statusFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'status' => $statusFilter])) : '');

admin_page_start($user, 'businesses', 'Businesses', 'Manage registered businesses');
?>
<div class="page-head">
  <div>
    <h1>Businesses</h1>
    <p>All businesses created on the platform.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/businesses.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Business name, slug, owner name or email">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All statuses</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= selected($statusFilter, $s) ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?> Filter</button>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/businesses.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
admin_table(
    [
        ['key' => 'name', 'label' => 'Business'],
        ['key' => 'owner_name', 'label' => 'Owner'],
        ['key' => 'category_name', 'label' => 'Category'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'website_status', 'label' => 'Website'],
        ['key' => 'created_at', 'label' => 'Created'],
    ],
    $businesses,
    [
        'pagination' => $pagination,
        'pagination_url' => $baseUrl,
        'actions' => [
            [
                'label' => 'Change status',
                'icon' => 'edit',
                'url' => fn ($row) => 'businesses.php?edit_status=' . $row['id'],
            ],
            [
                'label' => 'View website',
                'icon' => 'external',
                'url' => fn ($row) => business_site_url($row),
                'condition' => fn ($row) => ($row['website_status'] ?? '') === 'published',
            ],
            [
                'label' => 'Delete',
                'icon' => 'trash',
                'form' => 'businesses.php',
                'confirm' => 'Delete this business and all its data? This cannot be undone.',
                'hidden' => [
                    'action' => 'delete',
                    'business_id' => fn ($row) => $row['id'],
                ],
                'condition' => fn ($row) => ($row['owner_name'] ?? '') !== '', // prevent deleting if no owner
            ],
        ],
    ]
);
?>

<?php if (isset($_GET['edit_status'])): ?>
  <?php $editId = (int) $_GET['edit_status']; $editBiz = db_row('SELECT * FROM businesses WHERE id = ?', [$editId]); ?>
  <?php if ($editBiz): ?>
    <div class="modal-backdrop" id="status-modal" role="dialog" aria-modal="true" aria-labelledby="status-modal-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="status-modal-title" class="mt-0">Change status for <?= e($editBiz['name']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/businesses.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/businesses.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="business_id" value="<?= (int) $editBiz['id'] ?>">
          <div class="field">
            <label for="new-status">New status</label>
            <select id="new-status" name="status" required>
              <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= selected($editBiz['status'], $s) ?>><?= e(ucfirst($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save status</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/businesses.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
admin_page_end(['scripts' => ['app.js']]);