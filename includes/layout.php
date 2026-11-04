<?php
/**
 * Shared page chrome: document head, public header/footer, app shell.
 * All dynamic output is escaped with e().
 */

function layout_head(string $title, array $options = []): void
{
    $siteName = (string) setting('site_name', 'Uraio Business OS');
    $fullTitle = $title === '' ? $siteName : $title . ' · ' . $siteName;
    $description = (string) ($options['description'] ?? 'Run your business, manage customers and publish a professional website — all from one place.');
    $bodyClass = (string) ($options['body_class'] ?? '');
    $noindex = !empty($options['noindex']);
    $manifest = $options['manifest'] ?? 'manifest.php';
    $theme = (string) ($_COOKIE['ubos_theme'] ?? 'light');
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<meta name="theme-color" content="#0f766e">
<meta name="application-name" content="<?= e($siteName) ?>">
<link rel="manifest" href="<?= e(app_url($manifest)) ?>">
<link rel="icon" href="<?= e(app_url('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(app_url('assets/icons/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
<?php foreach (($options['styles'] ?? []) as $style): ?>
<link rel="stylesheet" href="<?= e(asset_url('css/' . $style)) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>" data-sw="<?= e(app_url('sw.js')) ?>" data-version="<?= e(UBOS_VERSION) ?>">
<?php
}

function layout_end(array $options = []): void
{
    $nonce = csp_nonce();
    ?>
<?php foreach (($options['scripts'] ?? []) as $script): ?>
<script src="<?= e(asset_url('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
<?php if (!empty($options['inline_script'])): ?>
<script nonce="<?= e($nonce) ?>"><?= $options['inline_script'] ?></script>
<?php endif; ?>
</body>
</html>
<?php
}

function layout_flashes(): void
{
    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $iconName = ['success' => 'check', 'danger' => 'alert', 'warning' => 'alert', 'info' => 'info'][$type];
        echo '<div class="alert alert-' . e($type) . '" role="status">' . icon($iconName) . '<div>' . e($flash['message']) . '</div></div>';
    }
}

function layout_public_nav(): void
{
    $user = current_user();
    ?>
<header class="landing-nav container">
  <a class="brand" href="<?= e(app_url('index.php')) ?>">
    <span class="brand-mark"><?= icon('sparkles') ?></span>
    <span class="brand-text">Uraio<span class="muted brand-suffix" style="font-weight:500"> Business OS</span></span>
  </a>
  <nav class="nav-links" aria-label="Main navigation">
    <a href="<?= e(app_url('index.php#features')) ?>" class="hide-mobile">Features</a>
    <a href="<?= e(app_url('index.php#categories')) ?>" class="hide-mobile">Categories</a>
    <a href="<?= e(app_url('index.php#pricing')) ?>" class="hide-mobile">Pricing</a>
    <a href="<?= e(app_url('index.php#faq')) ?>" class="hide-mobile">FAQ</a>
    <?php if ($user): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(app_url('app/dashboard.php')) ?>">Open dashboard</a>
    <?php else: ?>
      <a href="<?= e(app_url('auth/login.php')) ?>" class="hide-mobile">Sign in</a>
      <a class="btn btn-primary btn-sm" href="<?= e(app_url('auth/register.php')) ?>">Get started</a>
    <?php endif; ?>
  </nav>
</header>
<?php
}

