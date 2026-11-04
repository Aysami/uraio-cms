<?php
/**
 * Admin dashboard: key metrics and recent activity.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$stats = [
    'users' => (int) db_val("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL"),
    'businesses' => (int) db_val("SELECT COUNT(*) FROM businesses WHERE deleted_at IS NULL"),
    'active_businesses' => (int) db_val("SELECT COUNT(*) FROM businesses WHERE status = 'active' AND deleted_at IS NULL"),
    'published_websites' => (int) db_val("SELECT COUNT(*) FROM business_websites WHERE status = 'published'"),
    'categories' => (int) db_val("SELECT COUNT(*) FROM categories WHERE status = 'active'"),
    'subscriptions' => (int) db_val("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"),
    'mrr' => (float) db_val("
        SELECT COALESCE(SUM(CASE
            WHEN billing_cycle = 'annual' THEN amount / 12
            WHEN billing_cycle = 'quarterly' THEN amount / 3
            ELSE amount
        END), 0)
        FROM subscriptions WHERE status IN ('active','trialing')
    "),
    'pending_payments' => (int) db_val("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
    'domains' => (int) db_val("SELECT COUNT(*) FROM website_domains WHERE status = 'verified'"),
];

$recentUsers = db_all("SELECT u.id, u.name, u.email, r.slug AS role_slug, u.created_at FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 5");
$recentBusinesses = db_all("SELECT b.id, b.name, b.slug, b.status, u.name AS owner_name, b.created_at FROM businesses b JOIN users u ON u.id = b.owner_id WHERE b.deleted_at IS NULL ORDER BY b.id DESC LIMIT 5");
$recentPayments = db_all("SELECT p.*, u.name AS user_name, b.name AS business_name FROM payments p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN businesses b ON b.id = p.business_id ORDER BY p.id DESC LIMIT 5");
$recentActivity = db_all("SELECT a.*, u.name AS user_name, b.name AS business_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN businesses b ON b.id = a.business_id ORDER BY a.id DESC LIMIT 10");

admin_page_start($user, 'dashboard', 'Dashboard', 'Platform overview');
?>
<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><div class="stat-label">Total users</div><div class="stat-value"><?= e(number_format($stats['users'])) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('briefcase') ?></div><div class="stat-label">Businesses</div><div class="stat-value"><?= e(number_format($stats['businesses'])) ?></div><div class="stat-note"><?= e(number_format($stats['active_businesses'])) ?> active</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('globe') ?></div><div class="stat-label">Published websites</div><div class="stat-value"><?= e(number_format($stats['published_websites'])) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('tag') ?></div><div class="stat-label">Categories</div><div class="stat-value"><?= e(number_format($stats['categories'])) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('credit-card') ?></div><div class="stat-label">Active subscriptions</div><div class="stat-value"><?= e(number_format($stats['subscriptions'])) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('dollar') ?></div><div class="stat-label">MRR</div><div class="stat-value" style="font-size:1.3rem"><?= e(money($stats['mrr'], 'NGN')) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('alert') ?></div><div class="stat-label">Pending payments</div><div class="stat-value"><?= e(number_format($stats['pending_payments'])) ?></div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('link') ?></div><div class="stat-label">Verified domains</div><div class="stat-value"><?= e(number_format($stats['domains'])) ?></div></div>
</div>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Recent users</h2><a class="small link" href="<?= e(app_url('admin/users.php')) ?>">View all</a></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($recentUsers as $u): ?>
              <tr>
                <td><span class="cell-main"><?= e($u['name']) ?></span><span class="cell-sub mono"><?= e($u['email']) ?></span></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $u['role_slug'] ?? 'User'))) ?></td>
                <td class="small muted"><?= e(time_ago($u['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Recent businesses</h2><a class="small link" href="<?= e(app_url('admin/businesses.php')) ?>">View all</a></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Business</th><th>Owner</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($recentBusinesses as $b): ?>
              <tr>
                <td><span class="cell-main"><?= e($b['name']) ?></span><span class="cell-sub mono"><?= e($b['slug']) ?></span></td>
                <td><?= e($b['owner_name']) ?></td>
                <td><?= status_badge((string) $b['status']) ?></td>
                <td class="small muted"><?= e(time_ago($b['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Recent payments</h2><a class="small link" href="<?= e(app_url('admin/payments.php')) ?>">View all</a></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Reference</th><th>Business</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentPayments as $p): ?>
              <tr>
                <td class="mono small"><?= e($p['reference']) ?></td>
                <td><?= e($p['business_name'] ?? '—') ?></td>
                <td><?= e(ucfirst($p['provider'])) ?></td>
                <td class="strong"><?= e(money((float) $p['amount'], (string) $p['currency'])) ?></td>
                <td><?= status_badge((string) $p['status']) ?></td>
                <td class="small muted"><?= e(time_ago($p['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Recent activity</h2></div>
      <div class="card-body">
        <?php if (!$recentActivity): ?>
          <p class="small muted" style="margin:0">No activity recorded yet.</p>
        <?php else: ?>
          <?php foreach ($recentActivity as $a): ?>
            <div class="mb-2">
              <div class="flex-between">
                <span class="strong small"><?= e(ucwords(str_replace(['.', '_'], [' · ', ' '], (string) $a['action']))) ?></span>
                <span class="small muted"><?= e(time_ago($a['created_at'])) ?></span>
              </div>
              <div class="small muted">
                <?= $a['user_name'] ? e($a['user_name']) : 'System' ?>
                <?= $a['business_name'] ? ' · ' . e($a['business_name']) : '' ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
admin_page_end(['scripts' => ['app.js']]);