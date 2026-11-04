<?php
/**
 * Analytics: real website traffic and customer activity for the active
 * business. No demo numbers are generated. Advanced breakdowns require a plan
 * that includes advanced analytics.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];

$days = (int) query('days', 30);
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}
$since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
$advanced = business_can('advanced_analytics', $business);

$totals = [
    'visits' => (int) db_val('SELECT COUNT(*) FROM website_visits WHERE business_id = ? AND visited_at >= ?', [$businessId, $since]),
    'visitors' => (int) db_val('SELECT COUNT(DISTINCT ip_hash) FROM website_visits WHERE business_id = ? AND visited_at >= ?', [$businessId, $since]),
    'messages' => (int) db_val('SELECT COUNT(*) FROM messages WHERE business_id = ? AND created_at >= ?', [$businessId, $since]),
    'bookings' => (int) db_val('SELECT COUNT(*) FROM bookings WHERE business_id = ? AND created_at >= ?', [$businessId, $since]),
    'orders' => (int) db_val('SELECT COUNT(*) FROM orders WHERE business_id = ? AND placed_at >= ?', [$businessId, $since]),
    'revenue' => (float) db_val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE business_id = ? AND placed_at >= ? AND payment_status = 'paid'", [$businessId, $since]),
];

$dailyRows = db_all(
    'SELECT DATE(visited_at) AS day, COUNT(*) AS visits FROM website_visits
     WHERE business_id = ? AND visited_at >= ? GROUP BY DATE(visited_at)',
    [$businessId, $since]
);
$byDay = [];
foreach ($dailyRows as $row) {
    $byDay[$row['day']] = (int) $row['visits'];
}
$series = [];
$maxVisits = 1;
for ($i = $days - 1; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime('-' . $i . ' days'));
    $count = $byDay[$key] ?? 0;
    $maxVisits = max($maxVisits, $count);
    $series[] = ['day' => $key, 'visits' => $count];
}

$topPages = [];
$referrers = [];
$devices = ['mobile' => 0, 'desktop' => 0];
if ($advanced) {
    $topPages = db_all(
        'SELECT path, COUNT(*) AS visits, COUNT(DISTINCT ip_hash) AS visitors
         FROM website_visits WHERE business_id = ? AND visited_at >= ?
         GROUP BY path ORDER BY visits DESC LIMIT 10',
        [$businessId, $since]
    );
    $rawReferrers = db_all(
        'SELECT referrer, COUNT(*) AS visits FROM website_visits
         WHERE business_id = ? AND visited_at >= ? GROUP BY referrer ORDER BY visits DESC LIMIT 200',
        [$businessId, $since]
    );
    foreach ($rawReferrers as $row) {
        $host = $row['referrer'] ? parse_url((string) $row['referrer'], PHP_URL_HOST) : null;
        $key = $host ? preg_replace('/^www\./', '', strtolower($host)) : 'Direct / none';
        $referrers[$key] = ($referrers[$key] ?? 0) + (int) $row['visits'];
    }
    arsort($referrers);
    $referrers = array_slice($referrers, 0, 8, true);

    $deviceRow = db_row(
        "SELECT
            SUM(CASE WHEN user_agent REGEXP 'Mobile|Android|iPhone|iPad' THEN 1 ELSE 0 END) AS mobile,
            SUM(CASE WHEN user_agent REGEXP 'Mobile|Android|iPhone|iPad' THEN 0 ELSE 1 END) AS desktop
         FROM website_visits WHERE business_id = ? AND visited_at >= ?",
        [$businessId, $since]
    );
    $devices = [
        'mobile' => (int) ($deviceRow['mobile'] ?? 0),
        'desktop' => (int) ($deviceRow['desktop'] ?? 0),
    ];
}

$recentMessages = db_all(
    'SELECT * FROM messages WHERE business_id = ? ORDER BY id DESC LIMIT 5',
    [$businessId]
);
$recentBookings = db_all(
    'SELECT * FROM bookings WHERE business_id = ? ORDER BY id DESC LIMIT 5',
    [$businessId]
);
$website = business_website($businessId);

layout_app_start($business, 'analytics', 'Analytics', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Analytics</h1>
    <p>How people find and use <?= e($business['name']) ?>. Figures are recorded from real visits and submissions.</p>
  </div>
  <div class="page-actions">
    <div class="btn-group">
      <a class="btn btn-ghost btn-sm<?= $days === 7 ? ' is-active' : '' ?>" href="<?= e(app_url('app/analytics.php?days=7')) ?>">7 days</a>
      <a class="btn btn-ghost btn-sm<?= $days === 30 ? ' is-active' : '' ?>" href="<?= e(app_url('app/analytics.php?days=30')) ?>">30 days</a>
      <a class="btn btn-ghost btn-sm<?= $days === 90 ? ' is-active' : '' ?>" href="<?= e(app_url('app/analytics.php?days=90')) ?>">90 days</a>
    </div>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if ($website && $website['status'] !== 'published'): ?>
  <div class="alert alert-warning"><?= icon('alert') ?><div>Your website is not published, so public traffic is paused. <a class="link" href="<?= e(app_url('app/website.php')) ?>">Publish it</a> to start collecting visits.</div></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon"><?= icon('eye') ?></div><div class="stat-label">Website visits</div><div class="stat-value"><?= e(number_format($totals['visits'])) ?></div><div class="stat-note">Last <?= e((string) $days) ?> days</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><div class="stat-label">Unique visitors</div><div class="stat-value"><?= e(number_format($totals['visitors'])) ?></div><div class="stat-note">Based on anonymised IP hashes</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('mail') ?></div><div class="stat-label">Messages</div><div class="stat-value"><?= e(number_format($totals['messages'])) ?></div><div class="stat-note">Contact form submissions</div></div>
  <div class="stat-card"><div class="stat-icon"><?= icon('calendar') ?></div><div class="stat-label">Bookings</div><div class="stat-value"><?= e(number_format($totals['bookings'])) ?></div><div class="stat-note">Requests received</div></div>
</div>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Visits — <?= e((string) $days) ?> days</h2>
          <p class="small muted" style="margin:4px 0 0">Each bar is a day. Visits are logged on published pages only.</p>
        </div>
      </div>
      <div class="card-body">
        <?php if ($totals['visits'] === 0): ?>
          <div class="empty-state" style="padding:26px 10px">
            <div class="empty-icon"><?= icon('chart') ?></div>
            <h3>No visits yet</h3>
            <p>Share your website link or add it to your social profiles. Visits will appear here as they happen.</p>
            <a class="btn btn-soft btn-sm" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> Open website</a>
          </div>
        <?php else: ?>
          <div class="flex" style="align-items:flex-end;gap:<?= $days > 30 ? '2px' : '4px' ?>;height:170px">
            <?php foreach ($series as $point): ?>
              <div style="flex:1;text-align:center;min-width:0">
                <div title="<?= e($point['day']) ?>: <?= (int) $point['visits'] ?> visits" style="height:<?= max(3, (int) round(($point['visits'] / $maxVisits) * 130)) ?>px;background:var(--brand-soft);border-radius:4px 4px 2px 2px"></div>
                <?php if ($days <= 30 || (int) date('d', strtotime($point['day'])) === 1): ?>
                  <div class="muted" style="font-size:.6rem;margin-top:4px"><?= e(date('j', strtotime($point['day']))) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($advanced): ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Top pages</h2></div>
        <?php if (!$topPages): ?>
          <div class="card-body"><p class="small muted" style="margin:0">No page views recorded in this period.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Page</th><th class="text-right">Visits</th><th class="text-right">Visitors</th></tr></thead>
              <tbody>
                <?php foreach ($topPages as $page): ?>
                  <tr>
                    <td class="mono small"><?= e($page['path']) ?></td>
                    <td class="text-right strong"><?= e(number_format((int) $page['visits'])) ?></td>
                    <td class="text-right"><?= e(number_format((int) $page['visitors'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Business activity</h2></div>
      <div class="card-body">
        <div class="flex-between mb-2"><span class="small muted">Orders placed</span><span class="strong"><?= e(number_format($totals['orders'])) ?></span></div>
        <div class="flex-between mb-2"><span class="small muted">Paid revenue</span><span class="strong"><?= e(money($totals['revenue'], (string) $business['currency'])) ?></span></div>
        <div class="flex-between mb-2"><span class="small muted">Bookings</span><span class="strong"><?= e(number_format($totals['bookings'])) ?></span></div>
        <div class="flex-between"><span class="small muted">Messages</span><span class="strong"><?= e(number_format($totals['messages'])) ?></span></div>
      </div>
    </div>

    <?php if ($advanced): ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Where visitors came from</h2></div>
        <div class="card-body">
          <?php if (!$referrers): ?>
            <p class="small muted" style="margin:0">No referrer data yet.</p>
          <?php else: ?>
            <?php $maxRef = max($referrers); ?>
            <?php foreach ($referrers as $host => $count): ?>
              <div class="mb-2">
                <div class="flex-between small"><span class="truncate"><?= e($host) ?></span><span class="strong"><?= e(number_format($count)) ?></span></div>
                <div style="height:6px;background:var(--line);border-radius:4px;margin-top:4px"><div style="height:6px;width:<?= max(4, (int) round(($count / $maxRef) * 100)) ?>%;background:var(--brand);border-radius:4px"></div></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Devices</h2></div>
        <div class="card-body">
          <?php $deviceTotal = max(1, $devices['mobile'] + $devices['desktop']); ?>
          <div class="flex-between mb-2"><span class="small muted">Mobile</span><span class="strong"><?= e(number_format($devices['mobile'])) ?> · <?= e((string) round(($devices['mobile'] / $deviceTotal) * 100)) ?>%</span></div>
          <div class="flex-between"><span class="small muted">Desktop / tablet</span><span class="strong"><?= e(number_format($devices['desktop'])) ?> · <?= e((string) round(($devices['desktop'] / $deviceTotal) * 100)) ?>%</span></div>
        </div>
      </div>
    <?php else: ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Advanced analytics</h2></div>
        <div class="card-body">
          <p class="small muted">See top pages, traffic sources and device breakdowns on a plan with advanced analytics.</p>
          <a class="btn btn-primary btn-block" href="<?= e(app_url('app/billing.php')) ?>"><?= icon('chart', 'icon icon-sm') ?> Upgrade plan</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Latest enquiries</h2></div>
      <div class="card-body">
        <?php if (!$recentMessages): ?>
          <p class="small muted" style="margin:0">No messages yet. Contact form submissions appear here.</p>
        <?php else: ?>
          <?php foreach ($recentMessages as $message): ?>
            <div class="mb-2">
              <div class="flex-between"><span class="strong small truncate"><?= e($message['name']) ?></span><?= status_badge((string) $message['status']) ?></div>
              <div class="small muted"><?= e(str_limit($message['subject'] ?: $message['body'], 70)) ?></div>
              <div class="small muted"><?= e(time_ago($message['created_at'])) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
