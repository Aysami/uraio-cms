<?php
/**
 * Uraio Connect — embed form submissions (contact, booking).
 * POST /connect/submit.php?t={token}&w=contact|booking
 */

declare(strict_types=1);
define('UBOS_CONNECT_EMBED', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/connect-render.php';

$token = trim((string) ($_GET['t'] ?? ''));
$widget = trim((string) ($_GET['w'] ?? ''));
$business = $token !== '' ? embed_business_by_token($token) : null;

if (!$business || !in_array($widget, ['contact', 'booking'], true)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$redirect = app_url('connect/embed.php?t=' . rawurlencode($token) . '&w=' . rawurlencode($widget));

if (!embed_request_allowed($business)) {
    flash('warning', 'This form is not authorised on this website.');
    redirect($redirect);
}

if (!is_post()) {
    redirect($redirect);
}

$redirectSent = $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'sent=' . $widget;

// Honeypot: pretend success, do nothing.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect($redirectSent . '#ok');
}

if (!rate_limit('connect-' . $token . '-' . client_ip(), 5, 600)) {
    flash('danger', 'Too many submissions. Please wait a few minutes and try again.');
    redirect($redirect);
}

$back = app_url('connect/embed.php?t=' . rawurlencode($token) . '&w=' . rawurlencode($widget) . '&sent=' . urlencode($widget));
$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));

if ($name === '' || mb_strlen($name) > 190) {
    flash('danger', 'Enter a valid name.');
    redirect($redirect);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('danger', 'Enter a valid email address.');
    redirect($redirect);
}

if ($widget === 'contact') {
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 2000) {
        flash('danger', 'Enter a message (max 2,000 characters).');
        redirect($redirect);
    }
    $messageId = db_insert('messages', [
        'business_id' => $business['id'],
        'name' => $name,
        'email' => $email !== '' ? $email : null,
        'phone' => $phone !== '' ? $phone : null,
        'subject' => 'Website contact form',
        'body' => $message,
        'status' => 'new',
        'source' => 'embed',
    ]);
    notify_business_owners((int) $business['id'], 'New message from your website', $name . ' sent you a message via contact form.', 'app/records.php?business=' . $business['id'] . '&entity=messages', 'message');
    activity_log('connect.message_submitted', 'message', $messageId, ['via' => 'embed'], (int) $business['id']);
    send_email_template('new_customer', (string) db_val('SELECT email FROM users WHERE id = ?', [$business['owner_id']]), [
        'name' => db_val('SELECT name FROM users WHERE id = ?', [$business['owner_id']]),
        'customer' => $name,
    ]);
    redirect($back . '#ok');
}

/* booking */
$date = (string) ($_POST['booking_date'] ?? '');
$partySize = max(1, min(100, (int) ($_POST['party_size'] ?? 2)));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime('today')) {
    flash('danger', 'Choose a valid date.');
    redirect($redirect);
}
$notes = trim((string) ($_POST['notes'] ?? ''));
$reference = generate_reference('BKG');
$bookingId = db_insert('bookings', [
    'business_id' => $business['id'],
    'reference' => $reference,
    'customer_name' => $name,
    'customer_email' => $email !== '' ? $email : null,
    'customer_phone' => $phone !== '' ? $phone : null,
    'booking_date' => $date,
    'party_size' => $partySize,
    'status' => 'pending',
    'notes' => $notes !== '' ? $notes : null,
    'meta' => json_encode(['source' => 'embed']),
]);
notify_business_owners((int) $business['id'], 'New booking request', $name . ' requested a booking for ' . $date . '.', 'app/records.php?business=' . $business['id'] . '&entity=bookings', 'booking');
activity_log('connect.booking_submitted', 'booking', $bookingId, ['via' => 'embed', 'reference' => $reference], (int) $business['id']);
redirect($back . '#ok');
