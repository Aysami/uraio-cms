<?php
/**
 * Category-aware business dashboard. Every number is read from the tenant's
 * own records — no demo or hardcoded statistics.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();

$widgets = module_widgets($business);
$modules = enabled_modules($business);
$website = business_website((int) $business['id']);
$subscription = subscription_summary((int) $business['id']);
$plan = $subscription['plan'];
$entities = entity_definitions($business);

$customerCount = isset($entities['customers']) ? entity_count($business, 'customers') : 0;
$productCount = isset($entities['products']) ? entity_count($business, 'products') : 0;
$orderCount = isset($entities['orders']) ? entity_count($business, 'orders') : 0;
$bookingCount = isset($entities['bookings']) ? entity_count($business, 'bookings') : 0;

$recentOrders = [];
if (isset($entities['orders'])) {
    $recentOrders = db_all(
        'SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
         WHERE o.business_id = ? ORDER BY o.id DESC LIMIT 6',
        [$business['id']]
    );
}
$upcomingBookings = [];
if (isset($entities['bookings'])) {
    $upcomingBookings = db_all(
        "SELECT * FROM bookings WHERE business_id = ? AND booking_date >= CURDATE() AND status NOT IN ('cancelled','no_show') ORDER BY booking_date ASC, start_time ASC LIMIT 5",
        [$business['id']]
    );
}
$recentActivity = db_all(
    'SELECT * FROM activity_logs WHERE business_id = ? ORDER BY id DESC LIMIT 8',
    [$business['id']]
);

/* Last 7 days of orders, zero-filled. */
$days = [];
$ordersByDay = [];
try {
    $rows = db_all(
        'SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue
         FROM orders WHERE business_id = ? AND created_at >= (CURDATE() - INTERVAL 6 DAY)
         GROUP BY DATE(created_at)',
        [$business['id']]
    );
    foreach ($rows as $row) {
        $ordersByDay[$row['day']] = $row;
    }
} catch (Throwable $e) {
}
$maxOrders = 1;
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $row = $ordersByDay[$key] ?? ['day' => $key, 'orders' => 0, 'revenue' => 0];
    $maxOrders = max($maxOrders, (int) $row['orders']);
    $days[] = $row;
}

$checklist = [
    ['label' => 'Verify your email', 'done' => !empty($user['email_verified_at']), 'url' => 'app/profile.php'],
    ['label' => 'Add your first customer', 'done' => $customerCount > 0, 'url' => 'app/records.php?entity=customers&action=new'],
    ['label' => 'Add a product or service', 'done' => $productCount > 0, 'url' => 'app/records.php?entity=products&action=new'],
    ['label' => 'Publish your website', 'done' => ($website['status'] ?? '') === 'published', 'url' => 'app/website.php'],
];
$doneCount = count(array_filter(array_column($checklist, 'done')));
$progress = (int) round(($doneCount / max(1, count($checklist))) * 100);

layout_app_start($business, 'overview', 'Overview', $business['name']);
?>
<?php if (empty($user['email_verified_at'])): ?>
  <div class="alert alert-warning"><?= icon('alert') ?><div><strong>Verify your email.</strong> We sent a link to <?= e($user['email']) ?>. <a class="link" href="<?= e(app_url('app/profile.php')) ?>">Resend verification</a></div></div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>Hello, <?= e(explode(' ', (string) $user['name'])[0]) ?> 👋</h1>
    <p>Here is what is happening at <strong><?= e($business['name']) ?></strong> today.</p>
  </div>
  <div class="page-actions">
    <?php if ($website && $website['status'] === 'published'): ?>
      <a class="btn btn-ghost" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View website</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(app_url('app/website.php')) ?>"><?= icon('globe', 'icon icon-sm') ?> Publish website</a>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(app_url('app/records.php?entity=orders&action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New order</a>
  </div>
</div>

