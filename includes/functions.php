<?php
/**
 * Shared helpers used across the platform.
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    $url = preg_match('#^https?://#i', $path) ? $path : app_url($path);
    header('Location: ' . $url);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function query(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json') || strtolower($requested) === 'xmlhttprequest';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function old(string $key, mixed $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function remember_old(array $input): void
{
    unset($input['password'], $input['password_confirm'], $input['csrf_token']);
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function input_error(string $key): ?string
{
    return $_SESSION['_errors'][$key] ?? null;
}

function set_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function take_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function slugify(string $text, string $fallback = 'business'): string
{
    $text = strtolower(trim($text));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        $text = $fallback . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }
    return substr($text, 0, 80);
}

/**
 * Slugs that would collide with application routes, real files/directories or
 * reserved platform pages. Public sites now live at /{slug}, so these can
 * never be handed to a business.
 */
function reserved_business_slugs(): array
{
    return [
        // Real directories and files in the web root.
        'admin', 'api', 'app', 'assets', 'auth', 'cgi-bin', 'config', 'connect',
        'deploy', 'docs', 'includes', 'logs', 'modules', 'plugins', 'storage',
        'tests', 'uploads', 'well-known',
        'index', 'site', 'install', 'manifest', 'robots', 'sitemap', 'sw', 'offline',
        // Platform and account routes.
        'login', 'logout', 'register', 'signup', 'signin', 'signout', 'account',
        'dashboard', 'onboarding', 'setup', 'billing', 'pricing', 'plans', 'features',
        'categories', 'category', 'help', 'support', 'about', 'contact', 'terms',
        'privacy', 'cookies', 'legal', 'blog', 'news', 'careers', 'status', 'faq',
        'search', 'settings', 'profile', 'notifications', 'messages', 'inbox',
        'webhooks', 'webhook', 'cdn', 'static', 'media', 'images', 'files',
        'download', 'downloads', 'preview', 's', 'www', 'mail', 'ftp', 'email',
        'uraio', 'create',
    ];
}

function business_slug_is_reserved(string $slug): bool
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return true;
    }
    return in_array($slug, reserved_business_slugs(), true);
}

function unique_slug(string $table, string $column, string $value, ?int $ignoreId = null): string
{
    $base = slugify($value);
    $slug = $base;
    $i = 2;
    while (true) {
        $reserved = $table === 'businesses' && $column === 'slug' && business_slug_is_reserved($slug);
        if (!$reserved) {
            $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
            $params = [$slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            if ((int) db_val($sql, $params) === 0) {
                return $slug;
            }
        }
        $slug = $base . '-' . $i++;
    }
}

function money(float|int|string|null $amount, string $currency = 'NGN'): string
{
    $symbols = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh'];
    $symbol = $symbols[$currency] ?? ($currency . ' ');
    return $symbol . number_format((float) $amount, 2);
}

function nice_date(?string $date, string $format = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function nice_datetime(?string $date): string
{
    return nice_date($date, 'M j, Y g:ia');
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : 'U';
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $value = db_val('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    } catch (Throwable $e) {
        return $default;
    }
    return $cache[$key] = ($value === null ? $default : $value);
}

function set_setting(string $key, mixed $value, string $group = 'general', bool $public = false): void
{
    $existing = db_val('SELECT id FROM settings WHERE setting_key = ?', [$key]);
    if ($existing) {
        db_update('settings', ['setting_value' => (string) $value, 'is_public' => (int) $public], 'id = :id', ['id' => $existing]);
        return;
    }
    db_insert('settings', [
        'setting_key' => $key,
        'setting_value' => (string) $value,
        'setting_group' => $group,
        'is_public' => (int) $public,
    ]);
}

function activity_log(string $action, ?string $entityType = null, mixed $entityId = null, array $meta = [], ?int $businessId = null): void
{
    try {
        $user = current_user();
        db_insert('activity_logs', [
            'business_id' => $businessId ?? (active_business()['id'] ?? null),
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'meta' => $meta ? json_encode($meta) : null,
            'ip' => client_ip(),
        ]);
    } catch (Throwable $e) {
        // Logging must never break a request.
        error_log('activity_log failed: ' . $e->getMessage());
    }
}

function notify_user(int $userId, string $title, string $body = '', string $url = '', ?int $businessId = null, string $type = 'system'): void
{
    db_insert('notifications', [
        'user_id' => $userId,
        'business_id' => $businessId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'url' => $url,
    ]);
}

function notify_business_owners(int $businessId, string $title, string $body = '', string $url = '', string $type = 'system'): void
{
    $members = db_all("SELECT user_id FROM business_members WHERE business_id = ? AND status = 'active' AND role IN ('owner','manager')", [$businessId]);
    foreach ($members as $member) {
        notify_user((int) $member['user_id'], $title, $body, $url, $businessId, $type);
    }
}

function paginate(int $total, int $perPage = 20, ?int $page = null): array
{
    $perPage = max(1, min(100, $perPage));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, $page ?? (int) ($_GET['page'] ?? 1)));
    return [
        'total' => $total,
        'per_page' => $perPage,
        'page' => $page,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function pagination_links(array $pagination, string $baseUrl): string
{
    if ($pagination['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    for ($i = 1; $i <= $pagination['pages']; $i++) {
        if ($pagination['pages'] > 9 && $i !== 1 && $i !== $pagination['pages'] && abs($i - $pagination['page']) > 2) {
            if (abs($i - $pagination['page']) === 3) {
                $html .= '<span class="pagination-gap">…</span>';
            }
            continue;
        }
        $active = $i === $pagination['page'] ? ' is-active' : '';
        $html .= '<a class="pagination-link' . $active . '" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function upload_image(string $field, string $folder = 'general', int $maxBytes = 5_242_880): ?string
{
    return upload_image_file($_FILES[$field] ?? null, $folder, $maxBytes);
}

function upload_image_file(?array $file, string $folder = 'general', int $maxBytes = 5_242_880): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('File is too large. Maximum size is ' . round($maxBytes / 1048576, 1) . ' MB.');
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WebP, GIF or SVG images are allowed.');
    }
    if ($mime === 'image/svg+xml') {
        $raw = file_get_contents($file['tmp_name']);
        if ($raw === false || preg_match('/<script|onload=|onerror=/i', $raw)) {
            throw new RuntimeException('This SVG contains unsafe content.');
        }
    }
    $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'general';
    $dir = BASE_PATH . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload directory is not writable.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    @chmod($dir . '/' . $name, 0644);
    return 'uploads/' . $folder . '/' . $name;
}

function upload_document(string $field, string $folder = 'documents', int $maxBytes = 10_485_760): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Invalid or oversized file.');
    }
    $allowed = ['application/pdf' => 'pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only PDF files are allowed.');
    }
    $dir = BASE_PATH . '/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.pdf';
    move_uploaded_file($file['tmp_name'], $dir . '/' . $name);
    return 'uploads/' . $folder . '/' . $name;
}

