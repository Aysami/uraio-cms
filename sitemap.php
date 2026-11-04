<?php
/**
 * XML sitemap: the platform landing page plus every published business website
 * and its published pages. Draft and private pages are excluded.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=1800');

$urls = [
    ['loc' => app_url('index.php'), 'lastmod' => date('Y-m-d'), 'changefreq' => 'weekly', 'priority' => '1.0'],
];

$businesses = db_all(
    "SELECT b.*, w.updated_at AS website_updated_at
     FROM businesses b JOIN business_websites w ON w.business_id = b.id
     WHERE b.status = 'active' AND b.deleted_at IS NULL AND w.status = 'published'
     ORDER BY b.updated_at DESC LIMIT 5000"
);

foreach ($businesses as $business) {
    $urls[] = [
        'loc' => business_site_url($business),
        'lastmod' => date('Y-m-d', strtotime((string) ($business['website_updated_at'] ?: $business['updated_at']))),
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
    $pages = db_all(
        "SELECT * FROM website_pages WHERE business_id = ? AND status = 'published' AND is_home = 0 ORDER BY sort_order ASC",
        [$business['id']]
    );
    foreach ($pages as $page) {
        $urls[] = [
            'loc' => business_site_url($business, (string) $page['slug']),
            'lastmod' => date('Y-m-d', strtotime((string) ($page['updated_at'] ?: $page['created_at']))),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= e($url['loc']) ?></loc>
    <lastmod><?= e($url['lastmod']) ?></lastmod>
    <changefreq><?= e($url['changefreq']) ?></changefreq>
    <priority><?= e($url['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
