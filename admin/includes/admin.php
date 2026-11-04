<?php
/**
 * Admin panel bootstrap and shared helpers.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

function require_admin_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Please sign in to continue.');
        redirect('admin/login.php');
    }
    if (!is_admin_user($user)) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Admin access required', 'You do not have permission to access the Uraio administration area.');
        exit;
    }
    return $user;
}

function admin_layout_head(string $title, array $options = []): void
{
    $siteName = 'Uraio Business OS — Admin';
    $fullTitle = $title === '' ? $siteName : $title . ' · ' . $siteName;
    $description = $options['description'] ?? 'Uraio Business OS administration panel.';
    $bodyClass = $options['body_class'] ?? 'admin-body';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f766e">
<link rel="icon" href="<?= e(app_url('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
<?php foreach (($options['styles'] ?? []) as $style): ?>
<link rel="stylesheet" href="<?= e(asset_url('css/' . $style)) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function admin_layout_end(array $options = []): void
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

function admin_flashes(): void
{
    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $iconName = ['success' => 'check', 'danger' => 'alert', 'warning' => 'alert', 'info' => 'info'][$type];
        echo '<div class="alert alert-' . e($type) . '" role="status">' . icon($iconName) . '<div>' . e($flash['message']) . '</div></div>';
    }
}

function admin_sidebar(string $activeKey): void
{
    $nav = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'url' => 'index.php'],
        ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'url' => 'users.php'],
        ['key' => 'businesses', 'label' => 'Businesses', 'icon' => 'briefcase', 'url' => 'businesses.php'],
        ['key' => 'categories', 'label' => 'Categories', 'icon' => 'tag', 'url' => 'categories.php'],
        ['key' => 'modules', 'label' => 'Modules', 'icon' => 'grid', 'url' => 'modules.php'],
        ['key' => 'templates', 'label' => 'Templates', 'icon' => 'layout', 'url' => 'templates.php'],
        ['key' => 'subscriptions', 'label' => 'Subscriptions', 'icon' => 'credit-card', 'url' => 'subscriptions.php'],
        ['key' => 'payments', 'label' => 'Payments', 'icon' => 'dollar', 'url' => 'payments.php'],
        ['key' => 'domains', 'label' => 'Domains', 'icon' => 'globe', 'url' => 'domains.php'],
        ['key' => 'activity', 'label' => 'Activity Logs', 'icon' => 'database', 'url' => 'activity.php'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'url' => 'settings.php'],
    ];
    ?>
<aside class="admin-sidebar" id="admin-sidebar">
  <div class="admin-sidebar-head">
    <a class="brand" href="<?= e(app_url('admin/index.php')) ?>">
      <span class="brand-mark brand-mark-sm"><?= icon('sparkles') ?></span>
      <span>Uraio Admin</span>
    </a>
    <button class="icon-btn mobile-menu-btn" type="button" data-sidebar-close aria-label="Close menu"><?= icon('x') ?></button>
  </div>
  <nav class="admin-side-nav" aria-label="Admin navigation">
    <?php foreach ($nav as $item): ?>
      <a class="admin-side-link<?= $activeKey === $item['key'] ? ' is-active' : '' ?>" href="<?= e(app_url('admin/' . $item['url'])) ?>">
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-foot">
    <a class="admin-side-link" href="<?= e(app_url('app/dashboard.php')) ?>" target="_blank" rel="noopener">
      <?= icon('external', 'icon icon-sm') ?><span>Open Business OS</span>
    </a>
    <form method="post" action="<?= e(app_url('auth/logout.php')) ?>" style="display:block;margin-top:8px">
      <?= csrf_field() ?>
      <button class="admin-side-link" type="submit" style="background:none;border:0;padding:0;text-align:left;color:inherit;cursor:pointer;width:100%">
        <?= icon('logout', 'icon icon-sm') ?><span>Sign out</span>
      </button>
    </form>
  </div>
</aside>
<div class="admin-sidebar-backdrop" data-sidebar-close></div>
<?php
}

function admin_page_start(array $user, string $activeKey, string $title, string $subtitle = ''): void
{
    admin_layout_head($title, ['body_class' => 'admin-body']);
    admin_sidebar($activeKey);
    ?>
<div class="admin-main">
  <header class="admin-topbar">
    <button class="icon-btn mobile-menu-btn" type="button" data-sidebar-open aria-label="Open menu"><?= icon('menu') ?></button>
    <div class="admin-topbar-title"><?= e($title) ?><?php if ($subtitle !== ''): ?><span><?= e($subtitle) ?></span><?php endif; ?></div>
    <div class="admin-topbar-actions">
      <span class="admin-user-badge">
        <?= icon('user', 'icon icon-sm') ?>
        <span><?= e($user['name']) ?></span>
        <span class="badge badge-info"><?= e($user['role_name'] ?? 'Admin') ?></span>
      </span>
    </div>
  </header>
  <main class="admin-content">
    <?php admin_flashes(); ?>
<?php
}

function admin_page_end(array $options = []): void
{
    ?>
  </main>
</div>
<?php
    admin_layout_end($options);
}

function admin_table(array $columns, array $rows, array $options = []): void
{
    $emptyMessage = $options['empty'] ?? 'No records found.';
    $actions = $options['actions'] ?? [];
    ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr>
      <?php foreach ($columns as $col): ?><th<?= isset($col['align']) ? ' class="' . e($col['align']) . '"' : '' ?>><?= e($col['label']) ?></th><?php endforeach; ?>
      <?php if ($actions): ?><th class="text-right">Actions</th><?php endif; ?>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= count($columns) + ($actions ? 1 : 0) ?>" class="text-center muted" style="padding:40px"><?= e($emptyMessage) ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ($columns as $col): ?>
              <?php $key = $col['key']; $val = $row[$key] ?? ''; ?>
              <td<?= isset($col['align']) ? ' class="' . e($col['align']) . '"' : '' ?>>
                <?php if (isset($col['render'])): ?>
                  <?= $col['render']($val, $row) ?>
                <?php else: ?>
                  <?= is_bool($val) ? ($val ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-neutral">No</span>') : e((string) $val) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php if ($actions): ?>
              <td class="text-right table-actions">
                <?php foreach ($actions as $action): ?>
                  <?php if (!isset($action['condition']) || $action['condition']($row)): ?>
                    <?php if (isset($action['form'])): ?>
                      <form method="post" action="<?= e(app_url('admin/' . $action['form'])) ?>"<?= isset($action['confirm']) ? ' data-confirm="' . e($action['confirm']) . '"' : '' ?> style="display:inline">
                        <?= csrf_field() ?>
                        <?php foreach (($action['hidden'] ?? []) as $hk => $hv): ?>
                          <?php $hiddenValue = $hv instanceof Closure ? $hv($row) : $hv; ?>
                          <input type="hidden" name="<?= e($hk) ?>" value="<?= e((string) ($hiddenValue ?? '')) ?>">
                        <?php endforeach; ?>
                        <button class="btn btn-ghost btn-sm" type="submit" aria-label="<?= e($action['label']) ?>"><?= icon($action['icon']) ?></button>
                      </form>
                    <?php else: ?>
                      <?php
                        $actionUrl = (string) $action['url']($row);
                        $actionHref = preg_match('#^(?:https?:)?//#i', $actionUrl) ? $actionUrl : app_url('admin/' . $actionUrl);
                      ?>
                      <a class="btn btn-ghost btn-sm" href="<?= e($actionHref) ?>" aria-label="<?= e($action['label']) ?>"><?= icon($action['icon']) ?></a>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
    if (!empty($options['pagination'])) {
        echo pagination_links($options['pagination'], $options['pagination_url']);
    }
}