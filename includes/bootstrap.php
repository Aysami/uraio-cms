<?php
/**
 * Uraio Business OS bootstrap.
 * Loaded by every entry point. No framework, no Composer requirement.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('UBOS_VERSION', '1.0.5');

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/security.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/tenant.php';

if (PHP_SAPI !== 'cli') {
    start_secure_session();
    if (defined('UBOS_CONNECT_EMBED') && UBOS_CONNECT_EMBED) {
        send_embed_headers();
    } else {
        send_security_headers();
    }
}

if (PHP_SAPI !== 'cli') {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!is_installed() && $script !== 'install.php') {
        header('Location: ' . app_url('install.php'));
        exit;
    }
}

require_once BASE_PATH . '/includes/category-engine.php';
require_once BASE_PATH . '/includes/module-loader.php';
require_once BASE_PATH . '/includes/entity-engine.php';
require_once BASE_PATH . '/includes/website-engine.php';
require_once BASE_PATH . '/includes/subscription.php';
require_once BASE_PATH . '/includes/payment.php';
require_once BASE_PATH . '/includes/connect-engine.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/includes/ai.php';
require_once BASE_PATH . '/includes/uraio.php';
require_once BASE_PATH . '/includes/icons.php';
require_once BASE_PATH . '/includes/layout.php';

if (PHP_SAPI !== 'cli' && is_installed() && config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
