<?php
/**
 * Subscription plans, entitlements and lifecycle.
 */

function subscription_plans(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM subscription_plans';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY sort_order ASC, price_monthly ASC';
    return db_all($sql);
}

function subscription_plan(int|string $idOrSlug): ?array
{
    return is_numeric($idOrSlug)
        ? db_row('SELECT * FROM subscription_plans WHERE id = ?', [(int) $idOrSlug])
        : db_row('SELECT * FROM subscription_plans WHERE slug = ?', [(string) $idOrSlug]);
}

function plan_price(array $plan, string $cycle = 'monthly'): float
{
    return match ($cycle) {
        'quarterly' => (float) $plan['price_quarterly'],
        'annual' => (float) $plan['price_annual'],
        default => (float) $plan['price_monthly'],
    };
}

function plan_limits(array $plan): array
{
    $defaults = [
        'businesses' => 1,
        'staff' => 3,
        'products' => 50,
        'custom_domain' => 0,
        'advanced_analytics' => 0,
        'ai_credits' => 20,
        'templates' => 1,
    ];
    $limits = json_decode_assoc($plan['limits'] ?? null);
    return array_merge($defaults, $limits);
}

function current_subscription(int $businessId): ?array
{
    return db_row(
        "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.features, p.limits
         FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
         WHERE s.business_id = ? ORDER BY s.id DESC LIMIT 1",
        [$businessId]
    );
}

function subscription_active(?array $subscription): bool
{
    if (!$subscription) {
        return false;
    }
    if (in_array($subscription['status'], ['active', 'trialing'], true)) {
        if ($subscription['ends_at'] && strtotime($subscription['ends_at']) < time()) {
            return false;
        }
        return true;
    }
    return false;
}

function business_plan(array $business): array
{
    $subscription = current_subscription((int) $business['id']);
    if ($subscription) {
        return subscription_plan((int) $subscription['plan_id']) ?? subscription_plan('free');
    }
    return subscription_plan('free');
}

function business_limits(array $business): array
{
    $plan = business_plan($business);
    return $plan ? plan_limits($plan) : plan_limits(['limits' => null]);
}

function business_can(string $feature, array $business): bool
{
    $limits = business_limits($business);
    if ($feature === 'custom_domain') {
        return !empty($limits['custom_domain']);
    }
    if ($feature === 'advanced_analytics') {
        return !empty($limits['advanced_analytics']);
    }
    return true;
}

function create_free_subscription(int $businessId, int $userId): int
{
    $plan = subscription_plan('free');
    if (!$plan) {
        return 0;
    }
    $existing = db_val('SELECT id FROM subscriptions WHERE business_id = ? LIMIT 1', [$businessId]);
    if ($existing) {
        return (int) $existing;
    }
    return db_insert('subscriptions', [
        'business_id' => $businessId,
        'user_id' => $userId,
        'plan_id' => $plan['id'],
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'amount' => 0,
        'currency' => $plan['currency'],
        'starts_at' => date('Y-m-d H:i:s'),
        'ends_at' => date('Y-m-d H:i:s', strtotime('+10 years')),
    ]);
}

function subscription_cycle_end(string $cycle, ?string $from = null): string
{
    $fromTs = $from ? strtotime($from) : time();
    return match ($cycle) {
        'annual' => date('Y-m-d H:i:s', strtotime('+1 year', $fromTs)),
        'quarterly' => date('Y-m-d H:i:s', strtotime('+3 months', $fromTs)),
        default => date('Y-m-d H:i:s', strtotime('+1 month', $fromTs)),
    };
}

function upgrade_subscription(int $businessId, int $userId, int $planId, string $cycle = 'monthly'): array
{
    $plan = subscription_plan($planId);
    if (!$plan) {
        throw new RuntimeException('Subscription plan not found.');
    }
    $subscription = current_subscription($businessId);
    $price = plan_price($plan, $cycle);
    $reference = generate_reference('SUB');
    $paymentId = db_insert('payments', [
        'business_id' => $businessId,
        'user_id' => $userId,
        'subscription_id' => $subscription['id'] ?? null,
        'provider' => 'manual',
        'reference' => $reference,
        'amount' => $price,
        'currency' => $plan['currency'],
        'status' => 'pending',
        'meta' => json_encode(['plan_id' => $planId, 'cycle' => $cycle, 'purpose' => 'subscription']),
    ]);
    if ($price <= 0) {
        fulfill_payment($paymentId);
    }
    return ['payment_id' => $paymentId, 'reference' => $reference, 'amount' => $price, 'plan' => $plan];
}