function layout_public_footer(): void
{
    ?>
<footer class="landing-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a class="brand" href="<?= e(app_url('index.php')) ?>">
          <span class="brand-mark"><?= icon('sparkles') ?></span>
          <span>Uraio Business OS</span>
        </a>
        <p class="muted small" style="max-width:320px;margin-top:12px">The operating system for small businesses: customers, orders, bookings, payments and a website that grows with you.</p>
      </div>
      <div>
        <h3 class="footer-heading">Product</h3>
        <a href="<?= e(app_url('index.php#features')) ?>">Features</a>
        <a href="<?= e(app_url('index.php#categories')) ?>">Business categories</a>
        <a href="<?= e(app_url('index.php#pricing')) ?>">Pricing</a>
        <a href="<?= e(app_url('index.php#faq')) ?>">FAQ</a>
      </div>
      <div>
        <h3 class="footer-heading">Company</h3>
        <a href="<?= e(app_url('index.php#how')) ?>">How it works</a>
        <a href="<?= e(app_url('auth/register.php')) ?>">Create account</a>
        <a href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
        <a href="<?= e(app_url('site.php')) ?>">Business directory</a>
      </div>
      <div>
        <h3 class="footer-heading">Support</h3>
        <a href="mailto:<?= e((string) setting('support_email', 'support@uraio.com')) ?>">Email support</a>
        <a href="<?= e(app_url('index.php#faq')) ?>">Getting started</a>
        <a href="<?= e(app_url('index.php#pricing')) ?>">Plans &amp; billing</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e((string) setting('site_name', 'Uraio Business OS')) ?>. All rights reserved.</span>
      <span>Built for businesses in Nigeria and beyond.</span>
    </div>
  </div>
</footer>
<?php
}

function business_logo_html(array $business, string $class = 'avatar'): string
{
    if (!empty($business['logo'])) {
        return '<span class="' . e($class) . '"><img src="' . e(media_url($business['logo'])) . '" alt=""></span>';
    }
    return '<span class="' . e($class) . '">' . e(initials((string) $business['name'])) . '</span>';
}

