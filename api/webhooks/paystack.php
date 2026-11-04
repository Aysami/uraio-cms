<?php
/**
 * Paystack webhook endpoint.
 * POST /api/webhooks/paystack.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if ($rawBody === false || $signature === '') {
    json_response(['ok' => false, 'error' => 'Missing body or signature'], 400);
}

$config = payment_provider_config('paystack');
$secret = (string) ($config['secret'] ?? '');

if ($secret === '') {
    error_log('Paystack webhook received but Paystack is not configured');
    json_response(['ok' => false, 'error' => 'Gateway not configured'], 503);
}

if (!verify_paystack_signature($rawBody, $signature, $secret)) {
    json_response(['ok' => false, 'error' => 'Invalid signature'], 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$result = process_gateway_webhook('paystack', $rawBody, $payload);

if (!empty($result['ok'])) {
    json_response(['ok' => true, 'duplicate' => $result['duplicate'] ?? false]);
}

json_response(['ok' => false, 'error' => $result['error'] ?? 'Webhook processing failed'], 400);