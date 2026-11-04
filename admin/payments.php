<?php
/**
 * Admin: payment management and verification.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
require_once BASE_PATH . '/includes/payment.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$providerFilter = (string) ($_GET['provider'] ?? '');

$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (p.reference LIKE ? OR b.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}
if ($providerFilter !== '') {
    $where .= " AND p.provider = ?";
    $params[] = $providerFilter;
}

$total = (int) db_val("
    SELECT COUNT(*) FROM payments p
    LEFT JOIN businesses b ON b.id = p.business_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE $where
", $params);
$pagination = paginate($total, $perPage, $page);
$payments = db_all(
    "SELECT p.*, b.name AS business_name, b.slug AS business_slug, u.name AS user_name, u.email AS user_email
     FROM payments p
     LEFT JOIN businesses b ON b.id = p.business_id
     LEFT JOIN users u ON u.id = p.user_id
     WHERE $where
     ORDER BY p.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);

$providerLabels = ['paystack' => 'Paystack', 'flutterwave' => 'Flutterwave', 'manual' => 'Manual'];

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $paymentId = (int) post('payment_id', 0);
    $payment = $paymentId > 0 ? db_row('SELECT * FROM payments WHERE id = ?', [$paymentId]) : null;

    if ($action === 'verify' && $payment) {
        if (!in_array($payment['provider'], ['paystack', 'flutterwave'], true)) {
            flash('danger', 'This payment cannot be verified automatically.');
        } else {
            $verification = verify_gateway_payment($payment['provider'], $payment['reference']);
            if (!$verification['ok']) {
                mark_payment_failed($paymentId, (string) ($verification['error'] ?? 'Verification failed'));
                flash('danger', 'Verification failed: ' . ($verification['error'] ?? 'Unknown error'));
            } elseif (abs((float) $verification['amount'] - (float) $payment['amount']) > 0.01) {
                mark_payment_failed($paymentId, 'Amount mismatch');
                flash('danger', 'Amount mismatch between gateway and record.');
            } elseif (fulfill_payment($paymentId)) {
                flash('success', 'Payment verified and fulfilled.');
            } else {
                flash('danger', 'Verification succeeded but fulfillment failed.');
            }
        }
        redirect('admin/payments.php');
    }

    if ($action === 'manual_fulfill' && $payment) {
        if ($payment['status'] !== 'pending') {
            flash('warning', 'Payment is not pending.');
        } elseif (fulfill_payment($paymentId)) {
            flash('success', 'Manual payment fulfilled and subscription activated.');
        } else {
            flash('danger', 'Fulfillment failed.');
        }
        redirect('admin/payments.php');
    }

    if ($action === 'mark_failed' && $payment) {
        $reason = trim((string) post('reason', ''));
        mark_payment_failed($paymentId, $reason);
        flash('success', 'Payment marked as failed.');
        redirect('admin/payments.php');
    }

    if ($action === 'refund' && $payment) {
        if ($payment['status'] !== 'successful') {
            flash('danger', 'Only successful payments can be refunded.');
        } else {
            db_update('payments', ['status' => 'refunded'], 'id = :id', ['id' => $paymentId]);
            activity_log('admin.payment_refunded', 'payment', $paymentId, ['reason' => 'Admin refund'], null);
            flash('success', 'Payment marked as refunded. Process the actual refund with the gateway.');
        }
        redirect('admin/payments.php');
    }
}

$baseUrl = app_url('admin/payments.php') . ($search !== '' || $statusFilter !== '' || $providerFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'status' => $statusFilter, 'provider' => $providerFilter])) : '');

admin_page_start($user, 'payments', 'Payments', 'All payments across the platform');
?>
<div class="page-head">
  <div>
    <h1>Payments</h1>
    <p>Track, verify and manage subscription and order payments.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/payments.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Reference, business, user">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All</option>
          <option value="pending" <?= selected($statusFilter, 'pending') ?>>Pending</option>
          <option value="successful" <?= selected($statusFilter, 'successful') ?>>Successful</option>
          <option value="failed" <?= selected($statusFilter, 'failed') ?>>Failed</option>
          <option value="refunded" <?= selected($statusFilter, 'refunded') ?>>Refunded</option>
        </select>
      </div>
      <div class="field">
        <label for="provider">Provider</label>
        <select id="provider" name="provider">
          <option value="">All</option>
          <option value="paystack" <?= selected($providerFilter, 'paystack') ?>>Paystack</option>
          <option value="flutterwave" <?= selected($providerFilter, 'flutterwave') ?>>Flutterwave</option>
          <option value="manual" <?= selected($providerFilter, 'manual') ?>>Manual</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?> Filter</button>
      <?php if ($search !== '' || $statusFilter !== '' || $providerFilter !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/payments.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
admin_table(
    [
        ['key' => 'reference', 'label' => 'Reference'],
        ['key' => 'business_name', 'label' => 'Business'],
        ['key' => 'user_name', 'label' => 'User'],
        ['key' => 'provider', 'label' => 'Method', 'render' => fn ($v) => $providerLabels[$v] ?? ucfirst($v)],
        ['key' => 'amount', 'label' => 'Amount', 'align' => 'text-right', 'render' => fn ($v, $r) => money((float) $v, (string) $r['currency'])],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'created_at', 'label' => 'Created'],
    ],
    $payments,
    [
        'pagination' => $pagination,
        'pagination_url' => $baseUrl,
        'actions' => [
            [
                'label' => 'Verify (gateway)',
                'icon' => 'refresh',
                'form' => 'payments.php',
                'condition' => fn ($r) => in_array($r['provider'], ['paystack', 'flutterwave'], true) && $r['status'] === 'pending',
                'hidden' => [
                    'action' => 'verify',
                    'payment_id' => fn ($r) => $r['id'],
                ],
            ],
            [
                'label' => 'Fulfill manually',
                'icon' => 'check',
                'form' => 'payments.php',
                'confirm' => 'Manually fulfill this payment? This will activate the subscription. Only do this after confirming payment was received.',
                'condition' => fn ($r) => $r['provider'] === 'manual' && $r['status'] === 'pending',
                'hidden' => [
                    'action' => 'manual_fulfill',
                    'payment_id' => fn ($r) => $r['id'],
                ],
            ],
            [
                'label' => 'Mark failed',
                'icon' => 'x',
                'form' => 'payments.php',
                'condition' => fn ($r) => $r['status'] === 'pending',
                'hidden' => [
                    'action' => 'mark_failed',
                    'payment_id' => fn ($r) => $r['id'],
                    'reason' => 'Marked failed by admin',
                ],
            ],
            [
                'label' => 'Refund',
                'icon' => 'arrow-left',
                'form' => 'payments.php',
                'confirm' => 'Refund this payment? The actual refund must be processed with the payment gateway.',
                'condition' => fn ($r) => $r['status'] === 'successful',
                'hidden' => [
                    'action' => 'refund',
                    'payment_id' => fn ($r) => $r['id'],
                ],
            ],
        ],
    ]
);
?>

<?php
admin_page_end(['scripts' => ['app.js']]);