function asset_url(string $path): string
{
    return app_url('assets/' . ltrim($path, '/')) . '?v=' . rawurlencode(UBOS_VERSION);
}

function media_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return app_url($path);
}

/**
 * The canonical public base URL for a business website.
 * Order: verified custom domain → subdomain → pretty path → legacy /s/ path.
 */
function business_public_base(array $business): string
{
    $primary = null;
    if (!empty($business['id'])) {
        try {
            $primary = db_row("SELECT * FROM website_domains WHERE business_id = ? AND is_primary = 1 AND status = 'verified' LIMIT 1", [$business['id']]);
        } catch (Throwable $e) {
            $primary = null;
        }
    }
    if ($primary) {
        return 'https://' . $primary['domain'];
    }
    $slug = (string) ($business['slug'] ?? '');
    if (public_url_mode() === 'subdomain') {
        return 'https://' . $slug . '.' . site_domain();
    }
    if (public_url_mode() === 'path_s') {
        return app_url('s/' . $slug);
    }
    return app_url($slug);
}

function business_site_url(array $business, string $path = ''): string
{
    $base = business_public_base($business);
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function business_public_url(array $business, string $path = ''): string
{
    return business_site_url($business, $path);
}

function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'published' => 'success', 'paid' => 'success', 'successful' => 'success',
        'confirmed' => 'success', 'completed' => 'success', 'verified' => 'success', 'approved' => 'success',
        'available' => 'success', 'connected' => 'success', 'enrolled' => 'success',
        'pending' => 'warning', 'draft' => 'neutral', 'unpaid' => 'warning', 'trialing' => 'warning',
        'processing' => 'warning', 'in_progress' => 'warning', 'partial' => 'warning', 'open' => 'warning',
        'new' => 'info', 'sent' => 'info', 'read' => 'info', 'upcoming' => 'info', 'ongoing' => 'info',
        'inactive' => 'neutral', 'unpublished' => 'neutral', 'inactive' => 'neutral', 'archived' => 'neutral',
        'cancelled' => 'danger', 'failed' => 'danger', 'expired' => 'danger', 'overdue' => 'danger',
        'suspended' => 'danger', 'rejected' => 'danger', 'past_due' => 'danger', 'no_show' => 'danger',
        'maintenance' => 'warning', 'sold' => 'neutral', 'rented' => 'neutral', 'full' => 'danger',
        'awaiting_parts' => 'warning', 'checked_in' => 'info', 'replied' => 'info', 'resolved' => 'success',
    ];
    $tone = $map[$status] ?? 'neutral';
    return '<span class="badge badge-' . $tone . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function currency_options(): array
{
    return ['NGN' => 'Nigerian Naira (₦)', 'USD' => 'US Dollar ($)', 'GBP' => 'British Pound (£)', 'EUR' => 'Euro (€)', 'GHS' => 'Ghanaian Cedi (GH₵)', 'KES' => 'Kenyan Shilling (KSh)'];
}

function country_options(): array
{
    return ['Nigeria', 'Ghana', 'Kenya', 'South Africa', 'United Kingdom', 'United States', 'Canada', 'United Arab Emirates', 'Other'];
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function generate_reference(string $prefix = 'UB'): string
{
    return $prefix . '-' . strtoupper(date('ymd')) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function json_decode_assoc(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function str_limit(?string $text, int $limit = 120): string
{
    $text = trim((string) $text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? 'selected' : '';
}

function checked(mixed $value): string
{
    return $value ? 'checked' : '';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}
