<?php
/**
 * Security helpers: sessions, CSRF, headers, rate limiting, validation,
 * encryption and webhook signature checks.
 */

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ubos_session');
    session_start();
    if (empty($_SESSION['_started'])) {
        $_SESSION['_started'] = time();
        session_regenerate_id(true);
    }
}

function send_security_headers(bool $publicSite = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    $nonce = csp_nonce();
    $script = "script-src 'self' 'nonce-{$nonce}'";
    $frame = "frame-src 'self' https://www.google.com https://maps.google.com";
    $style = "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com";
    $csp = "default-src 'self'; {$script}; {$style}; font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: https:; connect-src 'self'; {$frame}; form-action 'self'; "
        . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";
    header('Content-Security-Policy: ' . $csp);
    header('X-CSP-Nonce: ' . $nonce);
}

/**
 * Loose, public headers for Uraio Connect widget pages. Widgets must be
 * embeddable in iframes on third-party sites, and have no sensitive content.
 * Input validation and the per-business origins allowlist are the controls —
 * not frame restrictions.
 */
function send_embed_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; font-src 'none'; img-src data: https:; frame-src https://www.google.com https://maps.google.com; form-action 'self'; frame-ancestors *");
    header('Cross-Origin-Opener-Policy: unsafe-none');
    // No X-Frame-Options — the widget is designed to be embedded anywhere;
    // the per-business origins allowlist is enforced at runtime.
}

function csp_nonce(): string
{
    if (empty($_SESSION['_csp_nonce'])) {
        $_SESSION['_csp_nonce'] = base64_encode(random_bytes(16));
    }
    return $_SESSION['_csp_nonce'];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token = null): bool
{
    $token ??= $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && $token !== '' && !empty($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }
    if (!csrf_valid()) {
        http_response_code(419);
        if (wants_json()) {
            json_response(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
        }
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(419, 'Session expired', 'Your security token expired. Go back, refresh the page and try again.');
        exit;
    }
}

function rate_limit(string $bucket, int $max = 10, int $seconds = 900): bool
{
    $key = substr($bucket, 0, 180);
    $now = date('Y-m-d H:i:s');
    try {
        $row = db_row('SELECT * FROM rate_limits WHERE bucket = ?', [$key]);
        if (!$row || strtotime($row['expires_at']) < time()) {
            db_exec('DELETE FROM rate_limits WHERE bucket = ?', [$key]);
            db_insert('rate_limits', [
                'bucket' => $key,
                'hits' => 1,
                'window_start' => $now,
                'expires_at' => date('Y-m-d H:i:s', time() + $seconds),
            ]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$key]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function record_login_attempt(string $email, bool $successful): void
{
    try {
        db_insert('login_attempts', [
            'email' => strtolower($email),
            'ip' => client_ip(),
            'successful' => $successful ? 1 : 0,
        ]);
    } catch (Throwable $e) {
    }
}

function login_is_rate_limited(string $email): bool
{
    $count = (int) db_val(
        "SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE) AND (email = ? OR ip = ?)",
        [strtolower($email), client_ip()]
    );
    return $count >= 8;
}

/**
 * Validate input against a compact rule set.
 * Rules: required, email, min:n, max:n, numeric, url, in:a|b, confirmed,
 * slug, phone, date, boolean.
 */
function validate(array $data, array $rules): array
{
    $errors = [];
    foreach ($rules as $field => $ruleString) {
        $value = trim((string) ($data[$field] ?? ''));
        $ruleset = explode('|', $ruleString);
        $label = ucwords(str_replace(['_', '.'], ' ', $field));
        foreach ($ruleset as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            if ($name === 'required' && $value === '') {
                $errors[$field] = $label . ' is required.';
                break;
            }
            if ($value === '') {
                continue;
            }
            if ($name === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Enter a valid email address.';
                break;
            }
            if ($name === 'min' && mb_strlen($value) < (int) $arg) {
                $errors[$field] = $label . ' must be at least ' . (int) $arg . ' characters.';
                break;
            }
            if ($name === 'max' && mb_strlen($value) > (int) $arg) {
                $errors[$field] = $label . ' must be ' . (int) $arg . ' characters or fewer.';
                break;
            }
            if ($name === 'numeric' && !is_numeric($value)) {
                $errors[$field] = $label . ' must be a number.';
                break;
            }
            if ($name === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$field] = 'Enter a valid URL.';
                break;
            }
            if ($name === 'in' && !in_array($value, explode(',', (string) $arg), true)) {
                $errors[$field] = 'Choose a valid ' . strtolower($label) . '.';
                break;
            }
            if ($name === 'confirmed' && $value !== (string) ($data[$field . '_confirm'] ?? '')) {
                $errors[$field] = $label . ' confirmation does not match.';
                break;
            }
            if ($name === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                $errors[$field] = 'Use lowercase letters, numbers and hyphens only.';
                break;
            }
            if ($name === 'date' && strtotime($value) === false) {
                $errors[$field] = 'Enter a valid date.';
                break;
            }
            if ($name === 'phone' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $value)) {
                $errors[$field] = 'Enter a valid phone number.';
                break;
            }
        }
    }
    return $errors;
}

function password_errors(string $password): array
{
    $errors = [];
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include letters and numbers.';
    }
    return $errors;
}

function encrypt_secret(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }
    $key = hash('sha256', (string) config('app.key', 'ubos'), true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Could not encrypt secret.');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $stored): ?string
{
    if (!$stored) {
        return null;
    }
    if (!str_starts_with($stored, 'v1:')) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $key = hash('sha256', (string) config('app.key', 'ubos'), true);
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function verify_paystack_signature(string $rawBody, string $signature, string $secret): bool
{
    if ($secret === '' || $signature === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
}

function verify_flutterwave_signature(string $signature, string $hash): bool
{
    return $hash !== '' && $signature !== '' && hash_equals($hash, $signature);
}

function sanitize_filename(string $name): string
{
    $name = basename($name);
    return preg_replace('/[^a-zA-Z0-9._-]/', '-', $name) ?: 'file';
}

function password_hash_secure(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID);
}

function password_verify_secure(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}
