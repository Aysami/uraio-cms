<?php
/**
 * Manual payment fulfillment endpoint (admin only).
 * POST /api/webhooks/manual.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/payment.php';

$user = current_user();
if (!$user || !is_admin_user($user)) {
    json_response(['ok' => false, 'error' => 'Admin access required'], 403);
}

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

verify_csrf();

$reference = trim((string) post('reference', ''));
$action = (string) post('action', 'fulfill');

if ($reference === '') {
    json_response(['ok' => false, 'error' => 'Reference is required'], 422);
}

$payment = db_row('SELECT * FROM payments WHERE reference = ?', [$reference]);
if (!$payment) {
    json_response(['ok' => false, 'error' => 'Payment not found'], 404);
}

if ($action === 'fulfill') {
    if ($payment['status'] === 'successful') {
        json_response(['ok' => true, 'message' => 'Already fulfilled']);
    }
    if (fulfill_payment((int) $payment['id'])) {
        activity_log('admin.payment_manual_fulfill', 'payment', $payment['id'], ['reference' => $reference], null);
        json_response(['ok' => true, 'message' => 'Payment fulfilled']);
    }
    json_response(['ok' => false, 'error' => 'Fulfillment failed'], 500);
}

if ($action === 'fail') {
    $reason = trim((string) post('reason', 'Marked failed by admin'));
    mark_payment_failed((int) $payment['id'], $reason);
    activity_log('admin.payment_manual_fail', 'payment', $payment['id'], ['reference' => $reference, 'reason' => $reason], null);
    json_response(['ok' => true, 'message' => 'Payment marked failed']);
}

json_response(['ok' => false, 'error' => 'Invalid action'], 422);