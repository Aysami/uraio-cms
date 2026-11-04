<?php
/**
 * Dynamic PWA manifest. Uses the configured site name and absolute URLs so it
 * works from a subdirectory in development and from a domain in production.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$name = (string) setting('site_name', 'Uraio Business OS');
$user = current_user();
$start = $user ? app_url('app/dashboard.php') : app_url('index.php');

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name' => $name,
    'short_name' => 'Uraio',
    'description' => 'Run your business, manage customers and publish a professional website from one place.',
    'id' => app_url(''),
    'start_url' => $start,
    'scope' => app_url(''),
    'display' => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone', 'browser'],
    'orientation' => 'portrait-primary',
    'background_color' => '#f4f7f6',
    'theme_color' => '#0f766e',
    'lang' => 'en',
    'dir' => 'ltr',
    'categories' => ['business', 'productivity'],
    'icons' => [
        ['src' => app_url('assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => app_url('assets/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => app_url('assets/icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => app_url('assets/icons/favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    'shortcuts' => [
        ['name' => 'Dashboard', 'short_name' => 'Dashboard', 'url' => app_url('app/dashboard.php'), 'icons' => [['src' => app_url('assets/icons/icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Website editor', 'short_name' => 'Website', 'url' => app_url('app/website.php'), 'icons' => [['src' => app_url('assets/icons/icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Billing', 'short_name' => 'Billing', 'url' => app_url('app/billing.php'), 'icons' => [['src' => app_url('assets/icons/icon-192.png'), 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
