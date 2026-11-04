<?php
/**
 * Email verification link handler.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$verified = $token !== '' && verify_email_token($token);

if ($verified) {
    flash('success', 'Your email address is verified. Thank you.');
} else {
    flash('warning', 'This verification link is invalid or has expired.');
}
if (is_logged_in()) {
    redirect('app/dashboard.php');
}
redirect('auth/login.php');
