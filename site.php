<?php
/**
 * Public business website renderer.
 *
 * Resolves a business from a verified custom domain, a subdomain, or the
 * pretty /{slug} path (the legacy /s/{slug} form still works). Draft previews
 * require an authenticated member.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/site-render.php';

$preview = isset($_GET['preview']) && current_user();
$website = null;
$business = null;
$notFound = false;

if ($preview) {
    $business = active_business();
    if ($business) {
        $website = business_website((int) $business['id']);
    }
    if (!$business || !$website) {
        $notFound = true;
    }
} else {
    if (!isset($_GET['__tenant']) && isset($_GET['tenant'])) {
        $_GET['__tenant'] = (string) $_GET['tenant'];
    }
    $business = resolve_public_business();
    if ($business && $business['status'] === 'active') {
        $website = business_website((int) $business['id']);
        if (!$website || $website['status'] !== 'published') {
            $business = null;
        }
    } else {
        $business = null;
    }
    if (!$business) {
        $notFound = true;
    }
}

if ($notFound || !$business || !$website) {
    http_response_code(404);
    $siteName = setting('site_name', 'Uraio Business OS');
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Website not available · <?= e($siteName) ?></title><meta name="robots" content="noindex">
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b1220;color:#e7efec;font-family:system-ui,sans-serif;text-align:center;padding:24px}main{max-width:520px}a{color:#5eead4;font-weight:600}.mark{width:52px;height:52px;border-radius:14px;background:#0f766e;display:grid;place-items:center;margin:0 auto 20px;font-weight:800;font-size:22px}</style>
</head><body><main><div class="mark">U</div><h1>This website is not live</h1><p>The address may be incorrect, or the owner has not published the website yet.</p><p><a href="<?= e(app_url('index.php')) ?>">Go to <?= e($siteName) ?></a></p></main></body></html><?php
    exit;
}

$businessId = (int) $business['id'];
$business['category_name'] = (string) (db_val('SELECT name FROM categories WHERE id = ?', [$business['category_id']]) ?: 'Business');
$website['template_slug'] = (string) ($website['template_slug'] ?? 'aurora');
$pages = $preview
    ? website_pages($businessId)
    : db_all("SELECT * FROM website_pages WHERE business_id = ? AND status = 'published' ORDER BY sort_order ASC, id ASC", [$businessId]);
$home = website_home_page($businessId);
if (!$home) {
    $home = $pages[0] ?? null;
}

$requestedSlug = trim((string) query('page', ''));
if ($requestedSlug === '' || $requestedSlug === 'home') {
    $page = $home;
} else {
    $page = null;
    foreach ($pages as $candidate) {
        if ($candidate['slug'] === $requestedSlug) {
            $page = $candidate;
            break;
        }
    }
}
if (!$page) {
    http_response_code(404);
    $page = null;
}

$pageSlug = $page ? (string) $page['slug'] : 'home';
$formAction = $preview
    ? app_url('site.php?preview=1&page=' . urlencode($pageSlug))
    : business_site_url($business, $pageSlug === 'home' ? '' : $pageSlug);
$context = [
    'preview' => $preview,
    'form_action' => $formAction,
    'form_type' => (string) query('sent', ''),
    'form_success' => in_array(query('sent'), ['contact', 'booking'], true),
    'form_errors' => [],
    'form_old' => [],
];

if (is_post() && $page) {
    verify_csrf();
    $formType = (string) post('site_form', '');
    if (in_array($formType, ['contact', 'booking'], true)) {
        $context['form_type'] = $formType;
        $context['form_old'] = [
            'name' => trim((string) post('name', '')),
            'email' => trim((string) post('email', '')),
            'phone' => trim((string) post('phone', '')),
            'subject' => trim((string) post('subject', '')),
            'message' => trim((string) post('message', '')),
            'booking_date' => trim((string) post('booking_date', '')),
            'start_time' => trim((string) post('start_time', '')),
            'party_size' => trim((string) post('party_size', '')),
            'notes' => trim((string) post('notes', '')),
        ];
        $errors = [];
        if (trim((string) post('website', '')) !== '') {
            // Honeypot: behave like a successful submission without writing anything.
            redirect($formAction . (str_contains($formAction, '?') ? '&' : '?') . 'sent=' . $formType . '#' . $formType);
        }
        if (!rate_limit('site-' . $formType . '-' . $businessId . '-' . client_ip(), 5, 900)) {
            $errors['form'] = 'Too many submissions. Please wait a few minutes and try again.';
        }
        $old = $context['form_old'];
        if ($old['name'] === '' || mb_strlen($old['name']) < 2) {
            $errors['name'] = 'Enter your full name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($formType === 'contact') {
            if (mb_strlen($old['message']) < 10) {
                $errors['message'] = 'Please write a short message (at least 10 characters).';
            }
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $old['booking_date']);
            if (!$date || $date->format('Y-m-d') !== $old['booking_date'] || $old['booking_date'] < date('Y-m-d')) {
                $errors['booking_date'] = 'Choose today or a future date.';
            }
            if ($old['start_time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $old['start_time'])) {
                $errors['start_time'] = 'Choose a valid time.';
            }
            $party = (int) $old['party_size'];
            if ($party < 1 || $party > 500) {
                $errors['party_size'] = 'Enter a party size between 1 and 500.';
            }
        }
        if ($errors) {
            $context['form_errors'] = $errors;
        } else {
            if ($formType === 'contact') {
                $messageId = db_insert('messages', [
                    'business_id' => $businessId,
                    'name' => str_limit($old['name'], 190),
                    'email' => str_limit($old['email'], 190),
                    'phone' => str_limit($old['phone'], 40) ?: null,
                    'subject' => str_limit($old['subject'], 190) ?: 'Website enquiry',
                    'body' => str_limit($old['message'], 5000),
                    'status' => 'new',
                ]);
                notify_business_owners(
                    $businessId,
                    'New website enquiry',
                    $old['name'] . ' sent a message from your website.',
                    'app/records.php?entity=messages&action=edit&id=' . $messageId,
                    'message'
                );
                activity_log('website.contact', 'message', $messageId, [], $businessId);
            } else {
                $reference = generate_reference('BKG');
                $bookingId = db_insert('bookings', [
                    'business_id' => $businessId,
                    'reference' => $reference,
                    'customer_name' => str_limit($old['name'], 190),
                    'customer_email' => str_limit($old['email'], 190),
                    'customer_phone' => str_limit($old['phone'], 40) ?: null,
                    'booking_date' => $old['booking_date'],
                    'start_time' => $old['start_time'] ?: null,
                    'party_size' => (int) $old['party_size'],
                    'status' => 'pending',
                    'notes' => str_limit($old['notes'], 2000) ?: null,
                    'meta' => json_encode(['source' => 'website']),
                ]);
                notify_business_owners(
                    $businessId,
                    'New booking request',
                    $old['name'] . ' requested a booking for ' . nice_date($old['booking_date']) . '.',
                    'app/records.php?entity=bookings&action=edit&id=' . $bookingId,
                    'booking'
                );
                activity_log('website.booking', 'booking', $bookingId, [], $businessId);
            }
            redirect($formAction . (str_contains($formAction, '?') ? '&' : '?') . 'sent=' . $formType . '#' . $formType);
        }
    }
}

$pageTitle = $page ? (string) ($page['meta_title'] ?: $page['title']) : 'Page not found';
$siteName = (string) ($website['seo_title'] ?: $business['name']);
$title = $page && (int) $page['is_home'] === 1 ? $siteName : $pageTitle . ' · ' . $business['name'];
$description = (string) ($page['meta_description'] ?? '') ?: ((string) ($website['seo_description'] ?? '') ?: str_limit((string) ($business['description'] ?? ''), 300));
$ogImage = (string) ($website['og_image'] ?: $business['cover'] ?: $business['logo'] ?: '');
$canonical = $preview ? $formAction : business_site_url($business, $pageSlug === 'home' ? '' : $pageSlug);
$fontHeading = (string) ($website['font_heading'] ?? 'Sora');
$fontBody = (string) ($website['font_body'] ?? 'Inter');
$allowedFonts = ['Sora', 'Inter', 'Manrope', 'Space Grotesk', 'DM Serif Display', 'Playfair Display', 'Lora'];
if (!in_array($fontHeading, $allowedFonts, true)) {
    $fontHeading = 'Sora';
}
if (!in_array($fontBody, $allowedFonts, true)) {
    $fontBody = 'Inter';
}
$sections = $page ? website_sections((int) $page['id'], true) : [];
$socials = json_decode_assoc($business['socials'] ?? null);
$nonce = csp_nonce();

if (!$preview && $page) {
    try {
        $viewKey = 'site_view_' . $businessId . '_' . $page['id'];
        if (empty($_SESSION[$viewKey]) || (time() - (int) $_SESSION[$viewKey]) > 1800) {
            db_insert('website_visits', [
                'business_id' => $businessId,
                'path' => '/' . ($pageSlug === 'home' ? '' : $pageSlug),
                'referrer' => str_limit((string) ($_SERVER['HTTP_REFERER'] ?? ''), 500) ?: null,
                'user_agent' => str_limit((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255) ?: null,
                'ip_hash' => hash('sha256', client_ip() . '|' . (string) config('app.key', 'ubos') . '|' . date('Y-m-d')),
            ]);
            $_SESSION[$viewKey] = time();
        }
    } catch (Throwable $e) {
        // Analytics must never break the public website.
    }
}

$primary = (string) ($website['primary_color'] ?: '#0f766e');
$secondary = (string) ($website['secondary_color'] ?: '#0b1220');
$accent = (string) ($website['accent_color'] ?: '#f59e0b');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($preview): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= e(media_url($ogImage)) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="theme-color" content="<?= e($primary) ?>">
<link rel="icon" href="<?= e(!empty($business['logo']) ? media_url($business['logo']) : app_url('assets/icons/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= e(str_replace(' ', '+', $fontHeading)) ?>:wght@400;500;600;700;800&family=<?= e(str_replace(' ', '+', $fontBody)) ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('css/site.css')) ?>">
<style>:root{--site-primary:<?= e($primary) ?>;--site-secondary:<?= e($secondary) ?>;--site-accent:<?= e($accent) ?>;--site-heading:'<?= e($fontHeading) ?>',serif;--site-body:'<?= e($fontBody) ?>',sans-serif}</style>
<?php if ($page): ?>
<script type="application/ld+json" nonce="<?= e($nonce) ?>"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $business['name'],
    'description' => str_limit((string) ($business['description'] ?? ''), 300),
    'url' => $canonical,
    'telephone' => $business['phone'] ?: null,
    'email' => $business['email'] ?: null,
    'image' => $ogImage ? media_url($ogImage) : null,
    'address' => array_filter([
        '@type' => 'PostalAddress',
        'streetAddress' => $business['address'] ?: null,
        'addressLocality' => $business['city'] ?: null,
        'addressRegion' => $business['state'] ?: null,
        'addressCountry' => $business['country'] ?: null,
    ]),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="site-body tpl-<?= e($website['template_slug']) ?>">
<header class="site-header">
  <div class="site-container site-header-inner">
    <a class="site-brand" href="<?= e(site_page_url($business, 'home', $preview)) ?>">
      <?php if (!empty($business['logo'])): ?>
        <img src="<?= e(media_url($business['logo'])) ?>" alt="<?= e($business['name']) ?>">
      <?php else: ?>
        <span class="site-brand-mark"><?= e(initials((string) $business['name'])) ?></span>
        <span class="site-brand-name"><?= e($business['name']) ?></span>
      <?php endif; ?>
    </a>
    <input type="checkbox" id="site-nav-toggle" class="site-nav-toggle" aria-hidden="true">
    <label for="site-nav-toggle" class="site-nav-toggle-label" aria-label="Open navigation"><span></span><span></span><span></span></label>
    <nav class="site-nav" aria-label="Main navigation">
      <?php foreach ($pages as $navPage): ?>
        <a href="<?= e(site_page_url($business, (string) $navPage['slug'], $preview)) ?>"<?= (int) $navPage['id'] === (int) ($page['id'] ?? 0) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($navPage['title']) ?></a>
      <?php endforeach; ?>
      <?php if (site_whatsapp_url($business) !== ''): ?>
        <a class="site-nav-cta" href="<?= e(site_whatsapp_url($business, 'Hello ' . $business['name'] . '!')) ?>" target="_blank" rel="noopener">WhatsApp us</a>
      <?php elseif (!empty($business['phone'])): ?>
        <a class="site-nav-cta" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $business['phone'])) ?>">Call us</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($preview): ?>
<div class="preview-bar"><strong>Preview</strong><span>This is a private draft preview.</span><a href="<?= e(app_url('app/editor.php?page=' . ($page['id'] ?? 0))) ?>">Back to editor</a></div>
<?php endif; ?>

<?php if (!$page): ?>
<main class="site-main"><section class="site-section"><div class="site-container narrow" style="text-align:center;padding:80px 20px"><h1>Page not found</h1><p>The page you are looking for does not exist on this website.</p><a class="site-btn site-btn-primary" href="<?= e(site_page_url($business, 'home', $preview)) ?>">Back to home</a></div></section></main>
<?php else: ?>
<main class="site-main">
  <?php foreach ($sections as $section): ?>
    <?= site_render_section($section, $business, $website, $context) ?>
  <?php endforeach; ?>
</main>
<?php endif; ?>

<footer class="site-footer">
  <div class="site-container footer-grid">
    <div>
      <a class="site-brand site-brand-light" href="<?= e(site_page_url($business, 'home', $preview)) ?>">
        <?php if (!empty($business['logo'])): ?><img src="<?= e(media_url($business['logo'])) ?>" alt="<?= e($business['name']) ?>"><?php else: ?><span class="site-brand-mark"><?= e(initials((string) $business['name'])) ?></span><span class="site-brand-name"><?= e($business['name']) ?></span><?php endif; ?>
      </a>
      <p><?= e(str_limit((string) ($business['description'] ?? ''), 220)) ?></p>
      <?php if ($socials): ?>
        <div class="footer-socials">
          <?php foreach (['facebook', 'instagram', 'x', 'twitter', 'linkedin', 'tiktok', 'youtube'] as $network): ?>
            <?php if (!empty($socials[$network])): ?><a href="<?= e((string) $socials[$network]) ?>" target="_blank" rel="noopener"><?= e(ucfirst($network === 'x' ? 'X' : $network)) ?></a><?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <h3>Explore</h3>
      <?php foreach ($pages as $navPage): ?><a href="<?= e(site_page_url($business, (string) $navPage['slug'], $preview)) ?>"><?= e($navPage['title']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h3>Contact</h3>
      <?php if (!empty($business['address'])): ?><p><?= e($business['address']) ?><?= !empty($business['city']) ? ', ' . e($business['city']) : '' ?></p><?php endif; ?>
      <?php if (!empty($business['phone'])): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $business['phone'])) ?>"><?= e($business['phone']) ?></a><?php endif; ?>
      <?php if (!empty($business['email'])): ?><a href="mailto:<?= e($business['email']) ?>"><?= e($business['email']) ?></a><?php endif; ?>
    </div>
  </div>
  <div class="site-container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e($business['name']) ?>. All rights reserved.</span>
    <span>Powered by <a href="<?= e(app_url('index.php')) ?>">Uraio Business OS</a></span>
  </div>
</footer>
</body>
</html>
