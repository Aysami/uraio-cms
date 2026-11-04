<?php
/**
 * Admin: domain management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$typeFilter = (string) ($_GET['type'] ?? '');

$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (d.domain LIKE ? OR b.name LIKE ? OR b.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $where .= " AND d.status = ?";
    $params[] = $statusFilter;
}
if ($typeFilter !== '') {
    $where .= " AND d.type = ?";
    $params[] = $typeFilter;
}

$total = (int) db_val("
    SELECT COUNT(*) FROM website_domains d
    LEFT JOIN businesses b ON b.id = d.business_id
    WHERE $where
", $params);
$pagination = paginate($total, $perPage, $page);
$domains = db_all(
    "SELECT d.*, b.name AS business_name, b.slug AS business_slug
     FROM website_domains d
     LEFT JOIN businesses b ON b.id = d.business_id
     WHERE $where
     ORDER BY d.is_primary DESC, d.id DESC
     LIMIT {$perPage} OFFSET {$pagination['offset']}",
    $params
);

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    $domainId = (int) post('domain_id', 0);
    $domain = $domainId > 0 ? db_row('SELECT * FROM website_domains WHERE id = ?', [$domainId]) : null;

    if ($action === 'verify' && $domain) {
        if ($domain['status'] === 'verified') {
            flash('info', 'Domain is already verified.');
        } else {
            db_update('website_domains', ['status' => 'verified', 'verified_at' => date('Y-m-d H:i:s'), 'ssl_status' => 'pending'], 'id = :id', ['id' => $domainId]);
            activity_log('admin.domain_verified', 'website_domain', $domainId, ['domain' => $domain['domain']], null);
            flash('success', 'Domain manually verified. SSL will be provisioned.');
        }
        redirect('admin/domains.php');
    }
    if ($action === 'primary' && $domain) {
        db_exec('UPDATE website_domains SET is_primary = 0 WHERE business_id = ?', [$domain['business_id']]);
        db_update('website_domains', ['is_primary' => 1], 'id = :id', ['id' => $domainId]);
        activity_log('admin.domain_primary_set', 'website_domain', $domainId, ['domain' => $domain['domain']], null);
        flash('success', 'Primary domain updated.');
        redirect('admin/domains.php');
    }
    if ($action === 'ssl_status' && $domain) {
        $newStatus = (string) post('ssl_status', 'pending');
        $allowed = ['none', 'pending', 'active', 'failed'];
        if (in_array($newStatus, $allowed, true)) {
            db_update('website_domains', ['ssl_status' => $newStatus], 'id = :id', ['id' => $domainId]);
            activity_log('admin.domain_ssl_changed', 'website_domain', $domainId, ['ssl_status' => $newStatus], null);
            flash('success', 'SSL status updated.');
        }
        redirect('admin/domains.php');
    }
    if ($action === 'delete' && $domain) {
        db_exec('DELETE FROM website_domains WHERE id = ?', [$domainId]);
        activity_log('admin.domain_deleted', 'website_domain', $domainId, ['domain' => $domain['domain']], null);
        flash('success', 'Domain removed.');
        redirect('admin/domains.php');
    }
}

$baseUrl = app_url('admin/domains.php') . ($search !== '' || $statusFilter !== '' || $typeFilter !== '' ? '?' . http_build_query(array_filter(['q' => $search, 'status' => $statusFilter, 'type' => $typeFilter])) : '');

admin_page_start($user, 'domains', 'Domains', 'Custom and subdomain management');
?>
<div class="page-head">
  <div>
    <h1>Domains</h1>
    <p>Custom domains and Uraio subdomains for business websites.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" action="<?= e(app_url('admin/domains.php')) ?>" class="form-grid" style="gap:12px;align-items:end">
      <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Domain, business name or slug">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All</option>
          <option value="pending" <?= selected($statusFilter, 'pending') ?>>Pending</option>
          <option value="verified" <?= selected($statusFilter, 'verified') ?>>Verified</option>
          <option value="failed" <?= selected($statusFilter, 'failed') ?>>Failed</option>
        </select>
      </div>
      <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
          <option value="">All</option>
          <option value="custom" <?= selected($typeFilter, 'custom') ?>>Custom</option>
          <option value="subdomain" <?= selected($typeFilter, 'subdomain') ?>>Subdomain</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('search', 'icon icon-sm') ?> Filter</button>
      <?php if ($search !== '' || $statusFilter !== '' || $typeFilter !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/domains.php')) ?>"><?= icon('x', 'icon icon-sm') ?> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
admin_table(
    [
        ['key' => 'domain', 'label' => 'Domain'],
        ['key' => 'type', 'label' => 'Type', 'render' => fn ($v) => ucfirst($v)],
        ['key' => 'business_name', 'label' => 'Business'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'ssl_status', 'label' => 'SSL'],
        ['key' => 'is_primary', 'label' => 'Primary', 'render' => fn ($v) => (int) $v === 1 ? '<span class="badge badge-success">Yes</span>' : '<span class="muted">No</span>'],
        ['key' => 'verified_at', 'label' => 'Verified'],
    ],
    $domains,
    [
        'pagination' => $pagination,
        'pagination_url' => $baseUrl,
        'actions' => [
            [
                'label' => 'Verify',
                'icon' => 'check',
                'form' => 'domains.php',
                'condition' => fn ($r) => $r['status'] !== 'verified',
                'hidden' => [
                    'action' => 'verify',
                    'domain_id' => fn ($r) => $r['id'],
                ],
            ],
            [
                'label' => 'Make primary',
                'icon' => 'star',
                'form' => 'domains.php',
                'condition' => fn ($r) => (int) $r['is_primary'] !== 1 && $r['status'] === 'verified',
                'hidden' => [
                    'action' => 'primary',
                    'domain_id' => fn ($r) => $r['id'],
                ],
            ],
            [
                'label' => 'SSL status',
                'icon' => 'shield',
                'url' => fn ($r) => 'domains.php?ssl=' . $r['id'],
            ],
            [
                'label' => 'Delete',
                'icon' => 'trash',
                'form' => 'domains.php',
                'confirm' => 'Remove this domain? The business website will revert to its Uraio subdomain.',
                'hidden' => [
                    'action' => 'delete',
                    'domain_id' => fn ($r) => $r['id'],
                ],
            ],
        ],
    ]
);
?>

<?php if (isset($_GET['ssl'])): ?>
  <?php $sslId = (int) $_GET['ssl']; $sslDomain = db_row('SELECT * FROM website_domains WHERE id = ?', [$sslId]); ?>
  <?php if ($sslDomain): ?>
    <div class="modal-backdrop" id="ssl-modal" role="dialog" aria-modal="true" aria-labelledby="ssl-title">
      <div class="modal">
        <div class="flex-between mb-2">
          <h2 id="ssl-title" class="mt-0">SSL status for <?= e($sslDomain['domain']) ?></h2>
          <a class="icon-btn" href="<?= e(app_url('admin/domains.php')) ?>" aria-label="Close"><?= icon('x') ?></a>
        </div>
        <form method="post" action="<?= e(app_url('admin/domains.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="ssl_status">
          <input type="hidden" name="domain_id" value="<?= (int) $sslDomain['id'] ?>">
          <div class="field">
            <label for="ssl_status">SSL status</label>
            <select id="ssl_status" name="ssl_status">
              <option value="none" <?= selected($sslDomain['ssl_status'], 'none') ?>>None</option>
              <option value="pending" <?= selected($sslDomain['ssl_status'], 'pending') ?>>Pending</option>
              <option value="active" <?= selected($sslDomain['ssl_status'], 'active') ?>>Active</option>
              <option value="failed" <?= selected($sslDomain['ssl_status'], 'failed') ?>>Failed</option>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save</button>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/domains.php')) ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
admin_page_end(['scripts' => ['app.js']]);