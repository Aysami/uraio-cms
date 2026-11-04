<?php
/**
 * Admin: activity logs and audit trail.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$userFilter = (int) ($_GET['user_id'] ?? 0);
$businessFilter = (int) ($_GET['business_id'] ?? 0);
$fromDate = (string) ($_GET['from'] ?? '');
$toDate = (string) ($_GET['to'] ?? '');

$where = "1=1";
$params = [];
if ($actionFilter !== '') {
    $where .= " AND a.action LIKE ?";
    $params[] = "%$actionFilter%";
}
if ($userFilter > 0) {
    $where .= " AND a.user_id = ?";
    $params[] = $userFilter;
}
if ($businessFilter > 0) {
    $where .= " AND a.business_id = ?";
    $params[] = $businessFilter;
}
if ($fromDate !== '') {
    $where .= " AND DATE(a.created_at) >= ?";
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND DATE(a.created_at) <= ?";
    $params[] = $toDate;
}

$total = (int) db_val("SELECT COUNT(*) FROM activity_logs a WHERE $where", $params);
$pagination = paginate($total, $perPage, $page);
$activities = db_all(
    "SELECT a.*, u.name AS user_name, u.email AS user_email, b.name AS business_name
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN businesses b ON b.id = a.business_id
     WHERE $where
     ORDER BY a.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);

$baseUrl = app_url('admin/activity.php') . '?' . http_build_query(array_filter([
    'action' => $actionFilter,
    'user_id' => $userFilter ?: null,
    'business_id' => $businessFilter ?: null,
    'from' => $fromDate,
    'to' => $toDate,
]));

admin_page_start($user, 'activity', 'Activity logs', 'System-wide audit trail');
?>
<div class="page-head">
  <div>
    <h1>Activity logs</h1>
    <p>Complete audit trail of all significant actions across the platform.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/activity.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="action">Action</label>
        <input type="search" id="action" name="action" value="<?= e($actionFilter) ?>" placeholder="e.g. business.created, payment.verified">
      </div>
      <div class="field">
        <label for="user_id">User</label>
        <input type="number" id="user_id" name="user_id" value="<?= e($userFilter) ?>" min="1" placeholder="User ID">
      </div>
      <div class="field">
        <label for="business_id">Business</label>
        <input type="number" id="business_id" name="business_id" value="<?= e($businessFilter) ?>" min="1" placeholder="Business ID">
      </div>
      <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= e($fromDate) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= e($toDate) ?>">
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?> Filter</button>
      <?php if ($actionFilter !== '' || $userFilter > 0 || $businessFilter > 0 || $fromDate !== '' || $toDate !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/activity.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Time</th><th>Action</th><th>User</th><th>Business</th><th>Entity</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
          <?php foreach ($activities as $a): ?>
            <tr>
              <td class="small muted"><?= e(nice_datetime($a['created_at'])) ?></td>
              <td class="small"><?= e(ucwords(str_replace(['.', '_'], [' · ', ' '], (string) $a['action']))) ?></td>
              <td>
                <?php if ($a['user_name']): ?>
                  <span class="cell-main"><?= e($a['user_name']) ?></span>
                  <span class="cell-sub mono small">#<?= (int) $a['user_id'] ?></span>
                <?php else: ?>
                  <span class="muted">System</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($a['business_name']): ?>
                  <span class="cell-main"><?= e($a['business_name']) ?></span>
                  <span class="cell-sub mono small">#<?= (int) $a['business_id'] ?></span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="mono small">
                <?php if ($a['entity_type']): ?>
                  <?= e($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . $a['entity_id'] : '' ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="mono small"><?= e($a['ip'] ?? '—') ?></td>
              <td class="small muted" style="max-width:300px">
                <?php if ($a['meta']): ?>
                  <details>
                    <summary>View meta</summary>
                    <pre class="small" style="margin:8px 0;background:var(--surface-soft);padding:8px;border-radius:6px;overflow:auto"><?= e(json_encode(json_decode($a['meta'], true), JSON_PRETTY_PRINT)) ?></pre>
                  </details>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pagination['pages'] > 1): ?>
      <div class="card-body"><?= pagination_links($pagination, $baseUrl) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php
admin_page_end(['scripts' => ['app.js']]);