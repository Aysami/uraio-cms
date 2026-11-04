<?php
/**
 * Authentication and role-based authorization.
 */

function current_user(bool $refresh = false): ?array
{
    static $user = null;
    if ($user !== null && !$refresh) {
        return $user;
    }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    try {
        $user = db_row(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'",
            [$id]
        );
    } catch (Throwable $e) {
        return null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin_user(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) {
        return false;
    }
    return (int) $user['is_super_admin'] === 1
        || in_array($user['role_slug'] ?? '', ['super_admin', 'admin', 'support_agent', 'content_manager'], true);
}

function is_super_admin(?array $user = null): bool
{
    $user ??= current_user();
    return $user && ((int) $user['is_super_admin'] === 1 || ($user['role_slug'] ?? '') === 'super_admin');
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['_intended'] = $next;
        flash('warning', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect('app/dashboard.php');
    }
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin_user($user)) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Access denied', 'You do not have permission to access the Uraio administration area.');
        exit;
    }
    return $user;
}

function require_super_admin(): array
{
    $user = require_login();
    if (!is_super_admin($user)) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Access denied', 'This action requires super administrator privileges.');
        exit;
    }
    return $user;
}

function user_permissions(?array $user = null): array
{
    $user ??= current_user();
    if (!$user) {
        return [];
    }
    if (is_admin_user($user)) {
        return ['*'];
    }
    $rows = db_all(
        'SELECT p.slug FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = ?',
        [$user['role_id']]
    );
    return array_column($rows, 'slug');
}

function can(string $permission): bool
{
    $permissions = user_permissions();
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (login_is_rate_limited($email)) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in 15 minutes.'];
    }
    $user = db_row(
        "SELECT u.*, r.slug AS role_slug FROM users u LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.email = ? AND u.deleted_at IS NULL",
        [$email]
    );
    if (!$user || !password_verify_secure($password, $user['password_hash'])) {
        record_login_attempt($email, false);
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }
    if ($user['status'] !== 'active') {
        record_login_attempt($email, false);
        return ['ok' => false, 'error' => 'This account is not active. Contact support.'];
    }
    record_login_attempt($email, true);
    login_user($user);
    return ['ok' => true, 'user' => $user];
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['_login_time'] = time();
    unset($_SESSION['_csrf_token']);
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    try {
        db_insert('user_sessions', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', session_id()),
            'ip' => client_ip(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30),
        ]);
        db_exec('DELETE FROM user_sessions WHERE expires_at < NOW()');
    } catch (Throwable $e) {
    }
    activity_log('auth.login', 'user', $user['id']);
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        activity_log('auth.logout', 'user', $user['id']);
    }
    try {
        db_exec('DELETE FROM user_sessions WHERE token_hash = ?', [hash('sha256', session_id())]);
    } catch (Throwable $e) {
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function create_user(array $data): int
{
    $userId = db_insert('users', [
        'uuid' => uuid(),
        'name' => trim($data['name']),
        'email' => strtolower(trim($data['email'])),
        'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
        'password_hash' => password_hash_secure($data['password']),
        'status' => 'active',
        'role_id' => $data['role_id'] ?? db_val("SELECT id FROM roles WHERE slug = 'business_owner'"),
    ]);
    return $userId;
}

function issue_verification_token(int $userId, string $type = 'email'): string
{
    $token = bin2hex(random_bytes(32));
    db_insert('user_verifications', [
        'user_id' => $userId,
        'type' => $type,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 48),
    ]);
    return $token;
}

function verify_email_token(string $token): bool
{
    $row = db_row(
        "SELECT * FROM user_verifications WHERE token_hash = ? AND type = 'email' AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
        [hash('sha256', $token)]
    );
    if (!$row) {
        return false;
    }
    db_exec('UPDATE user_verifications SET used_at = NOW() WHERE id = ?', [$row['id']]);
    db_exec('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$row['user_id']]);
    activity_log('auth.email_verified', 'user', $row['user_id']);
    return true;
}

function issue_password_reset(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    db_insert('password_resets', [
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60),
    ]);
    return $token;
}

function reset_password_with_token(string $token, string $password): bool
{
    $row = db_row(
        'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
        [hash('sha256', $token)]
    );
    if (!$row) {
        return false;
    }
    db_exec("UPDATE users SET password_hash = ?, status = IF(status = 'pending', 'active', status) WHERE id = ?", [password_hash_secure($password), $row['user_id']]);
    db_exec("UPDATE business_members SET status = 'active' WHERE user_id = ? AND status = 'invited'", [$row['user_id']]);
    db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);
    activity_log('auth.password_reset', 'user', $row['user_id']);
    return true;
}
