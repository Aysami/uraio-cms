<?php
/**
 * Dynamic robots.txt. Private areas are always disallowed.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
?>
User-agent: *
Allow: /$
Disallow: /app/
Disallow: /admin/
Disallow: /api/
Disallow: /auth/
Disallow: /install.php
Disallow: /site.php

Sitemap: <?= e(app_url('sitemap.php')) ?>
