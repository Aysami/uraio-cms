<?php
/**
 * Billing: current plan, limits, upgrades and verified payment history.
 * Paid plans are only activated after the gateway confirms the transaction
 * server-side (or an administrator approves a manual payment).
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];

$cycle = (string) query('cycle', 'monthly');
if (!in_array($cycle, ['monthly', 'quarterly', 'annual'], true)) {
    $cycle = 'monthly';
}
$errors = [];

/* ---------- Gateway callback: verify before fulfilling ---------- */
if (query('payment') === 'callback') {
    $reference = trim((string) (query('reference') ?: query('trxref')));
    $payment = $reference !== ''
        ? db_row('SELECT * FROM payments WHERE reference = ? AND business_id = ?', [$reference, $businessId])
        : null;
    if (!$payment) {
        flash('danger', 'We could not find that payment for this business.');
    } elseif ($payment['status'] === 'successful') {
        flash('info', 'That payment has already been confirmed.');
    } elseif (!in_array($payment['provider'], ['paystack', 'flutterwave'], true)) {
        flash('danger', 'This payment cannot be verified automatically. Contact support with your reference.');
    } else {
        $verification = verify_gateway_payment($payment['provider'], $reference);
        if (empty($verification['ok'])) {
            mark_payment_failed((int) $payment['id'], (string) ($verification['error'] ?? 'Verification failed'));
            flash('danger', 'The payment could not be verified. You have not been charged again. Contact support if money left your account.');
        } elseif (abs((float) $verification['amount'] - (float) $payment['amount']) > 0.01) {
            mark_payment_failed((int) $payment['id'], 'Amount mismatch');
            flash('danger', 'The verified amount did not match the order. Contact support with reference ' . $reference . '.');
        } elseif (fulfill_payment((int) $payment['id'])) {
            flash('success', 'Payment confirmed. Your new plan is now active.');
        } else {
            flash('danger', 'Payment was verified but activation failed. Contact support with reference ' . $reference . '.');
        }
    }
    redirect('app/billing.php');
}

/* ---------- Checkout ---------- */
if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');

    if ($action === 'checkout') {
        if (!in_array($business['member_role'] ?? '', ['owner'], true) && !is_admin_user($user)) {
            $errors['form'] = 'Only the business owner can change the subscription.';
        }
        $planId = (int) post('plan_id', 0);
        $plan = subscription_plan($planId);
        $chosenCycle = (string) post('cycle', 'monthly');
        if (!in_array($chosenCycle, ['monthly', 'quarterly', 'annual'], true)) {
            $chosenCycle = 'monthly';
        }
        $provider = (string) post('provider', 'manual');
        if (!$plan || $plan['status'] !== 'active') {
            $errors['form'] = 'Choose a valid subscription plan.';
        } elseif (!in_array($provider, ['paystack', 'flutterwave', 'manual'], true)) {
            $errors['form'] = 'Choose a valid payment method.';
        } elseif (!payment_provider_enabled($provider)) {
            $errors['form'] = 'That payment method is not available right now. Choose another method or contact support.';
        } else {
            $amount = plan_price($plan, $chosenCycle);
            if ($amount <= 0) {
                $errors['form'] = 'The Free plan is applied automatically and does not require checkout.';
            } else {
                $current = current_subscription($businessId);
                $reference = generate_reference('SUB');
                $paymentId = create_payment([
                    'business_id' => $businessId,
                    'user_id' => (int) $user['id'],
                    'subscription_id' => $current['id'] ?? null,
                    'provider' => $provider,
                    'reference' => $reference,
                    'amount' => $amount,
                    'currency' => $plan['currency'],
                    'meta' => [
                        'purpose' => 'subscription',
                        'plan_id' => (int) $plan['id'],
                        'plan_name' => $plan['name'],
                        'cycle' => $chosenCycle,
                    ],
                ]);
                activity_log('billing.checkout_started', 'payment', $paymentId, [
                    'plan' => $plan['slug'],
                    'cycle' => $chosenCycle,
                    'provider' => $provider,
                ], $businessId);

                if ($provider === 'manual') {
                    flash('info', 'Manual payment started. Use reference ' . $reference . ' when sending payment. Your plan activates after our team confirms it.');
                    redirect('app/billing.php');
                }

                $callback = app_url('app/billing.php?payment=callback&reference=' . rawurlencode($reference));
                $init = initialize_gateway_payment(
                    $provider,
                    (string) $user['email'],
                    $amount,
                    $reference,
                    $callback,
                    ['currency' => $plan['currency'], 'plan' => $plan['slug'], 'business_id' => $businessId]
                );
                if (!empty($init['ok']) && !empty($init['authorization_url'])) {
                    redirect((string) $init['authorization_url']);
                }
                mark_payment_failed($paymentId, (string) ($init['error'] ?? 'Gateway initialisation failed'));
                $errors['form'] = (string) ($init['error'] ?? 'Could not start the payment. Please try again.');
            }
        }
    }

    if ($action === 'cancel_pending') {
        $paymentId = (int) post('payment_id', 0);
        $payment = db_row("SELECT * FROM payments WHERE id = ? AND business_id = ? AND status = 'pending'", [$paymentId, $businessId]);
        if ($payment) {
            mark_payment_failed((int) $payment['id'], 'Cancelled by business owner');
            activity_log('billing.checkout_cancelled', 'payment', $paymentId, [], $businessId);
            flash('success', 'Pending payment cancelled.');
        } else {
            flash('warning', 'That pending payment could not be found.');
        }
        redirect('app/billing.php');
    }
}

