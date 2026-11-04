<?php
/**
 * Flutterwave webhook endpoint.
 * POST /api/webhooks/flutterwave.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';

if ($rawBody === false || $signature === '') {
    json_response(['ok' => false, 'error' => 'Missing body or signature'], 400);
}

$config = payment_provider_config('flutterwave');
$webhookHash = (string) ($config['webhook_hash'] ?? '');

if ($webhookHash === '') {
    error_log('Flutterwave webhook received but webhook hash is not configured');
    json_response(['ok' => false, 'error' => 'Gateway not configured'], 503);
}

if (!verify_flutterwave_signature($signature, $webhookHash)) {
    json_response(['ok' => false, 'error' => 'Invalid signature'], 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$result = process_gateway_webhook('flutterwave', $rawBody, $payload);

if (!empty($result['ok'])) {
    json_response(['ok' => true, 'duplicate' => $result['duplicate'] ?? false]);
}

json_response(['ok' => false, 'error' => $result['error'] ?? 'Webhook processing failed'], 400);