<?php
/**
 * Multi-tenant resolution. Never trust a business id supplied by the client
 * without checking membership on the server.
 */

function user_businesses(?int $userId = null): array
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }
    return db_all(
        "SELECT b.*, bm.role AS member_role, c.name AS category_name, c.icon AS category_icon,
                w.status AS website_status, w.id AS website_id
         FROM businesses b
         JOIN business_members bm ON bm.business_id = b.id AND bm.status = 'active'
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN business_websites w ON w.business_id = b.id
         WHERE bm.user_id = ? AND b.deleted_at IS NULL
         ORDER BY b.created_at ASC",
        [$userId]
    );
}

function user_can_access_business(int $businessId, ?int $userId = null): bool
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId <= 0 || $businessId <= 0) {
        return false;
    }
    return (bool) db_val(
        "SELECT COUNT(*) FROM business_members bm
         JOIN businesses b ON b.id = bm.business_id
         WHERE bm.business_id = ? AND bm.user_id = ? AND bm.status = 'active' AND b.deleted_at IS NULL",
        [$businessId, $userId]
    );
}

function active_business(): ?array
{
    static $cached = null;
    static $resolved = false;
    if ($resolved) {
        return $cached;
    }
    $resolved = true;
    $user = current_user();
    if (!$user) {
        return null;
    }
    $businesses = user_businesses((int) $user['id']);
    if (!$businesses) {
        return null;
    }
    $requested = (int) ($_GET['business'] ?? $_POST['business_id'] ?? $_SESSION['active_business_id'] ?? 0);
    $chosen = null;
    foreach ($businesses as $business) {
        if ($requested > 0 && (int) $business['id'] === $requested) {
            $chosen = $business;
            break;
        }
    }
    if (!$chosen) {
        $chosen = $businesses[0];
    }
    $_SESSION['active_business_id'] = (int) $chosen['id'];
    $cached = $chosen;
    return $cached;
}

function require_business(): array
{
    require_login();
    $business = active_business();
    if (!$business) {
        flash('info', 'Create your first business to continue.');
        redirect('app/setup.php');
    }
    return $business;
}

function switch_business(int $businessId): bool
{
    if (!user_can_access_business($businessId)) {
        return false;
    }
    $_SESSION['active_business_id'] = $businessId;
    return true;
}

/**
 * Verify the active business is the requested tenant and that the member
 * has at least the required role. Used by module controllers.
 */
function tenant_guard(int $businessId, array $roles = ['owner', 'manager']): array
{
    $user = require_login();
    $business = db_row(
        "SELECT b.*, bm.role AS member_role FROM businesses b
         JOIN business_members bm ON bm.business_id = b.id
         WHERE b.id = ? AND bm.user_id = ? AND bm.status = 'active' AND b.deleted_at IS NULL",
        [$businessId, $user['id']]
    );
    if (!$business) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Business not available', 'You do not have access to this business.');
        exit;
    }
    if ($roles && !in_array($business['member_role'], $roles, true) && !is_admin_user($user)) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Permission required', 'Your business role does not allow this action.');
        exit;
    }
    return $business;
}

/**
 * Resolve a business for public website rendering by subdomain, custom domain
 * or local preview path. Published websites only, unless preview is authorised.
 */
function resolve_public_business(): ?array
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $tenant = trim((string) ($_GET['__tenant'] ?? $_GET['b'] ?? ''));
    $domain = null;

    if ($tenant === '' && $host !== '') {
        $base = strtolower(site_domain());
        if ($host !== $base && str_ends_with($host, '.' . $base)) {
            $tenant = substr($host, 0, -strlen('.' . $base));
        } elseif ($host !== 'localhost' && $host !== '127.0.0.1' && !str_ends_with($host, '.local')) {
            $domain = db_row("SELECT * FROM website_domains WHERE domain = ? AND status = 'verified' LIMIT 1", [$host]);
        }
    }

    if ($domain) {
        return db_row("SELECT b.*, w.id AS website_id FROM businesses b JOIN business_websites w ON w.business_id = b.id WHERE b.id = ? AND b.deleted_at IS NULL", [$domain['business_id']]);
    }

    $tenant = preg_replace('/[^a-z0-9-]/', '', strtolower($tenant));
    if ($tenant === '') {
        return null;
    }
    return db_row(
        "SELECT b.*, w.id AS website_id FROM businesses b
         JOIN business_websites w ON w.business_id = b.id
         WHERE b.slug = ? AND b.deleted_at IS NULL LIMIT 1",
        [$tenant]
    );
}