$summary = subscription_summary($businessId);
$plans = subscription_plans();
$current = $summary['subscription'];
$currentPlan = $summary['plan'];
$limits = $summary['limits'];
$payments = db_all(
    'SELECT * FROM payments WHERE business_id = ? ORDER BY id DESC LIMIT 40',
    [$businessId]
);
$pendingManual = db_all(
    "SELECT * FROM payments WHERE business_id = ? AND status = 'pending' AND provider = 'manual' ORDER BY id DESC",
    [$businessId]
);
$providerLabels = ['paystack' => 'Paystack', 'flutterwave' => 'Flutterwave', 'manual' => 'Bank transfer / manual'];
$availableProviders = array_values(array_filter(['paystack', 'flutterwave', 'manual'], 'payment_provider_enabled'));

layout_app_start($business, 'billing', 'Billing', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Plan &amp; billing</h1>
    <p>Manage the subscription for <?= e($business['name']) ?>. Plans activate only after payment is verified.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('app/settings.php')) ?>"><?= icon('settings', 'icon icon-sm') ?> Business settings</a>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Current plan</h2>
          <p class="small muted" style="margin:4px 0 0">
            <?= e($currentPlan['name'] ?? 'Free') ?>
            <?php if ($current): ?>· <?= e(ucfirst($current['billing_cycle'])) ?> billing<?php endif; ?>
          </p>
        </div>
        <?= $summary['active'] ? status_badge('active') : status_badge('inactive') ?>
      </div>
      <div class="card-body">
        <div class="flex-between flex-wrap">
          <div>
            <div class="small muted">Amount</div>
            <div class="strong" style="font-size:1.35rem"><?= e(money((float) ($current['amount'] ?? 0), (string) ($current['currency'] ?? $currentPlan['currency'] ?? 'NGN'))) ?></div>
          </div>
          <div>
            <div class="small muted">Started</div>
            <div class="strong"><?= e(nice_date($current['starts_at'] ?? null)) ?></div>
          </div>
          <div>
            <div class="small muted">Renews / ends</div>
            <div class="strong"><?= e(nice_date($current['ends_at'] ?? null)) ?></div>
          </div>
          <div>
            <div class="small muted">Status</div>
            <div class="strong"><?= e(ucfirst($current['status'] ?? 'active')) ?></div>
          </div>
        </div>
        <?php if ($current && $current['ends_at'] && strtotime($current['ends_at']) < time()): ?>
          <div class="alert alert-warning" style="margin-top:16px"><?= icon('alert') ?><div>Your paid period has ended. Renew to keep premium features and your custom domain active.</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Plan limits</h2></div>
      <div class="card-body">
        <div class="stat-grid">
          <div class="stat-card"><div class="stat-label">Businesses</div><div class="stat-value"><?= e((string) ($limits['businesses'] ?? 1)) ?></div><div class="stat-note">You own <?= e((string) user_owned_business_count((int) $user['id'])) ?></div></div>
          <div class="stat-card"><div class="stat-label">Staff records</div><div class="stat-value"><?= e((string) ($limits['staff'] ?? 3)) ?></div><div class="stat-note">Team members and staff</div></div>
          <div class="stat-card"><div class="stat-label">Products</div><div class="stat-value"><?= e(number_format((int) ($limits['products'] ?? 50))) ?></div><div class="stat-note">Catalogue items</div></div>
          <div class="stat-card"><div class="stat-label">AI credits</div><div class="stat-value"><?= e(number_format((int) ($limits['ai_credits'] ?? 20))) ?></div><div class="stat-note">Per month</div></div>
        </div>
        <div class="flex flex-wrap mt-2">
          <span class="badge <?= business_can('custom_domain', $business) ? 'badge-success' : 'badge-neutral' ?>"><?= business_can('custom_domain', $business) ? 'Custom domain enabled' : 'Custom domain unavailable' ?></span>
          <span class="badge <?= business_can('advanced_analytics', $business) ? 'badge-success' : 'badge-neutral' ?>"><?= business_can('advanced_analytics', $business) ? 'Advanced analytics enabled' : 'Basic analytics only' ?></span>
          <span class="badge badge-neutral"><?= e((string) ($limits['templates'] ?? 1)) ?> templates</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h2 class="mt-0">Payment history</h2>
        <div class="btn-group">
          <a class="btn btn-ghost btn-sm<?= $cycle === 'monthly' ? ' is-active' : '' ?>" href="<?= e(app_url('app/billing.php?cycle=monthly')) ?>">Monthly</a>
          <a class="btn btn-ghost btn-sm<?= $cycle === 'quarterly' ? ' is-active' : '' ?>" href="<?= e(app_url('app/billing.php?cycle=quarterly')) ?>">Quarterly</a>
          <a class="btn btn-ghost btn-sm<?= $cycle === 'annual' ? ' is-active' : '' ?>" href="<?= e(app_url('app/billing.php?cycle=annual')) ?>">Annual</a>
        </div>
      </div>
      <?php if (!$payments): ?>
        <div class="card-body"><p class="small muted" style="margin:0">No payments yet. Your free plan does not require payment.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Reference</th><th>Purpose</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($payments as $payment): ?>
                <?php $meta = json_decode_assoc($payment['meta'] ?? null); ?>
                <tr>
                  <td class="mono small"><?= e($payment['reference']) ?></td>
                  <td><?= e($meta['plan_name'] ?? ($meta['purpose'] ?? 'Payment')) ?><?php if (!empty($meta['cycle'])): ?><span class="cell-sub"><?= e(ucfirst($meta['cycle'])) ?></span><?php endif; ?></td>
                  <td><?= e($providerLabels[$payment['provider']] ?? ucfirst($payment['provider'])) ?></td>
                  <td class="strong"><?= e(money((float) $payment['amount'], (string) $payment['currency'])) ?></td>
                  <td><?= status_badge((string) $payment['status']) ?></td>
                  <td class="small muted"><?= e(nice_datetime($payment['paid_at'] ?: $payment['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <?php if ($pendingManual): ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Manual payment pending</h2></div>
        <div class="card-body">
          <?php foreach ($pendingManual as $payment): ?>
            <?php $meta = json_decode_assoc($payment['meta'] ?? null); ?>
            <p class="small">Send <strong><?= e(money((float) $payment['amount'], (string) $payment['currency'])) ?></strong> for the <strong><?= e($meta['plan_name'] ?? 'selected') ?></strong> plan and use this reference:</p>
            <div class="flex mb-2">
              <input type="text" readonly value="<?= e($payment['reference']) ?>" style="flex:1">
              <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($payment['reference']) ?>">Copy</button>
            </div>
            <p class="small muted">Email proof of payment to <?= e((string) setting('support_email', 'support@uraio.com')) ?> with your reference. Our team confirms it before activating the plan. No plan is activated from a screenshot alone.</p>
            <form method="post" action="<?= e(app_url('app/billing.php')) ?>" data-confirm="Cancel this pending manual payment?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel_pending">
              <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">Cancel request</button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Secure billing</h2></div>
      <div class="card-body">
        <ul class="check-list">
          <li>Card payments are verified with the gateway before activation.</li>
          <li>Webhooks are idempotent and recorded for auditing.</li>
          <li>Manual payments are approved by an administrator.</li>
          <li>Your payment details are never stored on our servers.</li>
        </ul>
        <?php if (!$availableProviders): ?>
          <div class="alert alert-warning"><?= icon('alert') ?><div>No payment gateway is configured yet. Contact <?= e((string) setting('support_email', 'support@uraio.com')) ?> to arrange payment.</div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<h2 class="section-title">Choose a plan</h2>
<p class="muted small">Prices shown for <strong><?= e($cycle) ?></strong> billing. Change the billing period above to compare.</p>
<div class="pricing-grid">
  <?php foreach ($plans as $plan): ?>
    <?php
      $price = plan_price($plan, $cycle);
      $isCurrent = $currentPlan && (int) $currentPlan['id'] === (int) $plan['id'] && $summary['active'];
      $planLimits = plan_limits($plan);
      $features = json_decode_assoc($plan['features'] ?? null);
    ?>
    <div class="plan-card<?= !empty($plan['is_popular']) ? ' is-popular' : '' ?><?= $isCurrent ? ' is-current' : '' ?>">
      <?php if (!empty($plan['is_popular'])): ?><span class="popular-tag">Most popular</span><?php endif; ?>
      <h3 class="mt-0"><?= e($plan['name']) ?></h3>
      <p class="small muted"><?= e($plan['description'] ?? '') ?></p>
      <div class="plan-price">
        <?php if ($price <= 0): ?>
          <span class="strong" style="font-size:1.8rem">Free</span>
        <?php else: ?>
          <span class="strong" style="font-size:1.8rem"><?= e(money($price, (string) $plan['currency'])) ?></span>
          <span class="muted small">/ <?= e($cycle === 'annual' ? 'year' : ($cycle === 'quarterly' ? 'quarter' : 'month')) ?></span>
        <?php endif; ?>
      </div>
      <ul class="plan-features">
        <?php foreach ($features as $feature): ?><li><?= e((string) $feature) ?></li><?php endforeach; ?>
        <li><?= e((string) ($planLimits['staff'] ?? 0)) ?> staff records</li>
        <li><?= e(number_format((int) ($planLimits['products'] ?? 0))) ?> products</li>
        <li><?= e(number_format((int) ($planLimits['ai_credits'] ?? 0))) ?> AI credits / month</li>
      </ul>
      <?php if ($isCurrent): ?>
        <button class="btn btn-soft btn-block" type="button" disabled>Current plan</button>
      <?php elseif ($price <= 0): ?>
        <p class="small muted" style="margin:0">Every business starts on Free automatically.</p>
      <?php elseif (!in_array($business['member_role'] ?? '', ['owner'], true) && !is_admin_user($user)): ?>
        <p class="small muted" style="margin:0">Ask the business owner to change the plan.</p>
      <?php else: ?>
        <form method="post" action="<?= e(app_url('app/billing.php')) ?>" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="checkout">
          <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
          <input type="hidden" name="cycle" value="<?= e($cycle) ?>">
          <div class="field">
            <label for="provider-<?= (int) $plan['id'] ?>">Payment method</label>
            <select name="provider" id="provider-<?= (int) $plan['id'] ?>" required>
              <?php foreach ($availableProviders as $provider): ?>
                <option value="<?= e($provider) ?>"><?= e($providerLabels[$provider]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary btn-block" type="submit">
            <?= icon('credit-card', 'icon icon-sm') ?> Continue with <?= e($plan['name']) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
