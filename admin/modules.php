<?php
/**
 * Admin: module management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$action = (string) query('action', 'list');
$moduleId = (int) query('id', 0);
$errors = [];

if (is_post()) {
    verify_csrf();
    $postAction = (string) post('action', '');

    if ($postAction === 'create') {
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', '')) ?: slugify($name);
        $description = trim((string) post('description', ''));
        $isCore = !empty(post('is_core'));
        $status = (string) post('status', 'active');
        $sortOrder = (int) post('sort_order', 0);

        if ($name === '') {
            $errors['name'] = 'Module name is required.';
        } elseif (db_val('SELECT id FROM modules WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        if (!$errors) {
            db_insert('modules', [
                'name' => str_limit($name, 120),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'is_core' => $isCore ? 1 : 0,
                'status' => $status,
                'sort_order' => $sortOrder,
            ]);
            activity_log('admin.module_created', 'module', 0, ['name' => $name], null);
            flash('success', 'Module created.');
            redirect('admin/modules.php');
        }
    }

    if ($postAction === 'update' && $moduleId > 0) {
        $module = db_row('SELECT * FROM modules WHERE id = ?', [$moduleId]);
        if (!$module) {
            flash('danger', 'Module not found.');
            redirect('admin/modules.php');
        }
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', ''));
        $description = trim((string) post('description', ''));
        $isCore = !empty(post('is_core'));
        $status = (string) post('status', 'active');
        $sortOrder = (int) post('sort_order', 0);

        if ($name === '') {
            $errors['name'] = 'Module name is required.';
        }
        if ($slug !== $module['slug'] && db_val('SELECT id FROM modules WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        if (!$errors) {
            db_update('modules', [
                'name' => str_limit($name, 120),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'is_core' => $isCore ? 1 : 0,
                'status' => $status,
                'sort_order' => $sortOrder,
            ], 'id = :id', ['id' => $moduleId]);
            activity_log('admin.module_updated', 'module', $moduleId, ['name' => $name], null);
            flash('success', 'Module updated.');
            redirect('admin/modules.php');
        }
    }

    if ($postAction === 'delete' && $moduleId > 0) {
        $module = db_row('SELECT * FROM modules WHERE id = ?', [$moduleId]);
        if (!$module) {
            flash('danger', 'Module not found.');
        } elseif ((int) $module['is_core'] === 1) {
            flash('danger', 'Core modules cannot be deleted.');
        } else {
            db_exec('DELETE FROM modules WHERE id = ?', [$moduleId]);
            activity_log('admin.module_deleted', 'module', $moduleId, ['name' => $module['name']], null);
            flash('success', 'Module deleted.');
        }
        redirect('admin/modules.php');
    }
}

$modules = db_all("SELECT * FROM modules ORDER BY is_core DESC, sort_order ASC, name ASC");

$editModule = null;
if ($action === 'edit' && $moduleId > 0) {
    $editModule = db_row('SELECT * FROM modules WHERE id = ?', [$moduleId]);
    if (!$editModule) {
        flash('danger', 'Module not found.');
        redirect('admin/modules.php');
    }
}
$newModule = $action === 'new';

admin_page_start($user, 'modules', 'Modules', 'Manage business modules and their availability');
?>
<div class="page-head">
  <div>
    <h1>Modules</h1>
    <p>Modules define business features like orders, bookings, products, rooms, etc. Core modules are available to all categories.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(app_url('admin/modules.php?action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New module</a>
  </div>
</div>

<?php admin_flashes(); ?>

<?php if ($newModule || $editModule): ?>
  <div class="card mb-2">
    <div class="card-head"><h2 class="mt-0"><?= $editModule ? 'Edit module' : 'New module' ?></h2></div>
    <form method="post" action="<?= e(app_url('admin/modules.php' . ($editModule ? '?action=edit&id=' . $editModule['id'] : '?action=create'))) ?>" class="card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editModule ? 'update' : 'create' ?>">
      <div class="form-grid">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="<?= e($editModule['name'] ?? old('name', '')) ?>" required maxlength="120">
          <?= render_field_error($errors, 'name') ?>
        </div>
        <div class="field">
          <label for="slug">Slug</label>
          <input type="text" id="slug" name="slug" value="<?= e($editModule['slug'] ?? old('slug', '')) ?>" pattern="[a-z0-9-]+" required maxlength="80">
          <?= render_field_error($errors, 'slug') ?>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="active" <?= selected($editModule['status'] ?? old('status', 'active'), 'active') ?>>Active</option>
            <option value="inactive" <?= selected($editModule['status'] ?? old('status', 'active'), 'inactive') ?>>Inactive</option>
          </select>
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" value="<?= e($editModule['sort_order'] ?? old('sort_order', 0)) ?>" min="0">
        </div>
        <div class="field field-check">
          <input type="checkbox" id="is_core" name="is_core" value="1" <?= !empty($editModule['is_core']) || !empty(old('is_core')) ? 'checked' : '' ?>>
          <label for="is_core">Core module (enabled for all categories)</label>
        </div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e($editModule['description'] ?? old('description', '')) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save module</button>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/modules.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Module</th><th>Slug</th><th>Core</th><th>Status</th><th>Categories</th><th>Businesses</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($modules as $mod): ?>
              <?php
                $catCount = (int) db_val('SELECT COUNT(*) FROM category_modules WHERE module_id = ?', [$mod['id']]);
                $bizCount = (int) db_val('SELECT COUNT(*) FROM business_modules WHERE module_id = ?', [$mod['id']]);
              ?>
              <tr>
                <td><strong><?= e($mod['name']) ?></strong><span class="cell-sub mono">#<?= (int) $mod['id'] ?></span></td>
                <td class="mono"><?= e($mod['slug']) ?></td>
                <td><?= (int) $mod['is_core'] === 1 ? '<span class="badge badge-info">Core</span>' : '<span class="muted">Optional</span>' ?></td>
                <td><?= status_badge((string) $mod['status']) ?></td>
                <td class="small muted"><?= e((string) $catCount) ?> categories</td>
                <td class="small muted"><?= e((string) $bizCount) ?> businesses</td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/modules.php?action=edit&id=' . $mod['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?></a>
                    <?php if ((int) $mod['is_core'] !== 1): ?>
                      <form method="post" action="<?= e(app_url('admin/modules.php')) ?>" data-confirm="Delete <?= e($mod['name']) ?>? This removes it from all categories and businesses.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $mod['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 'icon icon-sm') ?></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php
admin_page_end(['scripts' => ['app.js']]);