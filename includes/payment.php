<?php
/**
 * Payment gateway integration and verified fulfillment.
 * A payment is never fulfilled from a frontend success message.
 */

function payment_provider_config(string $provider): array
{
    $stored = json_decode_assoc(setting('payment_gateways', '{}'));
    $credentials = config('payments.' . $provider, []);
    return array_merge(is_array($credentials) ? $credentials : [], is_array($stored[$provider] ?? null) ? $stored[$provider] : []);
}

function payment_provider_enabled(string $provider): bool
{
    $config = payment_provider_config($provider);
    if ($provider === 'paystack') {
        return !empty($config['secret']);
    }
    if ($provider === 'flutterwave') {
        return !empty($config['secret']);
    }
    return $provider === 'manual';
}

function http_request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 30): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['ok' => false, 'status' => 0, 'error' => $error, 'body' => null];
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => null, 'body' => json_decode($response, true), 'raw' => $response];
}

function initialize_gateway_payment(string $provider, string $email, float $amount, string $reference, string $callbackUrl, array $metadata = []): array
{
    $config = payment_provider_config($provider);
    if ($provider === 'paystack') {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Paystack is not configured.'];
        }
        $response = http_request('POST', 'https://api.paystack.co/transaction/initialize', [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json',
        ], [
            'email' => $email,
            'amount' => (int) round($amount * 100),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);
        $url = $response['body']['data']['authorization_url'] ?? null;
        return $url
            ? ['ok' => true, 'authorization_url' => $url, 'reference' => $reference]
            : ['ok' => false, 'error' => $response['body']['message'] ?? 'Could not start Paystack payment.'];
    }

    if ($provider === 'flutterwave') {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Flutterwave is not configured.'];
        }
        $response = http_request('POST', 'https://api.flutterwave.com/v3/payments', [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json',
        ], [
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => $metadata['currency'] ?? 'NGN',
            'redirect_url' => $callbackUrl,
            'customer' => ['email' => $email],
            'customizations' => ['title' => 'Uraio Business OS'],
            'meta' => $metadata,
        ]);
        $url = $response['body']['data']['link'] ?? null;
        return $url
            ? ['ok' => true, 'authorization_url' => $url, 'reference' => $reference]
            : ['ok' => false, 'error' => $response['body']['message'] ?? 'Could not start Flutterwave payment.'];
    }

    return ['ok' => false, 'error' => 'Unsupported payment provider.'];
}

function verify_gateway_payment(string $provider, string $reference): array
{
    $config = payment_provider_config($provider);
    if ($provider === 'paystack') {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Paystack is not configured.'];
        }
        $response = http_request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference), [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json',
        ]);
        $data = $response['body']['data'] ?? null;
        if (!$data) {
            return ['ok' => false, 'error' => 'Payment could not be verified.'];
        }
        return [
            'ok' => ($data['status'] ?? '') === 'success',
            'amount' => ((float) ($data['amount'] ?? 0)) / 100,
            'currency' => $data['currency'] ?? 'NGN',
            'reference' => $data['reference'] ?? $reference,
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'raw' => $data,
        ];
    }
    if ($provider === 'flutterwave') {
        $secret = (string) ($config['secret'] ?? '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Flutterwave is not configured.'];
        }
        $response = http_request('GET', 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($reference), [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json',
        ]);
        $data = $response['body']['data'] ?? null;
        if (!$data) {
            return ['ok' => false, 'error' => 'Payment could not be verified.'];
        }
        return [
            'ok' => ($data['status'] ?? '') === 'successful',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'NGN',
            'reference' => $data['tx_ref'] ?? $reference,
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'raw' => $data,
        ];
    }
    return ['ok' => false, 'error' => 'Unsupported payment provider.'];
}

