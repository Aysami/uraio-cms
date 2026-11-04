<?php
/**
 * Admin: category management with hierarchy and module/template assignment.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
require_once BASE_PATH . '/includes/category-engine.php';
$user = require_admin_login();

$action = (string) query('action', 'list');
$categoryId = (int) query('id', 0);
$errors = [];

$modules = db_all("SELECT * FROM modules WHERE status = 'active' ORDER BY is_core DESC, name ASC");
$templates = db_all("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC, name ASC");

if (is_post()) {
    verify_csrf();
    $postAction = (string) post('action', '');

    if ($postAction === 'create') {
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', '')) ?: slugify($name);
        $parentId = (int) post('parent_id', 0);
        $description = trim((string) post('description', ''));
        $icon = trim((string) post('icon', '🏷️'));
        $isPopular = !empty(post('is_popular'));
        $sortOrder = (int) post('sort_order', 0);
        $defaultTemplateId = (int) post('default_template_id', 0);
        $moduleIds = array_map('intval', (array) (post('module_ids', []) ?: []));

        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        } elseif (db_val('SELECT id FROM categories WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if ($parentId > 0 && !category_by_id($parentId)) {
            $errors['parent_id'] = 'Invalid parent category.';
        }
        if ($defaultTemplateId > 0 && !db_row('SELECT id FROM website_templates WHERE id = ?', [$defaultTemplateId])) {
            $errors['default_template_id'] = 'Invalid template.';
        }

        if (!$errors) {
            $newId = db_insert('categories', [
                'parent_id' => $parentId > 0 ? $parentId : null,
                'name' => str_limit($name, 190),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'icon' => $icon !== '' ? $icon : '🏷️',
                'is_popular' => $isPopular ? 1 : 0,
                'sort_order' => $sortOrder,
                'status' => 'active',
                'default_template_id' => $defaultTemplateId > 0 ? $defaultTemplateId : null,
            ]);
            foreach ($moduleIds as $modId) {
                db_exec('INSERT IGNORE INTO category_modules (category_id, module_id, is_default) VALUES (?, ?, 1)', [$newId, $modId]);
            }
            activity_log('admin.category_created', 'category', $newId, ['name' => $name], null);
            flash('success', 'Category created.');
            redirect('admin/categories.php');
        }
    }

    if ($postAction === 'update' && $categoryId > 0) {
        $category = category_by_id($categoryId);
        if (!$category) {
            flash('danger', 'Category not found.');
            redirect('admin/categories.php');
        }
        $name = trim((string) post('name', ''));
        $slug = trim((string) post('slug', ''));
        $parentId = (int) post('parent_id', 0);
        $description = trim((string) post('description', ''));
        $icon = trim((string) post('icon', '🏷️'));
        $isPopular = !empty(post('is_popular'));
        $sortOrder = (int) post('sort_order', 0);
        $defaultTemplateId = (int) post('default_template_id', 0);
        $moduleIds = array_map('intval', (array) (post('module_ids', []) ?: []));

        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        }
        if ($slug !== $category['slug'] && db_val('SELECT id FROM categories WHERE slug = ?', [$slug])) {
            $errors['slug'] = 'That slug is already in use.';
        }
        if ($parentId > 0) {
            if ($parentId === $categoryId) {
                $errors['parent_id'] = 'A category cannot be its own parent.';
            } elseif (!category_by_id($parentId)) {
                $errors['parent_id'] = 'Invalid parent category.';
            }
        }
        if ($defaultTemplateId > 0 && !db_row('SELECT id FROM website_templates WHERE id = ?', [$defaultTemplateId])) {
            $errors['default_template_id'] = 'Invalid template.';
        }

        if (!$errors) {
            db_update('categories', [
                'parent_id' => $parentId > 0 ? $parentId : null,
                'name' => str_limit($name, 190),
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'icon' => $icon !== '' ? $icon : '🏷️',
                'is_popular' => $isPopular ? 1 : 0,
                'sort_order' => $sortOrder,
                'default_template_id' => $defaultTemplateId > 0 ? $defaultTemplateId : null,
            ], 'id = :id', ['id' => $categoryId]);
            db_exec('DELETE FROM category_modules WHERE category_id = ?', [$categoryId]);
            foreach ($moduleIds as $modId) {
                db_exec('INSERT IGNORE INTO category_modules (category_id, module_id, is_default) VALUES (?, ?, 1)', [$categoryId, $modId]);
            }
            activity_log('admin.category_updated', 'category', $categoryId, ['name' => $name], null);
            flash('success', 'Category updated.');
            redirect('admin/categories.php');
        }
    }

    if ($postAction === 'delete' && $categoryId > 0) {
        $category = category_by_id($categoryId);
        if (!$category) {
            flash('danger', 'Category not found.');
        } elseif ((int) db_val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$categoryId]) > 0) {
            flash('danger', 'Delete child categories first.');
        } else {
            db_exec('DELETE FROM categories WHERE id = ?', [$categoryId]);
            activity_log('admin.category_deleted', 'category', $categoryId, ['name' => $category['name']], null);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }

    if ($postAction === 'import_json') {
        $file = $_FILES['import_file'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            try {
                $result = import_categories_from_json($file['tmp_name']);
                flash('success', "Imported {$result['created']} new categories, updated {$result['updated']} existing.");
            } catch (Throwable $e) {
                flash('danger', 'Import failed: ' . $e->getMessage());
            }
        } else {
            flash('danger', 'Please select a valid JSON file.');
        }
        redirect('admin/categories.php');
    }

    if ($postAction === 'import_uraio') {
        try {
            $result = import_categories_from_uraio_database();
            flash('success', "Imported {$result['created']} new categories, updated {$result['updated']} from Uraio discovery.");
        } catch (Throwable $e) {
            flash('danger', 'Uraio import failed: ' . $e->getMessage());
        }
        redirect('admin/categories.php');
    }
}

$allCategories = all_categories(false);
$parents = array_values(array_filter($allCategories, fn ($c) => empty($c['parent_id'])));
$children = [];
foreach ($allCategories as $cat) {
    if ($cat['parent_id']) {
        $children[(int) $cat['parent_id']][] = $cat;
    }
}

$editCategory = null;
if ($action === 'edit' && $categoryId > 0) {
    $editCategory = category_by_id($categoryId);
    if (!$editCategory) {
        flash('danger', 'Category not found.');
        redirect('admin/categories.php');
    }
    $editCategory['modules'] = array_column(category_modules($categoryId), 'id');
}

$newCategory = $action === 'new';

admin_page_start($user, 'categories', 'Categories', 'Manage business categories, modules and templates');
?>
<div class="page-head">
  <div>
    <h1>Categories</h1>
    <p>Hierarchical business categories. Each category can have assigned modules and a default website template.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(app_url('admin/categories.php?action=new')) ?>"><?= icon('plus', 'icon icon-sm') ?> New category</a>
    <div class="btn-group">
      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/categories.php?action=import')) ?>"><?= icon('upload', 'icon icon-sm') ?> Import JSON</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/categories.php?action=import_uraio')) ?>"><?= icon('database', 'icon icon-sm') ?> Import from Uraio</a>
    </div>
  </div>
</div>

<?php admin_flashes(); ?>

<?php if ($newCategory || $editCategory): ?>
  <div class="card mb-2">
    <div class="card-head"><h2 class="mt-0"><?= $editCategory ? 'Edit category' : 'New category' ?></h2></div>
    <form method="post" action="<?= e(app_url('admin/categories.php' . ($editCategory ? '?action=edit&id=' . $editCategory['id'] : '?action=create'))) ?>" class="card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
      <div class="form-grid">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="<?= e($editCategory['name'] ?? old('name', '')) ?>" required maxlength="190">
          <?= render_field_error($errors, 'name') ?>
        </div>
        <div class="field">
          <label for="slug">Slug</label>
          <input type="text" id="slug" name="slug" value="<?= e($editCategory['slug'] ?? old('slug', '')) ?>" pattern="[a-z0-9-]+" required maxlength="190">
          <?= render_field_error($errors, 'slug') ?>
        </div>
        <div class="field">
          <label for="parent_id">Parent category</label>
          <select id="parent_id" name="parent_id">
            <option value="">— None (top-level) —</option>
            <?php foreach ($parents as $p): ?>
              <?php if ($editCategory && (int) $p['id'] === (int) $editCategory['id']) continue; ?>
              <option value="<?= (int) $p['id'] ?>" <?= selected($editCategory['parent_id'] ?? old('parent_id', 0), $p['id']) ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?= render_field_error($errors, 'parent_id') ?>
        </div>
        <div class="field">
          <label for="icon">Icon (emoji)</label>
          <input type="text" id="icon" name="icon" value="<?= e($editCategory['icon'] ?? old('icon', '🏷️')) ?>" maxlength="8" placeholder="🏷️">
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" value="<?= e($editCategory['sort_order'] ?? old('sort_order', 0)) ?>" min="0">
        </div>
        <div class="field">
          <label for="default_template_id">Default template</label>
          <select id="default_template_id" name="default_template_id">
            <option value="">— Platform default —</option>
            <?php foreach ($templates as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= selected($editCategory['default_template_id'] ?? old('default_template_id', 0), $t['id']) ?>><?= e($t['name']) ?> (<?= e($t['slug']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field-check">
          <input type="checkbox" id="is_popular" name="is_popular" value="1" <?= !empty($editCategory['is_popular']) || !empty(old('is_popular')) ? 'checked' : '' ?>>
          <label for="is_popular">Popular category</label>
        </div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e($editCategory['description'] ?? old('description', '')) ?></textarea>
      </div>

      <h3 style="margin:24px 0 12px">Assigned modules</h3>
      <div class="form-grid">
        <?php foreach ($modules as $mod): ?>
          <?php $checked = in_array((int) $mod['id'], $editCategory['modules'] ?? [], true) || in_array((int) $mod['id'], (array) old('module_ids', []), true); ?>
          <label class="field-check" style="flex:1 0 180px">
            <input type="checkbox" name="module_ids[]" value="<?= (int) $mod['id'] ?>" <?= $checked ? 'checked' : '' ?>>
            <span><?= e($mod['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save category</button>
        <a class="btn btn-ghost" href="<?= e(app_url('admin/categories.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>
<?php elseif ($action === 'import'): ?>
  <div class="card mb-2">
    <div class="card-head"><h2 class="mt-0">Import categories from JSON</h2></div>
    <div class="card-body">
      <p class="small muted">Upload a JSON file with the category structure. Existing categories with matching slugs will be updated.</p>
      <form method="post" action="<?= e(app_url('admin/categories.php')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_json">
        <div class="field">
          <label for="import_file">JSON file</label>
          <input type="file" id="import_file" name="import_file" accept=".json" required>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('upload', 'icon icon-sm') ?> Import</button>
      </form>
    </div>
  </div>
<?php elseif ($action === 'import_uraio'): ?>
  <div class="card mb-2">
    <div class="card-head"><h2 class="mt-0">Import categories from Uraio Smart Discovery</h2></div>
    <div class="card-body">
      <div class="alert alert-warning"><?= icon('alert') ?><div>This reads from the configured Uraio discovery database (settings: uraio_db_host, uraio_db_name, uraio_db_user, uraio_db_pass). Only id, name, slug and icon are imported. The adapter is read-only and never writes to the discovery database.</div></div>
      <form method="post" action="<?= e(app_url('admin/categories.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_uraio">
        <button class="btn btn-primary" type="submit"><?= icon('database', 'icon icon-sm') ?> Import from Uraio</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Category</th><th>Slug</th><th>Parent</th><th>Modules</th><th>Template</th><th>Popular</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($parents as $parent): ?>
              <?php $modCount = count(category_modules((int) $parent['id'])); $tmpl = $parent['default_template_id'] ? db_row('SELECT name FROM website_templates WHERE id = ?', [$parent['default_template_id']]) : null; ?>
              <tr>
                <td><strong><?= e($parent['icon'] ?? '🏷️') ?> <?= e($parent['name']) ?></strong><span class="cell-sub mono">#<?= (int) $parent['id'] ?></span></td>
                <td class="mono"><?= e($parent['slug']) ?></td>
                <td class="muted">—</td>
                <td class="small muted"><?= e((string) $modCount) ?> modules</td>
                <td class="small muted"><?= $tmpl ? e($tmpl['name']) : '—' ?></td>
                <td><?= !empty($parent['is_popular']) ? '<span class="badge badge-info">Yes</span>' : '<span class="muted">No</span>' ?></td>
                <td><?= status_badge((string) $parent['status']) ?></td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/categories.php?action=edit&id=' . $parent['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?></a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/categories.php?action=new&parent=' . $parent['id'])) ?>"><?= icon('plus', 'icon icon-sm') ?> Add child</a>
                    <form method="post" action="<?= e(app_url('admin/categories.php')) ?>" data-confirm="Delete <?= e($parent['name']) ?> and all its children?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $parent['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 'icon icon-sm') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php foreach ($children[(int) $parent['id']] ?? [] as $child): ?>
                <?php $modCount = count(category_modules((int) $child['id'])); $tmpl = $child['default_template_id'] ? db_row('SELECT name FROM website_templates WHERE id = ?', [$child['default_template_id']]) : null; ?>
                <tr>
                  <td><span style="padding-left:24px">↳ <?= e($child['icon'] ?? '🏷️') ?> <?= e($child['name']) ?></span><span class="cell-sub mono">#<?= (int) $child['id'] ?></span></td>
                  <td class="mono"><?= e($child['slug']) ?></td>
                  <td class="small muted"><?= e($parent['name']) ?></td>
                  <td class="small muted"><?= e((string) $modCount) ?> modules</td>
                  <td class="small muted"><?= $tmpl ? e($tmpl['name']) : '—' ?></td>
                  <td><?= !empty($child['is_popular']) ? '<span class="badge badge-info">Yes</span>' : '<span class="muted">No</span>' ?></td>
                  <td><?= status_badge((string) $child['status']) ?></td>
                  <td>
                    <div class="table-actions">
                      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/categories.php?action=edit&id=' . $child['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?></a>
                      <form method="post" action="<?= e(app_url('admin/categories.php')) ?>" data-confirm="Delete <?= e($child['name']) ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $child['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 'icon icon-sm') ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php
admin_page_end(['scripts' => ['app.js']]);