<?php
/**
 * Admin: subscription management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');

$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (b.name LIKE ? OR b.slug LIKE ? OR p.name LIKE ? OR p.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$total = (int) db_val("
    SELECT COUNT(*) FROM subscriptions s
    JOIN businesses b ON b.id = s.business_id
    JOIN subscription_plans p ON p.id = s.plan_id
    WHERE $where
", $params);
$pagination = paginate($total, $perPage, $page);
$subscriptions = db_all(
    "SELECT s.*, b.name AS business_name, b.slug AS business_slug, u.name AS owner_name,
            p.name AS plan_name, p.slug AS plan_slug
     FROM subscriptions s
     JOIN businesses b ON b.id = s.business_id
     JOIN users u ON u.id = b.owner_id
     JOIN subscription_plans p ON p.id = s.plan_id
     WHERE $where
     ORDER BY s.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);

$plans = subscription_plans(false);
$statuses = ['trialing', 'active', 'past_due', 'canceled', 'expired'];

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $subId = (int) post('subscription_id', 0);
    $sub = $subId > 0 ? db_row('SELECT * FROM subscriptions WHERE id = ?', [$subId]) : null;

    if ($action === 'status' && $sub) {
        $newStatus = (string) post('status', '');
        if (in_array($newStatus, $statuses, true)) {
            db_update('subscriptions', ['status' => $newStatus, 'canceled_at' => $newStatus === 'canceled' ? date('Y-m-d H:i:s') : null], 'id = :id', ['id' => $subId]);
            activity_log('admin.subscription_status_changed', 'subscription', $subId, ['status' => $newStatus], null);
            flash('success', 'Subscription status updated.');
        }
        redirect('admin/subscriptions.php');
    }
    if ($action === 'plan' && $sub) {
        $planId = (int) post('plan_id', 0);
        $plan = subscription_plan($planId);
        if ($plan) {
            $cycle = (string) post('cycle', 'monthly');
            db_update('subscriptions', ['plan_id' => $planId, 'billing_cycle' => $cycle, 'amount' => plan_price($plan, $cycle), 'currency' => $plan['currency']], 'id = :id', ['id' => $subId]);
            activity_log('admin.subscription_plan_changed', 'subscription', $subId, ['plan' => $plan['slug'], 'cycle' => $cycle], null);
            flash('success', 'Subscription plan changed.');
        }
        redirect('admin/subscriptions.php');
    }
    if ($action === 'extend' && $sub) {
        $months = max(1, (int) post('months', 1));
        $newEnd = date('Y-m-d H:i:s', strtotime('+' . $months . ' months', strtotime($sub['ends_at'] ?: 'now')));
        db_update('subscriptions', ['ends_at' => $newEnd, 'status' => 'active', 'canceled_at' => null], 'id = :id', ['id' => $subId]);
        activity_log('admin.subscription_extended', 'subscription', $subId, ['months' => $months], null);
        flash('success', 'Subscription extended by ' . $months . ' month(s).');
        redirect('admin/subscriptions.php');
    }
}

$baseUrl = app_url('admin/subscriptions.php') . ($search !== '' || $statusFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'status' => $statusFilter])) : '');

admin_page_start($user, 'subscriptions', 'Subscriptions', 'Manage business subscriptions');
?>
<div class="page-head">
  <div>
    <h1>Subscriptions</h1>
    <p>Active, trial and past subscriptions across all businesses.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/subscriptions.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Business, plan or owner">
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
        <a class="btn btn-ghost" href="<?= e(app_url('admin/subscriptions.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
admin_table(
    [
        ['key' => 'business_name', 'label' => 'Business'],
        ['key' => 'owner_name', 'label' => 'Owner'],
        ['key' => 'plan_name', 'label' => 'Plan'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'billing_cycle', 'label' => 'Cycle'],
        ['key' => 'amount', 'label' => 'Amount', 'align' => 'text-right', 'render' => fn ($v, $r) => money((float) $v, (string) $r['currency'])],
        ['key' => 'ends_at', 'label' => 'Ends'],
    ],
    $subscriptions,
    [
        'pagination' => $pagination,
        'pagination_url' => $baseUrl,
        'actions' => [
            [
                'label' => 'Change status',
                'icon' => 'edit',
                'url' => fn ($row) => 'subscriptions.php?edit_status=' . $row['id'],
            ],
            [
                'label' => 'Change plan',
                'icon' => 'credit-card',
                'url' => fn ($row) => 'subscriptions.php?edit_plan=' . $row['id'],
            ],
            [
                'label' => 'Extend',
                'icon' => 'calendar',
                'url' => fn ($row) => 'subscriptions.php?extend=' . $row['id'],
            ],
        ],
    ]
);
?>

<?php if (isset($_GET['edit_status'])): ?>
  <?php $editId = (int) $_GET['edit_status']; $editSub = db_row('SELECT * FROM subscriptions WHERE id = ?', [$editId]); ?>
  <?php if ($editSub): ?>
    <div class="modal-backdrop" id="sub-status-modal" role="dialog" aria-modal="true" aria-labelledby="sub-status-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="sub-status-title" class="mt-0">Change status for <?= e($editSub['business_name'] ?? 'subscription #' . $editSub['id']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/subscriptions.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/subscriptions.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="subscription_id" value="<?= (int) $editSub['id'] ?>">
          <div class="field">
            <label for="new-status">New status</label>
            <select id="new-status" name="status" required>
              <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= selected($editSub['status'], $s) ?>><?= e(ucfirst($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save status</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/subscriptions.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['edit_plan'])): ?>
  <?php $editId = (int) $_GET['edit_plan']; $editSub = db_row('SELECT * FROM subscriptions WHERE id = ?', [$editId]); ?>
  <?php if ($editSub): ?>
    <div class="modal-backdrop" id="sub-plan-modal" role="dialog" aria-modal="true" aria-labelledby="sub-plan-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="sub-plan-title" class="mt-0">Change plan for <?= e($editSub['business_name'] ?? 'subscription #' . $editSub['id']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/subscriptions.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/subscriptions.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="plan">
          <input type="hidden" name="subscription_id" value="<?= (int) $editSub['id'] ?>">
          <div class="form-grid">
            <div class="field">
              <label for="new-plan">Plan</label>
              <select id="new-plan" name="plan_id" required>
                <?php foreach ($plans as $p): ?>
                  <option value="<?= (int) $p['id'] ?>" <?= selected($editSub['plan_id'], $p['id']) ?>><?= e($p['name']) ?> (<?= e($p['slug']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="new-cycle">Billing cycle</label>
              <select id="new-cycle" name="cycle">
                <option value="monthly" <?= selected($editSub['billing_cycle'], 'monthly') ?>>Monthly</option>
                <option value="quarterly" <?= selected($editSub['billing_cycle'], 'quarterly') ?>>Quarterly</option>
                <option value="annual" <?= selected($editSub['billing_cycle'], 'annual') ?>>Annual</option>
              </select>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save plan</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/subscriptions.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['extend'])): ?>
  <?php $editId = (int) $_GET['extend']; $editSub = db_row('SELECT * FROM subscriptions WHERE id = ?', [$editId]); ?>
  <?php if ($editSub): ?>
    <div class="modal-backdrop" id="sub-extend-modal" role="dialog" aria-modal="true" aria-labelledby="sub-extend-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="sub-extend-title" class="mt-0">Extend subscription for <?= e($editSub['business_name'] ?? 'subscription #' . $editSub['id']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/subscriptions.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/subscriptions.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="extend">
          <input type="hidden" name="subscription_id" value="<?= (int) $editSub['id'] ?>">
          <div class="field">
            <label for="months">Months to add</label>
            <input type="number" id="months" name="months" value="1" min="1" max="24" required>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Extend</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/subscriptions.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
admin_page_end(['scripts' => ['app.js']]);