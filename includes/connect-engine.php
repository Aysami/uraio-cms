<?php
/**
 * Uraio Connect — embeddable widgets for third-party websites.
 *
 * A business creates an embed token in the dashboard. The token identifies the
 * business without revealing the database id. Widgets only show public data
 * (menu, products, reviews, hours) and public forms (contact, booking).
 * Submissions go straight into the business inbox / bookings.
 *
 * Never trust the token blindly:
 *  - Origins are allowlisted per business (empty = any origin, warned in UI).
 *  - Submissions are rate limited and honeypot-protected.
 *  - Only safe, public fields are rendered.
 */

function embed_generate_token(): string
{
    return bin2hex(random_bytes(24));
}

function embed_issue_token(int $businessId): string
{
    $token = embed_generate_token();
    db_update('businesses', ['embed_token' => $token, 'embed_enabled' => 1], 'id = :id', ['id' => $businessId]);
    return $token;
}

function embed_revoke_token(int $businessId): void
{
    db_update('businesses', ['embed_token' => null, 'embed_enabled' => 0], 'id = :id', ['id' => $businessId]);
}

function embed_business_by_token(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{48}$/i', $token)) {
        return null;
    }
    $business = db_row(
        "SELECT b.*, c.name AS category_name
         FROM businesses b LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.embed_token = ? AND b.embed_enabled = 1 AND b.deleted_at IS NULL AND b.status = 'active'",
        [strtolower($token)]
    );
    if (!$business) {
        return null;
    }
    // Only show for published or bundle-facing sites; a business with no website yet can still embed.
    return $business;
}

/**
 * Origins as a list. Empty means every origin is allowed (the dashboard warns).
 * Stored as JSON in businesses.embed_origins.
 */
function embed_origins(array $business): array
{
    $decoded = json_decode_assoc($business['embed_origins'] ?? null);
    $origins = [];
    foreach ($decoded as $origin) {
        $host = embed_normalize_origin((string) $origin);
        if ($host !== null) {
            $origins[] = $host;
        }
    }
    return array_values(array_unique($origins));
}

function embed_origins_set(array $strings): string
{
    $origins = [];
    foreach ($strings as $input) {
        $host = embed_normalize_origin(trim((string) $input));
        if ($host !== null) {
            $origins[] = $host;
        }
    }
    $origins = array_values(array_unique($origins));
    return json_encode($origins, JSON_UNESCAPED_SLASHES);
}

function embed_normalize_origin(string $input): ?string
{
    $input = strtolower(trim($input));
    if ($input === '' || str_contains($input, ' ')) {
        return null;
    }
    if (!str_contains($input, '://') && !str_starts_with($input, '*')) {
        $input = 'https://' . $input;
    }
    $scheme = parse_url($input, PHP_URL_SCHEME);
    $host = parse_url($input, PHP_URL_HOST);
    if (!$host) {
        // Allow a bare wildcard entry such as *.example.com
        $input = preg_replace('#^\*\.#', '*.', $input);
        $host = ltrim($input, '*.');
        if ($host === $input) {
            return null;
        }
        return '*.' . $host;
    }
    if (!in_array($scheme, ['https', 'http'], true)) {
        return null;
    }
    $isWildcard = str_starts_with($input, 'https://*.') || str_starts_with($input, 'http://*.');
    $host = preg_replace('#^www\.#', '', strtolower($host));
    $host = preg_replace('/:\d+$/', '', $host);
    return ($isWildcard ? '*.' : '') . $host;
}

function embed_origin_host(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? '') ?: '');
    if ($host === '') {
        return null;
    }
    return preg_replace('#^www\.#', '', preg_replace('/:\d+$/', '', $host));
}

/**
 * Normalize an Origin/Referer value and check it against $business allowlist.
 * Empty allowlist = every origin allowed. Wildcards *.example.com are supported.
 */
function embed_origin_allowed(array $business, ?string $originUrl): bool
{
    $origins = embed_origins($business);
    if (!$origins) {
        return true;
    }
    $host = embed_origin_host($originUrl);
    if ($host === null) {
        return false;
    }
    foreach ($origins as $allowed) {
        if (str_starts_with($allowed, '*.')) {
            $suffix = substr($allowed, 1); // ".example.com"
            if ($host === substr($allowed, 2) || str_ends_with($host, $suffix)) {
                return true;
            }
            continue;
        }
        if ($host === $allowed) {
            return true;
        }
    }
    return false;
}

/**
 * Check the request origin. Allowed when origins empty, referer/origin matches
 * the allowlist, or the requester is an authorised member of the business
 * (dashboard preview).
 */
function embed_request_allowed(array $business, string $refererInput = ''): bool
{
    $origins = embed_origins($business);
    if (!$origins) {
        return true;
    }
    $referer = $refererInput !== '' ? $refererInput : ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_ORIGIN'] ?? '');
    if (embed_origin_allowed($business, $referer)) {
        return true;
    }
    // Allow an authenticated member to preview its own widget.
    $user = current_user();
    if ($user && user_can_access_business((int) $business['id'], (int) $user['id'])) {
        return true;
    }
    return false;
}

/**
 * Brand visibility: hidden on plans with 5+ templates (Pro and above),
 * visible elsewhere. Free owners can keep the default; the Connect panel
 * explains which plan unlocks it.
 */
function embed_branding_hidden(array $business): bool
{
    $limits = business_limits($business);
    return (int) ($limits['templates'] ?? 0) >= 5;
}

function embed_connect_url(string $path = ''): string
{
    return app_url('connect/' . $path);
}

function embed_widget_url(array $business, string $widget): string
{
    return embed_connect_url('embed.php?t=' . rawurlencode((string) $business['embed_token']) . '&w=' . rawurlencode($widget));
}

function embed_js_snippet(array $business, string $widget): string
{
    return '<div class="uraio-widget" data-widget="' . htmlspecialchars($widget, ENT_QUOTES) . '" data-token="' . htmlspecialchars((string) $business['embed_token'], ENT_QUOTES) . '"></div>' . "\n"
        . '<script src="' . embed_connect_url('embed.js') . '" defer></script>';
}

function embed_iframe_snippet(array $business, string $widget): string
{
    return '<iframe src="' . e(embed_widget_url($business, $widget)) . '" loading="lazy" width="100%" height="420" style="border:0;border-radius:12px;" title="' . e($business['name']) . ' — ' . e(ucfirst($widget)) . '"></iframe>';
}

function embed_iframe_height(string $widget): int
{
    return match ($widget) {
        'menu', 'products' => 560,
        'booking' => 480,
        'reviews' => 520,
        'hours' => 320,
        'whatsapp' => 72,
        default => 420,
    };
}

function embed_shortcode_snippet(array $business, string $widget): string
{
    return '<div class="uraio-widget" data-widget="' . htmlspecialchars($widget, ENT_QUOTES) . '" data-token="' . htmlspecialchars((string) $business['embed_token'], ENT_QUOTES) . '"></div>';
}