function fulfill_payment(int $paymentId): bool
{
    $payment = db_row('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$payment || $payment['status'] === 'successful') {
        return $payment !== null;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_update('payments', ['status' => 'successful', 'paid_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $paymentId]);
        $meta = json_decode_assoc($payment['meta'] ?? null);
        if (($meta['purpose'] ?? '') === 'subscription' && $payment['business_id']) {
            activate_subscription_payment($payment);
        }
        if (!empty($payment['order_id'])) {
            db_update('orders', ['payment_status' => 'paid', 'status' => 'confirmed'], 'id = :id', ['id' => $payment['order_id']]);
        }
        if (!empty($payment['invoice_id'])) {
            db_update('invoices', ['status' => 'paid'], 'id = :id', ['id' => $payment['invoice_id']]);
        }
        if (!empty($meta['donation_id'])) {
            db_exec("UPDATE donations SET status = 'successful' WHERE id = ?", [(int) $meta['donation_id']]);
        }
        $pdo->commit();
        activity_log('payment.successful', 'payment', $paymentId, ['provider' => $payment['provider']], $payment['business_id'] ? (int) $payment['business_id'] : null);
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Payment fulfillment failed: ' . $e->getMessage());
        return false;
    }
}

function mark_payment_failed(int $paymentId, string $reason = ''): void
{
    $payment = db_row('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$payment) {
        return;
    }
    $meta = json_decode_assoc($payment['meta'] ?? null);
    $meta['failure_reason'] = $reason;
    db_update('payments', ['status' => 'failed', 'meta' => json_encode($meta)], 'id = :id', ['id' => $paymentId]);
}

function record_webhook_event(string $provider, string $eventId, string $payload): array
{
    $existing = db_row('SELECT * FROM webhook_events WHERE provider = ? AND event_id = ?', [$provider, $eventId]);
    if ($existing) {
        return ['duplicate' => true, 'event' => $existing];
    }
    $id = db_insert('webhook_events', [
        'provider' => $provider,
        'event_id' => $eventId,
        'payload' => $payload,
        'status' => 'received',
    ]);
    return ['duplicate' => false, 'event' => db_row('SELECT * FROM webhook_events WHERE id = ?', [$id])];
}

function complete_webhook_event(int $id, string $status = 'processed', string $error = ''): void
{
    db_update('webhook_events', [
        'status' => $status,
        'error' => $error ?: null,
        'processed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $id]);
}

function process_gateway_webhook(string $provider, string $rawBody, array $payload): array
{
    if ($provider === 'paystack') {
        $reference = (string) ($payload['data']['reference'] ?? '');
        $eventId = (string) ($payload['data']['id'] ?? $reference);
    } else {
        $reference = (string) ($payload['data']['tx_ref'] ?? $payload['txRef'] ?? '');
        $eventId = (string) ($payload['data']['id'] ?? $reference);
    }
    if ($reference === '') {
        return ['ok' => false, 'error' => 'Missing payment reference.'];
    }
    $record = record_webhook_event($provider, $eventId !== '' ? $eventId : $reference, $rawBody);
    if ($record['duplicate'] && ($record['event']['status'] ?? '') === 'processed') {
        return ['ok' => true, 'duplicate' => true];
    }
    $payment = db_row('SELECT * FROM payments WHERE reference = ?', [$reference]);
    if (!$payment) {
        complete_webhook_event((int) $record['event']['id'], 'ignored', 'Payment not found');
        return ['ok' => true, 'ignored' => true];
    }
    $verification = verify_gateway_payment($provider, $reference);
    if (!$verification['ok']) {
        complete_webhook_event((int) $record['event']['id'], 'failed', $verification['error'] ?? 'Verification failed');
        return ['ok' => false, 'error' => $verification['error'] ?? 'Verification failed'];
    }
    if (abs((float) $verification['amount'] - (float) $payment['amount']) > 0.01) {
        complete_webhook_event((int) $record['event']['id'], 'failed', 'Amount mismatch');
        return ['ok' => false, 'error' => 'Payment amount mismatch.'];
    }
    fulfill_payment((int) $payment['id']);
    complete_webhook_event((int) $record['event']['id'], 'processed');
    return ['ok' => true];
}

function create_payment(array $data): int
{
    return db_insert('payments', [
        'business_id' => $data['business_id'] ?? null,
        'user_id' => $data['user_id'] ?? null,
        'subscription_id' => $data['subscription_id'] ?? null,
        'order_id' => $data['order_id'] ?? null,
        'invoice_id' => $data['invoice_id'] ?? null,
        'provider' => $data['provider'] ?? 'manual',
        'reference' => $data['reference'] ?? generate_reference('PAY'),
        'amount' => $data['amount'] ?? 0,
        'currency' => $data['currency'] ?? 'NGN',
        'status' => 'pending',
        'meta' => json_encode($data['meta'] ?? []),
    ]);
}

function payment_callback_url(): string
{
    return app_url('app/billing.php?payment=callback');
}