function layout_app_start(array $business, string $activeKey, string $title, string $subtitle = ''): void
{
    layout_head($title, ['body_class' => 'app-body', 'noindex' => true, 'description' => 'Uraio Business OS dashboard']);
    $user = require_login();
    $businesses = user_businesses((int) $user['id']);
    $unread = 0;
    try {
        $unread = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$user['id']]);
    } catch (Throwable $e) {
    }
    $nav = module_nav($business);
    $website = business_website((int) $business['id']);
    $isPublished = ($website['status'] ?? '') === 'published';
    ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <a class="brand" href="<?= e(app_url('app/dashboard.php')) ?>">
        <span class="brand-mark brand-mark-sm"><?= icon('sparkles') ?></span>
        <span style="font-size:1rem">Uraio</span>
      </a>
      <button class="icon-btn mobile-menu-btn" type="button" data-sidebar-close aria-label="Close menu"><?= icon('x') ?></button>
    </div>
    <div class="sidebar-body">
      <div class="sidebar-label">Business</div>
      <button class="business-switcher" type="button" data-open-modal="business-switcher-modal" aria-haspopup="dialog">
        <?= business_logo_html($business, 'avatar') ?>
        <span class="business-switcher-meta">
          <strong><?= e($business['name']) ?></strong>
          <span><?= e($business['category_name'] ?? 'Business') ?></span>
        </span>
        <?= icon('chevron-down', 'icon icon-sm') ?>
      </button>

      <div class="sidebar-label">Workspace</div>
      <nav class="side-nav" aria-label="Workspace">
        <?php
        $seen = [];
        foreach ($nav as $item):
            if (isset($seen[$item['key']])) {
                continue;
            }
            $seen[$item['key']] = true;
            $isActive = $activeKey === $item['key'];
            ?>
          <a class="side-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(app_url($item['url'])) ?>">
            <?= icon($item['icon'] ?? 'grid') ?><span><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar-label">Website</div>
      <nav class="side-nav" aria-label="Website">
        <a class="side-link<?= $activeKey === 'website' ? ' is-active' : '' ?>" href="<?= e(app_url('app/website.php')) ?>">
          <?= icon('globe') ?><span>Website</span>
          <?= status_badge($website['status'] ?? 'draft') ?>
        </a>
        <a class="side-link<?= $activeKey === 'pages' ? ' is-active' : '' ?>" href="<?= e(app_url('app/pages.php')) ?>">
          <?= icon('layout') ?><span>Pages</span>
        </a>
        <a class="side-link<?= $activeKey === 'appearance' ? ' is-active' : '' ?>" href="<?= e(app_url('app/appearance.php')) ?>">
          <?= icon('image') ?><span>Appearance</span>
        </a>
        <a class="side-link<?= $activeKey === 'domains' ? ' is-active' : '' ?>" href="<?= e(app_url('app/domains.php')) ?>">
          <?= icon('globe') ?><span>Domains</span>
        </a>
        <a class="side-link<?= $activeKey === 'connect' ? ' is-active' : '' ?>" href="<?= e(app_url('app/connect.php')) ?>">
          <?= icon('code') ?><span>Connect</span>
        </a>
      </nav>

      <div class="sidebar-label">Business</div>
      <nav class="side-nav" aria-label="Business">
        <a class="side-link<?= $activeKey === 'analytics' ? ' is-active' : '' ?>" href="<?= e(app_url('app/analytics.php')) ?>">
          <?= icon('chart') ?><span>Analytics</span>
        </a>
        <a class="side-link<?= $activeKey === 'staff' ? ' is-active' : '' ?>" href="<?= e(app_url('app/staff.php')) ?>">
          <?= icon('users') ?><span>Team</span>
        </a>
        <a class="side-link<?= $activeKey === 'billing' ? ' is-active' : '' ?>" href="<?= e(app_url('app/billing.php')) ?>">
          <?= icon('credit-card') ?><span>Billing</span>
        </a>
        <a class="side-link<?= $activeKey === 'settings' ? ' is-active' : '' ?>" href="<?= e(app_url('app/settings.php')) ?>">
          <?= icon('settings') ?><span>Settings</span>
        </a>
      </nav>
    </div>
    <div class="sidebar-foot">
      <a class="side-link" href="<?= e(app_url('app/profile.php')) ?>">
        <span class="avatar"><?= e(initials((string) $user['name'])) ?></span>
        <span style="min-width:0">
          <strong class="truncate" style="display:block;font-size:.82rem"><?= e($user['name']) ?></strong>
          <span class="muted" style="font-size:.7rem"><?= e($user['role_name'] ?? 'Account') ?></span>
        </span>
      </a>
    </div>
  </aside>
  <div class="sidebar-backdrop" data-sidebar-close></div>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn mobile-menu-btn" type="button" data-sidebar-open aria-label="Open menu"><?= icon('menu') ?></button>
      <div class="topbar-title"><?= e($title) ?><?php if ($subtitle !== ''): ?><span><?= e($subtitle) ?></span><?php endif; ?></div>
      <div class="topbar-actions">
        <?php if ($website): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener">
            <?= icon('external', 'icon icon-sm') ?> View site
          </a>
        <?php endif; ?>
        <a class="icon-btn" href="<?= e(app_url('app/notifications.php')) ?>" aria-label="Notifications">
          <?= icon('bell') ?>
          <?php if ($unread > 0): ?><span class="dot"></span><?php endif; ?>
        </a>
        <form method="post" action="<?= e(app_url('auth/logout.php')) ?>" style="display:inline">
          <?= csrf_field() ?>
          <button class="icon-btn" type="submit" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button>
        </form>
      </div>
    </header>
    <main class="content" id="main">
      <?php layout_flashes(); ?>
<?php
}

