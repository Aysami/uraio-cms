<?php
/**
 * Admin: editable email templates.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();
$templates = db_all("SELECT * FROM email_templates ORDER BY name ASC");

if (is_post()) {
    verify_csrf();
    $id = (int) post('template_id', 0);
    if ($id > 0) {
        $template = db_row('SELECT * FROM email_templates WHERE id = ?', [$id]);
        if (!$template) {
            flash('danger', 'Template not found.');
            redirect('admin/email-templates.php');
        }
        $subject = trim((string) post('subject', ''));
        $body = (string) post('body', '');
        $status = (string) post('status', 'active');
        if ($subject === '') {
            flash('danger', 'Subject is required.');
        } elseif (trim($body) === '') {
            flash('danger', 'Email body is required.');
        } else {
            db_update('email_templates', [
                'subject' => str_limit($subject, 255),
                'body' => $body,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            ], 'id = :id', ['id' => $id]);
            activity_log('admin.email_template_updated', 'email_template', $id, ['slug' => $template['slug']], null);
            flash('success', 'Email template saved.');
        }
        redirect('admin/email-templates.php?edit=' . $id);
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editTemplate = $editId > 0 ? db_row('SELECT * FROM email_templates WHERE id = ?', [$editId]) : null;

admin_page_start($user, 'settings', 'Email templates', 'Edit notification emails');
?>
<div class="page-head">
  <div>
    <h1>Email templates</h1>
    <p>Notification emails sent to users and business owners. Use <code>{{variable}}</code> placeholders.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('admin/settings.php')) ?>"><?= icon('chevron-left', 'icon icon-sm') ?> Back to settings</a>
  </div>
</div>

<?php admin_flashes(); ?>

<div class="grid grid-2-1">
  <div>
    <?php if ($editTemplate): ?>
      <div class="card">
        <div class="card-head">
          <div>
            <h2 class="mt-0"><?= e($editTemplate['name']) ?></h2>
            <p class="small muted mono" style="margin:4px 0 0"><?= e($editTemplate['slug']) ?></p>
          </div>
          <?= status_badge((string) $editTemplate['status']) ?>
        </div>
        <form method="post" action="<?= e(app_url('admin/email-templates.php')) ?>" class="card-body">
          <?= csrf_field() ?>
          <input type="hidden" name="template_id" value="<?= (int) $editTemplate['id'] ?>">
          <div class="field">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" value="<?= e($editTemplate['subject']) ?>" required maxlength="255">
          </div>
          <div class="field">
            <label for="body">Body (HTML)</label>
            <textarea id="body" name="body" rows="14" required style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem"><?= e($editTemplate['body']) ?></textarea>
            <span class="field-help">Use {{name}}, {{business}}, {{app_url}} and template-specific variables.</span>
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="active" <?= selected($editTemplate['status'], 'active') ?>>Active</option>
              <option value="inactive" <?= selected($editTemplate['status'], 'inactive') ?>>Inactive</option>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save template</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-body">
          <p class="muted">Select a template to edit. Templates control the emails sent for account, website, booking, order and payment events.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2 class="mt-0">All templates</h2></div>
      <div class="card-body" style="padding:8px">
        <div class="side-nav">
          <?php foreach ($templates as $t): ?>
            <a class="side-link<?= $editId === (int) $t['id'] ? ' is-active' : '' ?>" href="<?= e(app_url('admin/email-templates.php?edit=' . $t['id'])) ?>">
              <?= icon('mail') ?>
              <span style="min-width:0"><strong class="truncate" style="display:block"><?= e($t['name']) ?></strong><span class="muted small mono"><?= e($t['slug']) ?></span></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
admin_page_end(['scripts' => ['app.js']]);