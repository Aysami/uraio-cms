<?php
/**
 * Website section editor. Sections are edited as structured forms with a
 * same-origin live preview. Every section is verified against the business.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$website = business_website($businessId);
if (!$website) {
    flash('info', 'Create your website before editing content.');
    redirect('app/website.php');
}

$pages = website_pages($businessId);
if (!$pages) {
    flash('warning', 'Your website has no pages yet. Rebuild the starter pages from the website overview.');
    redirect('app/website.php');
}

$pageId = (int) query('page', (int) $pages[0]['id']);
$page = null;
foreach ($pages as $candidate) {
    if ((int) $candidate['id'] === $pageId) {
        $page = $candidate;
        break;
    }
}
if (!$page) {
    $page = $pages[0];
    $pageId = (int) $page['id'];
}

$schemas = section_schemas();
$errors = [];
$editingSection = null;

function editor_section_input(string $type, array $content): array
{
    return ['type' => $type, 'content' => $content, 'is_visible' => 1];
}

if (is_post()) {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can edit the website.');
        redirect('app/editor.php?page=' . $pageId);
    }
    $action = (string) post('action', '');
    $sectionId = (int) post('section_id', 0);

    try {
        if ($action === 'add') {
            $type = (string) post('type', '');
            if (!isset($schemas[$type])) {
                throw new InvalidArgumentException('Choose a valid section type.');
            }
            $defaults = section_defaults($type, $business, (string) ($website['template_slug'] ?? 'aurora'));
            $newId = save_website_section($business, $pageId, editor_section_input($type, $defaults));
            activity_log('section.created', 'website_section', $newId, ['type' => $type], $businessId);
            flash('success', $schemas[$type]['label'] . ' section added.');
            redirect('app/editor.php?page=' . $pageId . '&section=' . $newId);
        }

        if ($action === 'save') {
            $section = website_section_by_id($businessId, $sectionId);
            if (!$section) {
                throw new InvalidArgumentException('Section not found.');
            }
            save_website_section($business, $pageId, $_POST, $sectionId);
            activity_log('section.updated', 'website_section', $sectionId, ['type' => $section['type']], $businessId);
            flash('success', 'Section saved. Your preview has been refreshed.');
            redirect('app/editor.php?page=' . $pageId . '&section=' . $sectionId);
        }

        if ($action === 'move') {
            $direction = post('direction') === 'up' ? 'up' : 'down';
            if (move_website_section($business, $sectionId, $direction)) {
                flash('success', 'Section moved.');
            }
            redirect('app/editor.php?page=' . $pageId . '&section=' . $sectionId);
        }

        if ($action === 'toggle') {
            $section = website_section_by_id($businessId, $sectionId);
            if ($section) {
                db_update('website_sections', ['is_visible' => (int) !$section['is_visible']], 'id = :id', ['id' => $sectionId]);
                activity_log('section.visibility', 'website_section', $sectionId, ['visible' => !$section['is_visible']], $businessId);
                flash('success', (int) $section['is_visible'] ? 'Section hidden.' : 'Section shown.');
            }
            redirect('app/editor.php?page=' . $pageId . '&section=' . $sectionId);
        }

        if ($action === 'delete') {
            if (delete_website_section($business, $sectionId)) {
                activity_log('section.deleted', 'website_section', $sectionId, [], $businessId);
                flash('success', 'Section deleted.');
            }
            redirect('app/editor.php?page=' . $pageId);
        }
    } catch (InvalidArgumentException $e) {
        $errors['form'] = $e->getMessage();
    } catch (Throwable $e) {
        $errors['form'] = 'Could not save this section. Please try again.';
        error_log('section save failed: ' . $e->getMessage());
    }
}

$sectionId = (int) query('section', 0);
if ($sectionId > 0) {
    $editingSection = website_section_by_id($businessId, $sectionId);
    if ($editingSection && (int) $editingSection['page_id'] !== $pageId) {
        $editingSection = null;
    }
}

$sections = website_sections($pageId);
$editingType = $editingSection['type'] ?? '';
$editingContent = $editingSection ? json_decode_assoc($editingSection['content'] ?? null) : [];
$previewUrl = app_url('site.php?preview=1&page=' . urlencode((string) $page['slug']));

function editor_field(string $key, array $field, mixed $value): string
{
    $type = $field['type'] ?? 'text';
    $label = (string) ($field['label'] ?? ucwords(str_replace('_', ' ', $key)));
    $id = 'field-' . preg_replace('/[^a-z0-9_-]/i', '-', $key);
    $html = '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . e($id) . '" name="content[' . e($key) . ']" rows="' . (int) ($field['rows'] ?? 3) . '">' . e((string) $value) . '</textarea>';
    } elseif ($type === 'select') {
        $html .= '<select id="' . e($id) . '" name="content[' . e($key) . ']">';
        foreach (($field['options'] ?? []) as $optionValue => $optionLabel) {
            $html .= '<option value="' . e($optionValue) . '" ' . selected($value, $optionValue) . '>' . e($optionLabel) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'number') {
        $html .= '<input id="' . e($id) . '" type="number" name="content[' . e($key) . ']" value="' . e((string) $value) . '">';
    } elseif ($type === 'url') {
        $html .= '<input id="' . e($id) . '" type="url" name="content[' . e($key) . ']" value="' . e((string) $value) . '" placeholder="https://">';
    } else {
        $html .= '<input id="' . e($id) . '" type="text" name="content[' . e($key) . ']" value="' . e((string) $value) . '">';
    }
    $html .= '</div>';
    return $html;
}

layout_app_start($business, 'pages', 'Content editor', $business['name'] . ' · ' . $page['title']);
?>
<div class="editor-shell">
  <div class="editor-main">
    <div class="page-head">
      <div>
        <div class="breadcrumb"><a href="<?= e(app_url('app/pages.php')) ?>">Pages</a> <span>/</span> <span><?= e($page['title']) ?></span></div>
        <h1><?= e($editingSection ? 'Edit ' . strtolower($schemas[$editingType]['label'] ?? 'section') : 'Page content') ?></h1>
        <p><?= $editingSection ? 'Changes appear in the preview as soon as you save.' : 'Add, reorder and edit the sections on this page.' ?></p>
      </div>
      <div class="page-actions">
        <form method="get" action="<?= e(app_url('app/editor.php')) ?>">
          <input type="hidden" name="page" value="<?= $pageId ?>">
          <select name="page" onchange="this.form.submit()" aria-label="Choose page" style="max-width:190px">
            <?php foreach ($pages as $candidate): ?>
              <option value="<?= (int) $candidate['id'] ?>" <?= selected($pageId, $candidate['id']) ?>><?= e($candidate['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <a class="btn btn-ghost" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> Preview</a>
      </div>
    </div>

    <?php layout_flashes(); ?>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

    <?php if ($editingSection): ?>
      <div class="card mb-2">
        <div class="card-head">
          <div class="flex">
            <span class="section-type-icon"><?= icon($schemas[$editingType]['icon'] ?? 'layout') ?></span>
            <div><h2 class="mt-0"><?= e($schemas[$editingType]['label'] ?? ucfirst($editingType)) ?></h2><p class="small muted" style="margin:3px 0 0">Section <?= (int) array_search($editingSection['id'], array_column($sections, 'id'), true) + 1 ?> of <?= count($sections) ?></p></div>
          </div>
          <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>"><?= icon('x', 'icon icon-sm') ?> Close</a>
        </div>
        <div class="card-body">
          <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId . '&section=' . $editingSection['id'])) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="section_id" value="<?= (int) $editingSection['id'] ?>">
            <div class="form-grid">
              <?php foreach (($schemas[$editingType]['fields'] ?? []) as $key => $field): ?>
                <?php $value = $editingContent[$key] ?? ''; ?>
                <?php if (($field['type'] ?? 'text') === 'image'): ?>
                  <div class="field field-col-6">
                    <label for="field-<?= e($key) ?>"><?= e($field['label'] ?? 'Image') ?></label>
                    <?php if (!empty($value)): ?><img class="image-preview mb-1" src="<?= e(media_url((string) $value)) ?>" alt=""><?php endif; ?>
                    <input type="hidden" name="content[existing_<?= e($key) ?>]" value="<?= e((string) $value) ?>">
                    <input id="field-<?= e($key) ?>" type="file" name="section_files[<?= e($key) ?>]" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                    <span class="field-help">Upload a new image to replace the current one.</span>
                  </div>
                <?php elseif (($field['type'] ?? 'text') === 'repeater'): ?>
                  <?php $rows = is_array($value) ? $value : []; ?>
                  <div class="field field-col-12">
                    <label><?= e($field['label'] ?? 'Items') ?></label>
                    <div class="repeater" data-repeater>
                      <div class="repeater-rows">
                        <?php foreach ($rows as $rowIndex => $row): ?>
                          <?= editor_repeater_row($key, (int) $rowIndex, $field['fields'] ?? [], $row) ?>
                        <?php endforeach; ?>
                      </div>
                      <template data-repeater-template><?= editor_repeater_row($key, '__i__', $field['fields'] ?? [], []) ?></template>
                      <button class="btn btn-ghost btn-sm" type="button" data-repeater-add><?= icon('plus', 'icon icon-sm') ?> Add row</button>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="<?= in_array(($field['type'] ?? 'text'), ['textarea'], true) ? 'field-col-12' : 'field-col-6' ?>">
                    <?= editor_field($key, $field, $value) ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save section</button>
              <label class="switch-label"><input type="checkbox" name="is_visible" value="1" <?= checked($editingSection['is_visible']) ?>> Visible on website</label>
            </div>
          </form>
          <div class="flex mt-3" style="border-top:1px solid var(--line);padding-top:16px;flex-wrap:wrap">
            <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="section_id" value="<?= (int) $editingSection['id'] ?>">
              <input type="hidden" name="direction" value="up">
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon('arrow-up', 'icon icon-sm') ?> Move up</button>
            </form>
            <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="section_id" value="<?= (int) $editingSection['id'] ?>">
              <input type="hidden" name="direction" value="down">
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon('arrow-down', 'icon icon-sm') ?> Move down</button>
            </form>
            <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>" data-confirm="Delete this section?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="section_id" value="<?= (int) $editingSection['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" style="margin-left:auto"><?= icon('trash', 'icon icon-sm') ?> Delete section</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <div><h2 class="mt-0">Sections on “<?= e($page['title']) ?>”</h2><p class="small muted" style="margin:4px 0 0">Top to bottom is the order visitors see.</p></div>
        <button class="btn btn-primary btn-sm" type="button" data-open-modal="add-section-modal"><?= icon('plus', 'icon icon-sm') ?> Add section</button>
      </div>
      <div class="section-list">
        <?php if (!$sections): ?>
          <div class="empty-state">
            <div class="empty-icon"><?= icon('layout') ?></div>
            <h3>This page is empty</h3>
            <p>Add a section to start building the page.</p>
            <button class="btn btn-primary" type="button" data-open-modal="add-section-modal">Add a section</button>
          </div>
        <?php endif; ?>
        <?php foreach ($sections as $index => $section): ?>
          <?php $type = (string) $section['type']; ?>
          <div class="section-row<?= (int) $section['is_visible'] ? '' : ' is-hidden-section' ?>">
            <span class="drag-handle"><?= icon($schemas[$type]['icon'] ?? 'layout') ?></span>
            <div class="section-row-meta">
              <a class="cell-main" href="<?= e(app_url('app/editor.php?page=' . $pageId . '&section=' . $section['id'])) ?>"><?= e($schemas[$type]['label'] ?? ucfirst($type)) ?></a>
              <span class="cell-sub"><?= e(str_limit($section['title'] ?? '', 90) ?: 'No heading yet') ?></span>
            </div>
            <?php if ((int) $section['is_visible'] === 0): ?><span class="badge badge-neutral">Hidden</span><?php endif; ?>
            <div class="section-row-actions">
              <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit" aria-label="<?= (int) $section['is_visible'] ? 'Hide' : 'Show' ?> section" title="<?= (int) $section['is_visible'] ? 'Hide' : 'Show' ?>"><?= icon((int) $section['is_visible'] ? 'eye' : 'lock', 'icon icon-sm') ?></button>
              </form>
              <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/editor.php?page=' . $pageId . '&section=' . $section['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <aside class="editor-preview">
    <div class="editor-preview-head">
      <span><?= icon('eye', 'icon icon-sm') ?> Live preview</span>
      <a class="small link" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener">Open</a>
    </div>
    <iframe src="<?= e($previewUrl) ?>" title="Website preview" loading="lazy"></iframe>
    <p class="small muted" style="padding:10px 14px">Draft pages are visible only to you. Publish from the website overview.</p>
  </aside>
</div>

<div class="modal-backdrop" id="add-section-modal" role="dialog" aria-modal="true" aria-labelledby="add-section-title">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2 id="add-section-title" class="mt-0">Add a section</h2>
      <button class="icon-btn" type="button" data-close-modal aria-label="Close"><?= icon('x') ?></button>
    </div>
    <p class="small muted">Choose a block to add to the bottom of “<?= e($page['title']) ?>”. You can reorder it afterwards.</p>
    <form method="post" action="<?= e(app_url('app/editor.php?page=' . $pageId)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="section-picker">
        <?php foreach ($schemas as $type => $schema): ?>
          <label class="section-option">
            <input type="radio" name="type" value="<?= e($type) ?>" required>
            <span class="section-option-icon"><?= icon($schema['icon'] ?? 'layout') ?></span>
            <span><strong><?= e($schema['label']) ?></strong><span class="small muted"><?= count($schema['fields']) ?> fields</span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-close-modal>Cancel</button>
        <button class="btn btn-primary" type="submit">Add section</button>
      </div>
    </form>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js', 'editor.js']]);

function editor_repeater_row(string $key, int|string $rowIndex, array $fields, array $row): string
{
    $html = '<div class="repeater-row"><div class="form-grid">';
    foreach ($fields as $subKey => $subField) {
        $type = $subField['type'] ?? 'text';
        $name = 'content[' . $key . '][' . $rowIndex . '][' . $subKey . ']';
        $value = (string) ($row[$subKey] ?? '');
        if ($type === 'image') {
            $html .= '<div class="field field-col-6"><label>Image</label>';
            if ($value !== '') {
                $html .= '<img class="image-preview" src="' . e(media_url($value)) . '" alt="">';
            }
            $html .= '<input type="hidden" name="content[' . e($key) . '][' . e((string) $rowIndex) . '][existing_' . e($subKey) . ']" value="' . e($value) . '">';
            $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
            $html .= '<input type="file" name="section_files[' . e($key) . '][' . e((string) $rowIndex) . '][' . e($subKey) . ']" accept="image/png,image/jpeg,image/webp,image/svg+xml">';
            $html .= '</div>';
        } elseif ($type === 'textarea') {
            $html .= '<div class="field field-col-12"><label>' . e($subField['label'] ?? ucfirst($subKey)) . '</label><textarea name="' . e($name) . '" rows="' . (int) ($subField['rows'] ?? 2) . '">' . e($value) . '</textarea></div>';
        } else {
            $html .= '<div class="field field-col-6"><label>' . e($subField['label'] ?? ucfirst($subKey)) . '</label><input type="text" name="' . e($name) . '" value="' . e($value) . '"></div>';
        }
    }
    $html .= '</div><button class="btn btn-ghost btn-sm repeater-remove" type="button" data-repeater-remove>' . icon('trash', 'icon icon-sm') . ' Remove row</button></div>';
    return $html;
}
