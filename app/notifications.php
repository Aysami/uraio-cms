<?php
/**
 * In-app notifications for the signed-in user across all their businesses.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = active_business();
$userId = (int) $user['id'];

function notification_target(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return app_url('app/notifications.php');
    }
    if (preg_match('#^https?://#i', $url)) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url(app_url(''), PHP_URL_HOST));
        return $host !== '' && $host === $appHost ? $url : app_url('app/notifications.php');
    }
    if (!str_starts_with($url, '/')) {
        return app_url($url);
    }
    return $url;
}

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $notificationId = (int) post('notification_id', 0);
    $notification = $notificationId > 0
        ? db_row('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$notificationId, $userId])
        : null;

    if ($action === 'open' && $notification) {
        if (empty($notification['read_at'])) {
            db_update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $notificationId]);
        }
        redirect(notification_target((string) $notification['url']));
    }
    if ($action === 'read' && $notification) {
        db_update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $notificationId]);
        redirect('app/notifications.php');
    }
    if ($action === 'delete' && $notification) {
        db_exec('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$notificationId, $userId]);
        flash('success', 'Notification removed.');
        redirect('app/notifications.php');
    }
    if ($action === 'read_all') {
        db_exec('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
        flash('success', 'All notifications marked as read.');
        redirect('app/notifications.php');
    }
}

$filter = (string) query('filter', 'all');
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}
$where = 'n.user_id = ?';
$params = [$userId];
if ($filter === 'unread') {
    $where .= ' AND n.read_at IS NULL';
}
$total = (int) db_val("SELECT COUNT(*) FROM notifications n WHERE $where", $params);
$pagination = paginate($total, 25);
$notifications = db_all(
    "SELECT n.*, b.name AS business_name FROM notifications n
     LEFT JOIN businesses b ON b.id = n.business_id
     WHERE $where ORDER BY n.id DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);
$unread = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);

if ($business) {
    layout_app_start($business, 'notifications', 'Notifications', (string) $user['name']);
} else {
    layout_head('Notifications', ['noindex' => true]);
    ?>
    <div class="app-shell" style="display:block">
      <header class="topbar"><div class="topbar-title">Notifications</div><div class="topbar-actions"><a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/profile.php')) ?>">My account</a></div></header>
      <main class="content" style="max-width:900px;margin:0 auto"><?php layout_flashes(); ?>
    <?php
}
?>
<div class="page-head">
  <div>
    <h1>Notifications</h1>
    <p><?= $unread > 0 ? e((string) $unread) . ' unread notification' . ($unread === 1 ? '' : 's') : 'You are all caught up.' ?></p>
  </div>
  <div class="page-actions">
    <div class="btn-group">
      <a class="btn btn-ghost btn-sm<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(app_url('app/notifications.php')) ?>">All</a>
      <a class="btn btn-ghost btn-sm<?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= e(app_url('app/notifications.php?filter=unread')) ?>">Unread</a>
    </div>
    <?php if ($unread > 0): ?>
      <form method="post" action="<?= e(app_url('app/notifications.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all">
        <button class="btn btn-soft btn-sm" type="submit"><?= icon('check', 'icon icon-sm') ?> Mark all read</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php layout_flashes(); ?>

<div class="card">
  <?php if (!$notifications): ?>
    <div class="empty-state">
      <div class="empty-icon"><?= icon('bell') ?></div>
      <h3>No notifications<?= $filter === 'unread' ? ' unread' : '' ?></h3>
      <p>New orders, bookings, messages and billing updates will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Notification</th><th>Business</th><th>When</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($notifications as $notification): ?>
            <?php $isUnread = empty($notification['read_at']); ?>
            <tr<?= $isUnread ? ' class="is-unread"' : '' ?>>
              <td>
                <form method="post" action="<?= e(app_url('app/notifications.php')) ?>" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="open">
                  <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                  <button type="submit" class="link" style="background:none;border:0;padding:0;text-align:left;cursor:pointer">
                    <span class="cell-main"><?= $isUnread ? '● ' : '' ?><?= e($notification['title']) ?></span>
                    <?php if ($notification['body']): ?><span class="cell-sub"><?= e(str_limit($notification['body'], 140)) ?></span><?php endif; ?>
                  </button>
                </form>
              </td>
              <td class="small muted"><?= e($notification['business_name'] ?? 'Account') ?></td>
              <td class="small muted"><?= e(time_ago($notification['created_at'])) ?></td>
              <td>
                <div class="table-actions">
                  <?php if ($isUnread): ?>
                    <form method="post" action="<?= e(app_url('app/notifications.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="read">
                      <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit">Mark read</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="<?= e(app_url('app/notifications.php')) ?>" data-confirm="Delete this notification?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" aria-label="Delete notification"><?= icon('trash', 'icon icon-sm') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pagination['pages'] > 1): ?>
      <div class="card-body"><?= pagination_links($pagination, app_url('app/notifications.php' . ($filter === 'unread' ? '?filter=unread' : ''))) ?></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
if ($business) {
    layout_app_end(['scripts' => ['app.js']]);
} else {
    echo '</main></div>';
    layout_end(['scripts' => ['app.js']]);
}
