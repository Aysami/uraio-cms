<?php
/**
 * Uraio Connect — iframe widget dispatcher.
 * GET /connect/embed.php?t={token}&w={menu|products|hours|contact|booking|reviews|whatsapp}
 */

declare(strict_types=1);
define('UBOS_CONNECT_EMBED', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once BASE_PATH . '/includes/connect-render.php';

$token = trim((string) ($_GET['t'] ?? ''));
$widget = trim((string) ($_GET['w'] ?? 'contact'));
$validWidgets = ['menu', 'products', 'hours', 'contact', 'booking', 'reviews', 'whatsapp'];

header('X-Robots-Tag: noindex, nofollow');

$business = $token !== '' ? embed_business_by_token($token) : null;
if (!$business || !in_array($widget, $validWidgets, true)) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Widget not available</title><link rel="stylesheet" href="<?= e(app_url('connect/widget.css')) ?>?v=1.0.0"></head>
<body><div class="wgt-shell"><div class="empty">This widget is not available.</div></div></body></html><?php
    exit;
}

$sent = in_array($_GET['sent'] ?? '', ['contact', 'booking'], true) ? $_GET['sent'] : null;
$originAllowed = embed_request_allowed($business);
$title = e($business['name']) . ' — ' . e(connect_widget_title($widget));
$bodyHtml = $originAllowed
    ? connect_widget_html($business, $widget, $sent)
    : '<div class="empty">This content is not authorised to appear on this website. <a href="' . e(app_url('index.php')) . '" style="color:#0f766e;font-weight:700">Powered by Uraio</a></div>';
$brandVisible = !embed_branding_hidden($business);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(app_url('connect/widget.css')) ?>?v=1.0.0">
</head>
<body>
<div class="wgt-shell">
  <div class="wgt-title"><?= e(connect_widget_title($widget)) ?></div>
  <?= $bodyHtml ?>
  <?php if ($brandVisible): ?>
    <div class="wgt-brand"><span>Powered by</span> <a href="<?= e(app_url('index.php')) ?>" target="_blank" rel="noopener">Uraio Business OS</a></div>
  <?php endif; ?>
</div>
</body>
</html>
