<?php
/**
 * Website pages: create, edit metadata, set the home page, reorder and remove.
 * Every lookup is scoped to the active business.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$website = business_website($businessId);
if (!$website) {
    flash('info', 'Create your website first, then you can manage its pages.');
    redirect('app/website.php');
}

$errors = [];

function page_slug_unique(int $businessId, string $slug, ?int $ignoreId = null): string
{
    $base = slugify($slug, 'page');
    if ($base === 'home') {
        $base = 'home-page';
    }
    $candidate = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM website_pages WHERE business_id = ? AND slug = ?';
        $params = [$businessId, $candidate];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        if ((int) db_val($sql, $params) === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $i++;
    }
}

if (is_post()) {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can manage website pages.');
        redirect('app/pages.php');
    }
    $action = (string) post('action', '');
    $pageId = (int) post('page_id', 0);
    $page = $pageId > 0 ? db_row('SELECT * FROM website_pages WHERE id = ? AND business_id = ?', [$pageId, $businessId]) : null;

    try {
        if ($action === 'create') {
            $title = trim((string) post('title', ''));
            if ($title === '') {
                throw new InvalidArgumentException('Enter a page title.');
            }
            $slugInput = trim((string) post('slug', '')) ?: $title;
            $slug = page_slug_unique($businessId, $slugInput);
            $next = (int) db_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM website_pages WHERE business_id = ?', [$businessId]);
            $newId = db_insert('website_pages', [
                'business_id' => $businessId,
                'website_id' => (int) $website['id'],
                'title' => str_limit($title, 190),
                'slug' => $slug,
                'type' => 'custom',
                'meta_title' => str_limit($title, 190),
                'meta_description' => str_limit(trim((string) post('meta_description', '')), 320),
                'is_home' => 0,
                'status' => 'draft',
                'sort_order' => $next,
            ]);
            $heading = trim((string) post('heading', '')) ?: $title;
            $content = [
                'eyebrow' => $business['category_name'] ?? 'Page',
                'heading' => $heading,
                'subheading' => trim((string) post('intro', '')),
                'image' => (string) ($business['cover'] ?? ''),
            ];
            db_insert('website_sections', [
                'page_id' => $newId,
                'type' => 'page_header',
                'title' => $heading,
                'subtitle' => $content['subheading'],
                'content' => json_encode($content),
                'sort_order' => 0,
                'is_visible' => 1,
            ]);
            activity_log('page.created', 'website_page', $newId, ['title' => $title], $businessId);
            flash('success', 'Page created. Add sections to build it out.');
            redirect('app/editor.php?page=' . $newId);
        }

        if ($action === 'update' && $page) {
            $title = trim((string) post('title', ''));
            if ($title === '') {
                throw new InvalidArgumentException('Enter a page title.');
            }
            $slug = (int) $page['is_home'] === 1
                ? 'home'
                : page_slug_unique($businessId, trim((string) post('slug', '')) ?: $title, $pageId);
            $status = post('status') === 'published' ? 'published' : 'draft';
            db_update('website_pages', [
                'title' => str_limit($title, 190),
                'slug' => $slug,
                'meta_title' => str_limit(trim((string) post('meta_title', '')), 190) ?: null,
                'meta_description' => str_limit(trim((string) post('meta_description', '')), 320) ?: null,
                'status' => $status,
            ], 'id = :id', ['id' => $pageId]);
            activity_log('page.updated', 'website_page', $pageId, ['title' => $title], $businessId);
            flash('success', 'Page settings saved.');
            redirect('app/pages.php');
        }

        if ($action === 'set_home' && $page) {
            db_exec('UPDATE website_pages SET is_home = 0 WHERE business_id = ?', [$businessId]);
            db_update('website_pages', ['is_home' => 1, 'slug' => 'home', 'status' => 'published'], 'id = :id', ['id' => $pageId]);
            activity_log('page.home_set', 'website_page', $pageId, [], $businessId);
            flash('success', $page['title'] . ' is now the home page.');
            redirect('app/pages.php');
        }

        if ($action === 'delete' && $page) {
            if ((int) $page['is_home'] === 1) {
                throw new InvalidArgumentException('Choose another home page before deleting this one.');
            }
            if ((int) db_val('SELECT COUNT(*) FROM website_pages WHERE business_id = ?', [$businessId]) <= 1) {
                throw new InvalidArgumentException('A website needs at least one page.');
            }
            db_exec('DELETE FROM website_pages WHERE id = ? AND business_id = ?', [$pageId, $businessId]);
            activity_log('page.deleted', 'website_page', $pageId, ['title' => $page['title']], $businessId);
            flash('success', 'Page deleted.');
            redirect('app/pages.php');
        }

        if ($action === 'move' && $page) {
            $direction = post('direction') === 'up' ? 'up' : 'down';
            $operator = $direction === 'up' ? '<' : '>';
            $order = $direction === 'up' ? 'DESC' : 'ASC';
            $neighbour = db_row(
                "SELECT * FROM website_pages WHERE business_id = ? AND sort_order $operator ? ORDER BY sort_order $order, id $order LIMIT 1",
                [$businessId, $page['sort_order']]
            );
            if ($neighbour) {
                db_update('website_pages', ['sort_order' => $neighbour['sort_order']], 'id = :id', ['id' => $pageId]);
                db_update('website_pages', ['sort_order' => $page['sort_order']], 'id = :id', ['id' => $neighbour['id']]);
            }
            redirect('app/pages.php');
        }
    } catch (InvalidArgumentException $e) {
        $errors['form'] = $e->getMessage();
    } catch (Throwable $e) {
        $errors['form'] = 'Could not complete that page action.';
        error_log('page action failed: ' . $e->getMessage());
    }
}

$editId = (int) query('edit', 0);
$editPage = $editId > 0 ? db_row('SELECT * FROM website_pages WHERE id = ? AND business_id = ?', [$editId, $businessId]) : null;
$pages = website_pages($businessId);

layout_app_start($business, 'pages', 'Pages', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Pages</h1>
    <p>Structure your website and choose what appears in the navigation.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('app/website.php')) ?>">Website overview</a>
    <button class="btn btn-primary" type="button" data-open-modal="new-page-modal"><?= icon('plus', 'icon icon-sm') ?> New page</button>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

<div class="card">
  <?php if (!$pages): ?>
    <div class="empty-state">
      <div class="empty-icon"><?= icon('layout') ?></div>
      <h3>No pages yet</h3>
      <p>Rebuild your starter website or create a page from scratch.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Page</th><th>Address</th><th>Status</th><th>Sections</th><th class="text-right">Order</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pages as $index => $page): ?>
            <?php $sectionCount = count(website_sections((int) $page['id'])); ?>
            <tr>
              <td>
                <a class="cell-main" href="<?= e(app_url('app/editor.php?page=' . $page['id'])) ?>"><?= e($page['title']) ?></a>
                <span class="cell-sub"><?= (int) $page['is_home'] === 1 ? 'Home page' : e(ucfirst((string) $page['type'])) ?></span>
              </td>
              <td class="mono small">/<?= e($page['slug'] === 'home' ? '' : $page['slug']) ?></td>
              <td><?= status_badge((string) $page['status']) ?></td>
              <td><?= $sectionCount ?></td>
              <td class="text-right">
                <div class="table-actions">
                  <form method="post" action="<?= e(app_url('app/pages.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                    <input type="hidden" name="direction" value="up">
                    <button class="btn btn-ghost btn-sm" type="submit" aria-label="Move up" <?= $index === 0 ? 'disabled' : '' ?>><?= icon('arrow-up', 'icon icon-sm') ?></button>
                  </form>
                  <form method="post" action="<?= e(app_url('app/pages.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                    <input type="hidden" name="direction" value="down">
                    <button class="btn btn-ghost btn-sm" type="submit" aria-label="Move down" <?= $index === count($pages) - 1 ? 'disabled' : '' ?>><?= icon('arrow-down', 'icon icon-sm') ?></button>
                  </form>
                </div>
              </td>
              <td>
                <div class="table-actions">
                  <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/editor.php?page=' . $page['id'])) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit</a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/pages.php?edit=' . $page['id'])) ?>">Settings</a>
                  <?php if ((int) $page['is_home'] !== 1): ?>
                    <form method="post" action="<?= e(app_url('app/pages.php')) ?>" data-confirm="Make “<?= e($page['title']) ?>” the home page? The current home page will become a normal page.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_home">
                      <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit">Set home</button>
                    </form>
                    <form method="post" action="<?= e(app_url('app/pages.php')) ?>" data-confirm="Delete this page and all of its sections?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" aria-label="Delete"><?= icon('trash', 'icon icon-sm') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($editPage): ?>
  <div class="card mt-3">
    <div class="card-head">
      <div><h2 class="mt-0">Page settings</h2><p class="small muted" style="margin:4px 0 0"><?= e($editPage['title']) ?></p></div>
      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/pages.php')) ?>">Close</a>
    </div>
    <div class="card-body">
      <form method="post" action="<?= e(app_url('app/pages.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>">
        <div class="form-grid">
          <div class="field field-col-6">
            <label for="page-title">Page title</label>
            <input id="page-title" type="text" name="title" value="<?= e($editPage['title']) ?>" required>
          </div>
          <div class="field field-col-6">
            <label for="page-slug">Address</label>
            <div class="input-prefix"><span>/</span><input id="page-slug" type="text" name="slug" value="<?= e($editPage['slug']) ?>" <?= (int) $editPage['is_home'] === 1 ? 'readonly' : '' ?>></div>
            <span class="field-help"><?= (int) $editPage['is_home'] === 1 ? 'The home page always uses the root address.' : 'Lowercase letters, numbers and hyphens.' ?></span>
          </div>
          <div class="field field-col-6">
            <label for="page-meta-title">SEO title</label>
            <input id="page-meta-title" type="text" name="meta_title" value="<?= e($editPage['meta_title'] ?? '') ?>" maxlength="190">
          </div>
          <div class="field field-col-6">
            <label for="page-status">Status</label>
            <select id="page-status" name="status">
              <option value="published" <?= selected($editPage['status'], 'published') ?>>Published</option>
              <option value="draft" <?= selected($editPage['status'], 'draft') ?>>Draft</option>
            </select>
          </div>
          <div class="field field-col-12">
            <label for="page-meta-description">SEO description</label>
            <textarea id="page-meta-description" name="meta_description" rows="3" maxlength="320"><?= e($editPage['meta_description'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save page</button>
          <a class="btn btn-ghost" href="<?= e(app_url('app/editor.php?page=' . $editPage['id'])) ?>">Edit sections</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="modal-backdrop" id="new-page-modal" role="dialog" aria-modal="true" aria-labelledby="new-page-title">
  <div class="modal">
    <div class="flex-between mb-2">
      <h2 id="new-page-title" class="mt-0">New page</h2>
      <button class="icon-btn" type="button" data-close-modal aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form method="post" action="<?= e(app_url('app/pages.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="new-title">Page title <span class="req">*</span></label>
        <input id="new-title" type="text" name="title" required placeholder="e.g. Our services">
      </div>
      <div class="field">
        <label for="new-slug">Address</label>
        <div class="input-prefix"><span>/</span><input id="new-slug" type="text" name="slug" placeholder="our-services"></div>
        <span class="field-help">Leave blank to generate it from the title.</span>
      </div>
      <div class="field">
        <label for="new-heading">Page heading</label>
        <input id="new-heading" type="text" name="heading" placeholder="Shown at the top of the page">
      </div>
      <div class="field">
        <label for="new-intro">Introduction</label>
        <textarea id="new-intro" name="intro" rows="3" placeholder="A short introduction under the heading"></textarea>
      </div>
      <div class="field">
        <label for="new-meta">SEO description</label>
        <textarea id="new-meta" name="meta_description" rows="2" maxlength="320"></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-close-modal>Cancel</button>
        <button class="btn btn-primary" type="submit">Create page</button>
      </div>
    </form>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
