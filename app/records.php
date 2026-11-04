<?php
/**
 * Generic tenant-scoped CRUD for module entities.
 * The business is always resolved server-side; record ids are scoped to it.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();

$entityKey = preg_replace('/[^a-z0-9_]/', '', (string) query('entity', 'customers'));
$entity = entity_definition($business, $entityKey);
if (!$entity) {
    flash('warning', 'That record type is not available for this business.');
    redirect('app/dashboard.php');
}

$action = (string) query('action', 'list');
$id = (int) query('id', 0);
$errors = [];
$record = null;

if ($action === 'edit') {
    $record = entity_find($business, $entity, $id);
    if (!$record) {
        flash('danger', 'That record could not be found in this business.');
        redirect('app/records.php?entity=' . urlencode($entityKey));
    }
}

if (is_post() && post('action') === 'save') {
    verify_csrf();
    $id = (int) post('id', 0);
    $record = $id > 0 ? entity_find($business, $entity, $id) : null;
    if ($id > 0 && !$record) {
        flash('danger', 'That record could not be found in this business.');
        redirect('app/records.php?entity=' . urlencode($entityKey));
    }
    try {
        $savedId = entity_save($business, $entity, $_POST, $_FILES, $id > 0 ? $id : null);
        activity_log('record.' . ($id > 0 ? 'updated' : 'created'), $entity['table'], $savedId, ['entity' => $entityKey], (int) $business['id']);
        flash('success', ($entity['label'] ?? 'Record') . ' saved.');
        redirect('app/records.php?entity=' . urlencode($entityKey) . '&action=edit&id=' . $savedId);
    } catch (InvalidArgumentException $e) {
        $errors['form'] = $e->getMessage();
    } catch (Throwable $e) {
        $errors['form'] = 'Could not save this record. Please check the values and try again.';
        error_log('entity save failed: ' . $e->getMessage());
    }
    $record = array_merge($record ?? [], $_POST);
    $action = $id > 0 ? 'edit' : 'new';
}

if (is_post() && post('action') === 'delete') {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can delete records.');
        redirect('app/records.php?entity=' . urlencode($entityKey));
    }
    $deleteId = (int) post('id', 0);
    if (entity_delete($business, $entity, $deleteId)) {
        activity_log('record.deleted', $entity['table'], $deleteId, ['entity' => $entityKey], (int) $business['id']);
        flash('success', ($entity['label'] ?? 'Record') . ' deleted.');
    } else {
        flash('danger', 'Record not found or already deleted.');
    }
    redirect('app/records.php?entity=' . urlencode($entityKey));
}

$activeKey = $entityKey;
foreach (module_nav($business) as $navItem) {
    if (($navItem['entity'] ?? '') === $entityKey) {
        $activeKey = $navItem['key'];
        break;
    }
}

$plural = $entity['plural'] ?? ($entity['label'] ?? 'Records');
$hasImages = false;
foreach (entity_fields($entity) as $field) {
    if (($field['type'] ?? '') === 'image') {
        $hasImages = true;
        break;
    }
}

layout_app_start($business, $activeKey, $plural, $entity['module'] ?? '');

if ($action === 'new' || $action === 'edit'):
    $values = [];
    foreach (entity_fields($entity) as $key => $field) {
        $value = $record[$key] ?? ($field['default'] ?? '');
        if (($field['type'] ?? '') === 'tags' && is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = implode(', ', $decoded);
            }
        }
        if (($field['type'] ?? '') === 'json' && is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }
        $values[$key] = $value;
    }
    ?>
    <div class="page-head">
      <div>
        <div class="breadcrumb"><a href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>"><?= e($plural) ?></a> <span>/</span> <span><?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <h1><?= $action === 'edit' ? 'Edit ' : 'New ' ?><?= e(strtolower((string) ($entity['label'] ?? 'record'))) ?></h1>
        <p>Saved to <strong><?= e($business['name']) ?></strong> only.</p>
      </div>
      <div class="page-actions"><a class="btn btn-ghost" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>">Back to list</a></div>
    </div>

    <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="post" action="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>" <?= $hasImages ? 'enctype="multipart/form-data"' : '' ?>>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">
          <div class="form-grid">
            <?php foreach (entity_fields($entity) as $key => $field): ?>
              <?= entity_field_html($business, $key, $field, $values[$key] ?? null) ?>
            <?php endforeach; ?>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save <?= e(strtolower((string) ($entity['label'] ?? 'record'))) ?></button>
            <a class="btn btn-ghost" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>">Cancel</a>
            <?php if ($action === 'edit'): ?>
              <button class="btn btn-danger" type="submit" form="delete-form" style="margin-left:auto"><?= icon('trash', 'icon icon-sm') ?> Delete</button>
            <?php endif; ?>
          </div>
        </form>
        <?php if ($action === 'edit'): ?>
          <form id="delete-form" method="post" action="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>" data-confirm="Delete this <?= e(strtolower((string) ($entity['label'] ?? 'record'))) ?>? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
    layout_app_end(['scripts' => ['app.js']]);
    exit;
endif;

$params = ['q' => (string) query('q', ''), 'status' => (string) query('status', ''), 'page' => (int) query('page', 1)];
$result = entity_list($business, $entity, $params);
$rows = $result['rows'];
$pagination = $result['pagination'];
$listColumns = $entity['list'] ?? array_slice(array_keys(entity_fields($entity)), 0, 5);
$statusField = entity_field($entity, 'status');
$baseUrl = 'app/records.php?entity=' . urlencode($entityKey) . ($params['q'] !== '' ? '&q=' . urlencode($params['q']) : '') . ($params['status'] !== '' ? '&status=' . urlencode($params['status']) : '');
?>
<div class="page-head">
  <div>
    <h1><?= e($plural) ?></h1>
    <p><?= number_format($result['total']) ?> <?= $result['total'] === 1 ? 'record' : 'records' ?> for <?= e($business['name']) ?>.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey) . '&action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New <?= e(strtolower((string) ($entity['label'] ?? 'record'))) ?></a>
  </div>
</div>

<div class="card">
  <form method="get" action="<?= e(app_url('app/records.php')) ?>" class="table-toolbar">
    <input type="hidden" name="entity" value="<?= e($entityKey) ?>">
    <div class="search-input"><?= icon('search') ?><input type="search" name="q" value="<?= e($params['q']) ?>" placeholder="Search <?= e(strtolower($plural)) ?>…" aria-label="Search"></div>
    <?php if ($statusField && !empty($statusField['options'])): ?>
      <select name="status" aria-label="Filter by status" style="max-width:180px">
        <option value="">All statuses</option>
        <?php foreach ($statusField['options'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= selected($params['status'], $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter', 'icon icon-sm') ?> Filter</button>
    <?php if ($params['q'] !== '' || $params['status'] !== ''): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <?php foreach ($listColumns as $column): ?>
              <?php $columnField = entity_field($entity, $column); ?>
              <th><?= e($columnField['label'] ?? ucwords(str_replace('_', ' ', $column))) ?></th>
            <?php endforeach; ?>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($listColumns as $column): ?>
                <?php $field = entity_field($entity, $column) ?? ['type' => 'text']; ?>
                <td>
                  <?php if ($column === ($entity['list'][0] ?? array_key_first(entity_fields($entity)))): ?>
                    <a class="cell-main" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey) . '&action=edit&id=' . $row['id'])) ?>"><?= entity_value_display($business, $field, $row[$column] ?? null) ?></a>
                    <?php if (isset($row['created_at']) && $column !== 'created_at'): ?><span class="cell-sub"><?= e(time_ago($row['created_at'])) ?></span><?php endif; ?>
                  <?php else: ?>
                    <?= entity_value_display($business, $field, $row[$column] ?? null) ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <div class="table-actions">
                  <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey) . '&action=edit&id=' . $row['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit</a>
                  <form method="post" action="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey))) ?>" data-confirm="Delete this record?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" aria-label="Delete"><?= icon('trash', 'icon icon-sm') ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-foot">
      <span class="small muted">Showing <?= count($rows) ?> of <?= number_format($result['total']) ?></span>
      <?= pagination_links($pagination, app_url($baseUrl)) ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon"><?= icon($entity['icon'] ?? 'grid') ?></div>
      <h3>No <?= e(strtolower($plural)) ?> yet</h3>
      <p><?= $params['q'] !== '' || $params['status'] !== '' ? 'No records match your filters.' : 'Add your first record and it will appear here.' ?></p>
      <a class="btn btn-primary" href="<?= e(app_url('app/records.php?entity=' . urlencode($entityKey) . '&action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New <?= e(strtolower((string) ($entity['label'] ?? 'record'))) ?></a>
    </div>
  <?php endif; ?>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