function activate_subscription_payment(array $payment): void
{
    $meta = json_decode_assoc($payment['meta'] ?? null);
    $planId = (int) ($meta['plan_id'] ?? 0);
    $cycle = $meta['cycle'] ?? 'monthly';
    $plan = subscription_plan($planId);
    if (!$plan) {
        return;
    }
    $existing = current_subscription((int) $payment['business_id']);
    $now = date('Y-m-d H:i:s');
    if ($existing) {
        db_update('subscriptions', [
            'plan_id' => $plan['id'],
            'status' => 'active',
            'billing_cycle' => $cycle,
            'amount' => (float) $payment['amount'],
            'currency' => $payment['currency'],
            'starts_at' => $existing['starts_at'],
            'ends_at' => subscription_cycle_end($cycle, $existing['ends_at'] ?? $now),
            'canceled_at' => null,
        ], 'id = :id', ['id' => $existing['id']]);
        $subscriptionId = (int) $existing['id'];
    } else {
        $subscriptionId = db_insert('subscriptions', [
            'business_id' => $payment['business_id'],
            'user_id' => $payment['user_id'],
            'plan_id' => $plan['id'],
            'status' => 'active',
            'billing_cycle' => $cycle,
            'amount' => (float) $payment['amount'],
            'currency' => $payment['currency'],
            'starts_at' => $now,
            'ends_at' => subscription_cycle_end($cycle),
        ]);
    }
    db_exec('INSERT IGNORE INTO subscription_payments (subscription_id, payment_id) VALUES (?, ?)', [$subscriptionId, $payment['id']]);
    notify_user(
        (int) $payment['user_id'],
        'Subscription activated',
        $plan['name'] . ' is now active for your business.',
        'app/billing.php?business=' . $payment['business_id'],
        (int) $payment['business_id'],
        'billing'
    );
    send_email_template('subscription_activated', (string) db_val('SELECT email FROM users WHERE id = ?', [$payment['user_id']]), [
        'name' => db_val('SELECT name FROM users WHERE id = ?', [$payment['user_id']]),
        'plan' => $plan['name'],
        'business' => db_val('SELECT name FROM businesses WHERE id = ?', [$payment['business_id']]),
    ]);
}

function subscription_summary(int $businessId): array
{
    $subscription = current_subscription($businessId);
    $plan = $subscription ? subscription_plan((int) $subscription['plan_id']) : subscription_plan('free');
    return [
        'subscription' => $subscription,
        'plan' => $plan,
        'limits' => $plan ? plan_limits($plan) : [],
        'active' => subscription_active($subscription),
    ];
}

/**
 * Maximum number of businesses a user may own, based on the best active plan
 * across the businesses they already own. New users get the free plan limit.
 */
function user_business_limit(int $userId): int
{
    $rows = db_all(
        "SELECT p.limits FROM subscriptions s
         JOIN subscription_plans p ON p.id = s.plan_id
         JOIN businesses b ON b.id = s.business_id
         WHERE b.owner_id = ? AND b.deleted_at IS NULL AND s.status IN ('active','trialing')",
        [$userId]
    );
    $max = 1;
    if (!$rows) {
        $free = subscription_plan('free');
        return $free ? (int) (plan_limits($free)['businesses'] ?? 1) : 1;
    }
    foreach ($rows as $row) {
        $limits = json_decode_assoc($row['limits']);
        $max = max($max, (int) ($limits['businesses'] ?? 1));
    }
    return $max;
}

function user_owned_business_count(int $userId): int
{
    return (int) db_val('SELECT COUNT(*) FROM businesses WHERE owner_id = ? AND deleted_at IS NULL', [$userId]);
}

function template_allowed_for_business(array $template, array $business): bool
{
    if (empty($template['is_premium'])) {
        return true;
    }
    $limits = business_limits($business);
    return (int) ($limits['templates'] ?? 1) >= 5;
}
