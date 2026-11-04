<?php
/**
 * Admin: website template management.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();

$action = (string) query('action', 'list');
$templateId = (int) query('id', 0);
$errors = [];

$categories = all_categories(false);

if (is_post()) {
    verify_csrf();
    $postAction = (string) post('action', '');

    if ($postAction === 'create') {
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', '')) ?: slugify($name);
        $description = trim((string) post('description', ''));
        $categoryGroup = trim((string) post('category_group', ''));
        $isPremium = !empty(post('is_premium'));
        $isDefault = !empty(post('is_default'));
        $status = (string) post('status', 'active');
        $sortOrder = (int) post('sort_order', 0);

        if ($name === '') {
            $errors['name'] = 'Template name is required.';
        } elseif (db_val('SELECT id FROM website_templates WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        if (!$errors) {
            if ($isDefault) {
                db_exec("UPDATE website_templates SET is_default = 0");
            }
            db_insert('website_templates', [
                'name' => str_limit($name, 120),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'category_group' => $categoryGroup !== '' ? $categoryGroup : null,
                'is_premium' => $isPremium ? 1 : 0,
                'is_default' => $isDefault ? 1 : 0,
                'status' => $status,
                'sort_order' => $sortOrder,
            ]);
            activity_log('admin.template_created', 'website_template', 0, ['name' => $name], null);
            flash('success', 'Template created.');
            redirect('admin/templates.php');
        }
    }

    if ($postAction === 'update' && $templateId > 0) {
        $template = db_row('SELECT * FROM website_templates WHERE id = ?', [$templateId]);
        if (!$template) {
            flash('danger', 'Template not found.');
            redirect('admin/templates.php');
        }
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', ''));
        $description = trim((string) post('description', ''));
        $categoryGroup = trim((string) post('category_group', ''));
        $isPremium = !empty(post('is_premium'));
        $isDefault = !empty(post('is_default'));
        $status = (string) post('status', 'active');
        $sortOrder = (int) post('sort_order', 0);

        if ($name === '') {
            $errors['name'] = 'Template name is required.';
        }
        if ($slug !== $template['slug'] && db_val('SELECT id FROM website_templates WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        if (!$errors) {
            if ($isDefault) {
                db_exec("UPDATE website_templates SET is_default = 0");
            }
            db_update('website_templates', [
                'name' => str_limit($name, 120),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'category_group' => $categoryGroup !== '' ? $categoryGroup : null,
                'is_premium' => $isPremium ? 1 : 0,
                'is_default' => $isDefault ? 1 : 0,
                'status' => $status,
                'sort_order' => $sortOrder,
            ], 'id = :id', ['id' => $templateId]);
            activity_log('admin.template_updated', 'website_template', $templateId, ['name' => $name], null);
            flash('success', 'Template updated.');
            redirect('admin/templates.php');
        }
    }

    if ($postAction === 'delete' && $templateId > 0) {
        $template = db_row('SELECT * FROM website_templates WHERE id = ?', [$templateId]);
        if (!$template) {
            flash('danger', 'Template not found.');
        } elseif ((int) $template['is_default'] === 1) {
            flash('danger', 'The default template cannot be deleted.');
        } else {
            db_exec('DELETE FROM website_templates WHERE id = ?', [$templateId]);
            activity_log('admin.template_deleted', 'website_template', $templateId, ['name' => $template['name']], null);
            flash('success', 'Template deleted.');
        }
        redirect('admin/templates.php');
    }
}

$templates = db_all("SELECT * FROM website_templates ORDER BY is_default DESC, sort_order ASC, name ASC");

$editTemplate = null;
if ($action === 'edit' && $templateId > 0) {
    $editTemplate = db_row('SELECT * FROM website_templates WHERE id = ?', [$templateId]);
    if (!$editTemplate) {
        flash('danger', 'Template not found.');
        redirect('admin/templates.php');
    }
}
$newTemplate = $action === 'new';

admin_page_start($user, 'templates', 'Website templates', 'Manage website templates for business categories');
?>
<div class="page-head">
  <div>
    <h1>Website templates</h1>
    <p>Templates define the starter design and layout for business websites. One template is marked as the platform default.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(app_url('admin/templates.php?action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New template</a>
  </div>
</div>

<?php admin_flashes(); ?>

<?php if ($newTemplate || $editTemplate): ?>
  <div class="card mb-2">
    <div class="card-head"><h2 class="mt-0"><?= $editTemplate ? 'Edit template' : 'New template' ?></h2></div>
    <form method="post" action="<?= e(app_url('admin/templates.php' . ($editTemplate ? '?action=edit&id=' . $editTemplate['id'] : '?action=create'))) ?>" class="card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editTemplate ? 'update' : 'create' ?>">
      <div class="form-grid">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="<?= e($editTemplate['name'] ?? old('name', '')) ?>" required maxlength="120">
          <?= render_field_error($errors, 'name') ?>
        </div>
        <div class="field">
          <label for="slug">Slug</label>
          <input type="text" id="slug" name="slug" value="<?= e($editTemplate['slug'] ?? old('slug', '')) ?>" pattern="[a-z0-9-]+" required maxlength="80">
          <?= render_field_error($errors, 'slug') ?>
        </div>
        <div class="field">
          <label for="category_group">Category group</label>
          <input type="text" id="category_group" name="category_group" value="<?= e($editTemplate['category_group'] ?? old('category_group', '')) ?>" placeholder="e.g. Food & Hospitality">
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="active" <?= selected($editTemplate['status'] ?? old('status', 'active'), 'active') ?>>Active</option>
            <option value="inactive" <?= selected($editTemplate['status'] ?? old('status', 'active'), 'inactive') ?>>Inactive</option>
          </select>
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" value="<?= e($editTemplate['sort_order'] ?? old('sort_order', 0)) ?>" min="0">
        </div>
        <div class="field field-check">
          <input type="checkbox" id="is_premium" name="is_premium" value="1" <?= !empty($editTemplate['is_premium']) || !empty(old('is_premium')) ? 'checked' : '' ?>>
          <label for="is_premium">Premium (requires paid plan with templates ≥ 5)</label>
        </div>
        <div class="field field-check">
          <input type="checkbox" id="is_default" name="is_default" value="1" <?= !empty($editTemplate['is_default']) || !empty(old('is_default')) ? 'checked' : '' ?>>
          <label for="is_default">Default template</label>
        </div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e($editTemplate['description'] ?? old('description', '')) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save template</button>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/templates.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Template</th><th>Slug</th><th>Category group</th><th>Premium</th><th>Default</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($templates as $tmpl): ?>
              <tr>
                <td><strong><?= e($tmpl['name']) ?></strong><span class="cell-sub mono">#<?= (int) $tmpl['id'] ?></span></td>
                <td class="mono"><?= e($tmpl['slug']) ?></td>
                <td class="small muted"><?= e($tmpl['category_group'] ?? '—') ?></td>
                <td><?= (int) $tmpl['is_premium'] === 1 ? '<span class="badge badge-info">Premium</span>' : '<span class="muted">Standard</span>' ?></td>
                <td><?= (int) $tmpl['is_default'] === 1 ? '<span class="badge badge-success">Default</span>' : '<span class="muted">—</span>' ?></td>
                <td><?= status_badge((string) $tmpl['status']) ?></td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/templates.php?action=edit&id=' . $tmpl['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?></a>
                    <?php if ((int) $tmpl['is_default'] !== 1): ?>
                      <form method="post" action="<?= e(app_url('admin/templates.php')) ?>" data-confirm="Delete <?= e($tmpl['name']) ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $tmpl['id'] ?>">
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