function layout_app_end(array $options = []): void
{
    $business = active_business();
    ?>
    </main>
  </div>
</div>

<?php if ($business): ?>
<div class="modal-backdrop" id="business-switcher-modal" role="dialog" aria-modal="true" aria-labelledby="switcher-title">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2 id="switcher-title" class="mt-0">Switch business</h2>
      <button class="icon-btn" type="button" data-close-modal aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="side-nav">
      <?php foreach (user_businesses() as $item): ?>
        <a class="side-link<?= (int) $item['id'] === (int) $business['id'] ? ' is-active' : '' ?>" href="<?= e(app_url('app/dashboard.php?business=' . $item['id'])) ?>">
          <?= business_logo_html($item, 'avatar') ?>
          <span style="min-width:0"><strong class="truncate" style="display:block"><?= e($item['name']) ?></strong><span class="muted small"><?= e($item['category_name'] ?? 'Business') ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="divider"></div>
    <a class="btn btn-soft btn-block" href="<?= e(app_url('app/setup.php')) ?>"><?= icon('plus', 'icon icon-sm') ?> Add another business</a>
  </div>
</div>
<?php endif; ?>

<div class="toast-stack" id="toast-stack" aria-live="polite"></div>
<?php
    layout_end($options);
}

function safe_intended_path(string $fallback = 'app/dashboard.php'): string
{
    $intended = (string) ($_SESSION['_intended'] ?? '');
    unset($_SESSION['_intended']);
    if ($intended === '' || !str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
        return app_url($fallback);
    }
    // Only allow paths inside this installation.
    $basePath = rtrim((string) parse_url(app_url(''), PHP_URL_PATH), '/');
    if ($basePath !== '' && !str_starts_with($intended, $basePath . '/') && $intended !== $basePath) {
        return app_url($fallback);
    }
    return $intended;
}

function render_field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
}

function auth_page_start(string $title, string $subtitle, string $heading = 'Run your business from one place.', array $bullets = []): void
{
    layout_head($title, ['body_class' => 'auth-body', 'noindex' => true]);
    $bullets = $bullets ?: [
        'A category-aware dashboard from day one',
        'Customers, orders, bookings and invoices',
        'A publishable website on your own domain',
        'Secure, tenant-isolated records',
    ];
    ?>
<div class="auth-split">
  <aside class="auth-aside">
    <div>
      <a class="brand" href="<?= e(app_url('index.php')) ?>">
        <span class="brand-mark"><?= icon('sparkles') ?></span>
        <span>Uraio Business OS</span>
      </a>
      <h2><?= e($heading) ?></h2>
      <p>Everything your business needs to take orders, serve customers and look professional online.</p>
      <ul class="check-list mt-3">
        <?php foreach ($bullets as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <p class="small" style="color:rgba(255,255,255,.6)">© <?= date('Y') ?> Uraio Business OS</p>
  </aside>
  <div class="auth-form-side">
    <a class="brand" href="<?= e(app_url('index.php')) ?>">
      <span class="brand-mark brand-mark-sm"><?= icon('sparkles') ?></span>
      <span>Uraio</span>
    </a>
    <h1><?= e($title) ?></h1>
    <p class="auth-sub"><?= e($subtitle) ?></p>
    <?php layout_flashes(); ?>
<?php
}

function auth_page_end(string $footHtml = ''): void
{
    ?>
    <?php if ($footHtml !== ''): ?><div class="auth-foot"><?= $footHtml ?></div><?php endif; ?>
  </div>
</div>
<?php
    layout_end(['scripts' => ['app.js']]);
}

function business_meta(array $business): array
{
    return [
        'name' => (string) ($business['name'] ?? ''),
        'slug' => (string) ($business['slug'] ?? ''),
        'category' => (string) ($business['category_name'] ?? 'Business'),
        'description' => (string) ($business['description'] ?? ''),
        'email' => (string) ($business['email'] ?? ''),
        'phone' => (string) ($business['phone'] ?? ''),
        'whatsapp' => (string) ($business['whatsapp'] ?? ''),
        'address' => (string) ($business['address'] ?? ''),
        'city' => (string) ($business['city'] ?? ''),
        'state' => (string) ($business['state'] ?? ''),
        'country' => (string) ($business['country'] ?? 'Nigeria'),
        'logo' => (string) ($business['logo'] ?? ''),
        'cover' => (string) ($business['cover'] ?? ''),
    ];
}