<div class="stat-grid">
  <?php foreach (array_slice($widgets, 0, 4) as $widget): ?>
    <?php $value = widget_value($business, $widget); ?>
    <div class="stat-card">
      <div class="stat-icon"><?= icon($widget['icon'] ?? 'grid') ?></div>
      <div class="stat-label"><?= e($widget['label']) ?></div>
      <div class="stat-value"><?= e(format_widget_value($widget, $value, (string) $business['currency'])) ?></div>
      <div class="stat-note"><?= e($business['name']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$widgets): ?>
    <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><div class="stat-label">Customers</div><div class="stat-value"><?= e(number_format($customerCount)) ?></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('shopping-bag') ?></div><div class="stat-label">Products</div><div class="stat-value"><?= e(number_format($productCount)) ?></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('shopping-cart') ?></div><div class="stat-label">Orders</div><div class="stat-value"><?= e(number_format($orderCount)) ?></div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('calendar') ?></div><div class="stat-label">Bookings</div><div class="stat-value"><?= e(number_format($bookingCount)) ?></div></div>
  <?php endif; ?>
</div>

<?php if ($progress < 100): ?>
  <div class="card mb-2">
    <div class="card-head">
      <div>
        <h2 class="mt-0"><?= icon('sparkles', 'icon icon-sm') ?> Finish setting up</h2>
        <p class="small muted" style="margin:4px 0 0"><?= $doneCount ?> of <?= count($checklist) ?> complete — <?= $progress ?>%</p>
      </div>
      <span class="badge badge-info"><?= $progress ?>%</span>
    </div>
    <div class="card-body">
      <div style="height:7px;border-radius:6px;background:var(--surface-3);overflow:hidden;margin-bottom:16px">
        <div style="height:100%;width:<?= $progress ?>%;background:var(--brand);border-radius:6px"></div>
      </div>
      <div class="grid grid-4">
        <?php foreach ($checklist as $item): ?>
          <a class="flex" href="<?= e(app_url($item['url'])) ?>" style="color:inherit;padding:10px;border:1px solid var(--line);border-radius:10px;<?= $item['done'] ? 'opacity:.7' : '' ?>">
            <span class="avatar" style="<?= $item['done'] ? 'background:var(--success-soft);color:var(--success)' : '' ?>"><?= $item['done'] ? '✓' : '→' ?></span>
            <span class="small strong"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <h2 class="mt-0">Orders — last 7 days</h2>
        <a class="small link" href="<?= e(app_url('app/records.php?entity=orders')) ?>">All orders</a>
      </div>
      <div class="card-body">
        <?php if (array_sum(array_column($days, 'orders')) === 0): ?>
          <div class="empty-state" style="padding:28px 10px">
            <div class="empty-icon"><?= icon('chart') ?></div>
            <h3>No orders yet</h3>
            <p>Orders you create or receive from your website will appear here.</p>
            <a class="btn btn-primary btn-sm" href="<?= e(app_url('app/records.php?entity=orders&action=new')) ?>">Create an order</a>
          </div>
        <?php else: ?>
          <div class="flex" style="align-items:flex-end;gap:10px;height:170px;padding:8px 0">
            <?php foreach ($days as $day): ?>
              <?php $height = max(4, (int) round(((int) $day['orders'] / $maxOrders) * 120)); ?>
              <div style="flex:1;text-align:center">
                <div style="font-size:.7rem;color:var(--muted);margin-bottom:5px"><?= (int) $day['orders'] ?></div>
                <div title="<?= e($day['day']) ?>: <?= (int) $day['orders'] ?> orders" style="height:<?= $height ?>px;background:linear-gradient(180deg,var(--brand),var(--brand-dark));border-radius:6px 6px 2px 2px;min-height:4px"></div>
                <div class="small muted" style="margin-top:6px"><?= e(date('D', strtotime($day['day']))) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h2 class="mt-0">Recent orders</h2>
        <a class="small link" href="<?= e(app_url('app/records.php?entity=orders')) ?>">View all</a>
      </div>
      <?php if ($recentOrders): ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td><a class="cell-main" href="<?= e(app_url('app/records.php?entity=orders&action=edit&id=' . $order['id'])) ?>"><?= e($order['number'] ?: '#' . $order['id']) ?></a><span class="cell-sub"><?= e(nice_date($order['created_at'])) ?></span></td>
                  <td><?= e($order['customer_name'] ?: '—') ?></td>
                  <td><?= status_badge((string) $order['status']) ?></td>
                  <td class="text-right strong"><?= e(money((float) $order['total'], (string) $business['currency'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding:28px"><div class="empty-icon"><?= icon('shopping-cart') ?></div><h3>No orders yet</h3><p>Your latest orders will show up here.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Website</h2><?= status_badge($website['status'] ?? 'draft') ?></div>
      <div class="card-body">
        <?php if ($website): ?>
          <p class="small muted"><?= e($website['template_name'] ?? 'Aurora') ?> template · <?= (int) website_stats((int) $business['id'])['pages'] ?> pages</p>
          <div class="mono small truncate" style="background:var(--surface-2);padding:8px 10px;border-radius:8px;margin-bottom:12px"><?= e(business_public_url($business)) ?></div>
          <div class="flex" style="flex-wrap:wrap">
            <a class="btn btn-primary btn-sm" href="<?= e(app_url('app/editor.php')) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit website</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener">Preview</a>
          </div>
        <?php else: ?>
          <p class="small muted">You do not have a website yet.</p>
          <a class="btn btn-primary btn-sm" href="<?= e(app_url('app/website.php')) ?>">Create website</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Upcoming bookings</h2><a class="small link" href="<?= e(app_url('app/records.php?entity=bookings')) ?>">All</a></div>
      <div class="card-body">
        <?php if ($upcomingBookings): ?>
          <div class="side-nav">
            <?php foreach ($upcomingBookings as $booking): ?>
              <a class="side-link" href="<?= e(app_url('app/records.php?entity=bookings&action=edit&id=' . $booking['id'])) ?>">
                <span class="avatar"><?= e(date('d', strtotime((string) $booking['booking_date']))) ?></span>
                <span style="min-width:0"><strong class="truncate" style="display:block"><?= e($booking['customer_name']) ?></strong><span class="muted small"><?= e(nice_date($booking['booking_date'])) ?><?= $booking['start_time'] ? ' · ' . e(date('g:ia', strtotime((string) $booking['start_time']))) : '' ?></span></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="small muted">No upcoming bookings.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Plan</h2><a class="small link" href="<?= e(app_url('app/billing.php')) ?>">Manage</a></div>
      <div class="card-body">
        <div class="flex-between"><strong><?= e($plan['name'] ?? 'Free') ?></strong><span class="badge badge-<?= $subscription['active'] ? 'success' : 'warning' ?>"><?= e(ucfirst($subscription['subscription']['status'] ?? 'active')) ?></span></div>
        <p class="small muted mt-1"><?= (int) ($subscription['limits']['businesses'] ?? 1) ?> business(es) · <?= (int) ($subscription['limits']['staff'] ?? 2) ?> staff · <?= (int) ($subscription['limits']['products'] ?? 25) ?> products</p>
        <?php if ((float) ($plan['price_monthly'] ?? 0) <= 0): ?>
          <a class="btn btn-soft btn-sm btn-block" href="<?= e(app_url('app/billing.php')) ?>">Upgrade plan</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="section-title"><h2>Your modules</h2><a class="small link" href="<?= e(app_url('app/settings.php#modules')) ?>">Manage modules</a></div>
<div class="grid grid-4">
  <?php foreach ($modules as $slug => $module): ?>
    <?php $firstNav = $module['nav'][0] ?? null; ?>
    <a class="card card-pad" href="<?= e(app_url($firstNav['url'] ?? 'app/dashboard.php')) ?>" style="color:inherit">
      <div class="stat-icon"><?= icon($module['icon'] ?? 'grid') ?></div>
      <strong style="display:block;font-size:.9rem"><?= e($module['name']) ?></strong>
      <span class="small muted"><?= count($module['entities'] ?? []) ?> record types</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="section-title"><h2>Recent activity</h2></div>
<div class="card">
  <div class="card-body">
    <?php if ($recentActivity): ?>
      <div class="side-nav">
        <?php foreach ($recentActivity as $log): ?>
          <div class="side-link" style="cursor:default">
            <span class="avatar"><?= icon('clock', 'icon icon-sm') ?></span>
            <span style="min-width:0"><strong class="truncate" style="display:block"><?= e(ucwords(str_replace(['.', '_'], [' · ', ' '], (string) $log['action']))) ?></strong><span class="muted small"><?= e(time_ago($log['created_at'])) ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="small muted">Activity will appear here as you use the platform.</p>
    <?php endif; ?>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
