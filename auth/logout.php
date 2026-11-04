<?php
/**
 * Sign out. POST + CSRF only.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    logout_user();
    start_secure_session();
    flash('success', 'You have been signed out.');
    redirect('auth/login.php');
}

redirect('app/dashboard.php');
