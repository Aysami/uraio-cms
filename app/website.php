<?php
/**
 * Website overview: publish state, pages, visits and quick actions.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();

$website = business_website((int) $business['id']);
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) post('action', '');
    if ($action === 'create' && !$website) {
        try {
            create_website_for_business($business);
            flash('success', 'Your starter website is ready. Edit it, then publish when you are happy.');
            redirect('app/website.php');
        } catch (Throwable $e) {
            $errors['form'] = 'Could not create the website. Please try again.';
            error_log('website create failed: ' . $e->getMessage());
        }
    }
    if ($action === 'publish' && $website) {
        if (!can('publish_website') && !is_admin_user($user)) {
            $errors['form'] = 'Your role cannot publish websites.';
        } elseif (publish_website($business)) {
            send_email_template('website_published', (string) $user['email'], [
                'name' => $user['name'],
                'business' => $business['name'],
                'website_url' => business_public_url($business),
            ]);
            flash('success', 'Website published. It is now live at ' . business_public_url($business) . '.');
            redirect('app/website.php');
        }
    }
    if ($action === 'unpublish' && $website) {
        if (!can('publish_website') && !is_admin_user($user)) {
            $errors['form'] = 'Your role cannot unpublish websites.';
        } elseif (unpublish_website($business)) {
            flash('success', 'Website unpublished. Your data and pages are kept.');
            redirect('app/website.php');
        }
    }
    if ($action === 'reset' && $website) {
        if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
            $errors['form'] = 'Only an owner or manager can rebuild the website.';
        } else {
            try {
                create_website_for_business($business, $website['template_id'], true);
                flash('success', 'Website rebuilt from the selected template. Publish again to go live.');
                redirect('app/website.php');
            } catch (Throwable $e) {
                $errors['form'] = 'Could not rebuild the website.';
            }
        }
    }
}

$website = business_website((int) $business['id']);
$pages = $website ? website_pages((int) $business['id']) : [];
$stats = website_stats((int) $business['id']);
$visitsByDay = [];
if ($website) {
    $rows = db_all(
        'SELECT DATE(visited_at) AS day, COUNT(*) AS visits FROM website_visits
         WHERE business_id = ? AND visited_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(visited_at)',
        [$business['id']]
    );
    foreach ($rows as $row) {
        $visitsByDay[$row['day']] = (int) $row['visits'];
    }
}
$days = [];
$maxVisits = 1;
for ($i = 13; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $count = $visitsByDay[$key] ?? 0;
    $maxVisits = max($maxVisits, $count);
    $days[] = ['day' => $key, 'visits' => $count];
}

layout_app_start($business, 'website', 'Website', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Your website</h1>
    <p><?= $website ? 'Manage pages, design and publishing for ' . e($business['name']) . '.' : 'Create a professional website from your business details.' ?></p>
  </div>
  <div class="page-actions">
    <?php if ($website): ?>
      <a class="btn btn-ghost" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View website</a>
      <a class="btn btn-primary" href="<?= e(app_url('app/editor.php')) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit website</a>
    <?php endif; ?>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

<?php if (!$website): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon"><?= icon('globe') ?></div>
      <h3>No website yet</h3>
      <p>We will build starter pages from your business details and your category template. Every section can be edited afterwards.</p>
      <form method="post" action="<?= e(app_url('app/website.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <button class="btn btn-primary btn-lg" type="submit"><?= icon('sparkles', 'icon icon-sm') ?> Create my website</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-icon"><?= icon('eye') ?></div><div class="stat-label">Visits (30 days)</div><div class="stat-value"><?= e(number_format($stats['visits_30d'])) ?></div><div class="stat-note"><?= e(number_format($stats['visits_today'])) ?> today</div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('layout') ?></div><div class="stat-label">Pages</div><div class="stat-value"><?= e(number_format($stats['pages'])) ?></div><div class="stat-note">Published and draft</div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('grid') ?></div><div class="stat-label">Sections</div><div class="stat-value"><?= e(number_format($stats['sections'])) ?></div><div class="stat-note">Across all pages</div></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('credit-card') ?></div><div class="stat-label">Plan</div><div class="stat-value" style="font-size:1.1rem"><?= e(business_plan($business)['name'] ?? 'Free') ?></div><div class="stat-note"><?= business_can('custom_domain', $business) ? 'Custom domain enabled' : 'Custom domain on paid plans' ?></div></div>
  </div>

  <div class="grid grid-2-1">
    <div>
      <div class="card mb-2">
        <div class="card-head">
          <div>
            <h2 class="mt-0">Website status</h2>
            <p class="small muted" style="margin:4px 0 0">Template: <?= e($website['template_name'] ?? 'Aurora') ?> · <?= e(ucfirst($website['status'])) ?></p>
          </div>
          <?= status_badge((string) $website['status']) ?>
        </div>
        <div class="card-body">
          <label class="small muted">Website address</label>
          <div class="flex mb-2">
            <input type="text" readonly value="<?= e(business_public_url($business)) ?>" style="flex:1">
            <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e(business_public_url($business)) ?>">Copy</button>
          </div>
          <?php if ($website['status'] === 'published'): ?>
            <div class="alert alert-success"><?= icon('check') ?><div>Your website is live. Published <?= e(nice_datetime($website['published_at'])) ?>.</div></div>
            <form method="post" action="<?= e(app_url('app/website.php')) ?>" data-confirm="Unpublish the website? Visitors will see an offline page until you publish again.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unpublish">
              <button class="btn btn-ghost" type="submit">Unpublish</button>
            </form>
          <?php else: ?>
            <div class="alert alert-warning"><?= icon('alert') ?><div>Your website is not public yet. Publish when the content and design are ready.</div></div>
            <form method="post" action="<?= e(app_url('app/website.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="publish">
              <button class="btn btn-primary btn-lg" type="submit"><?= icon('globe', 'icon icon-sm') ?> Publish website</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2 class="mt-0">Pages</h2><a class="small link" href="<?= e(app_url('app/pages.php')) ?>">Manage pages</a></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Page</th><th>Address</th><th>Status</th><th class="text-right">Sections</th></tr></thead>
            <tbody>
              <?php foreach ($pages as $page): ?>
                <?php $sectionCount = count(website_sections((int) $page['id'])); ?>
                <tr>
                  <td><a class="cell-main" href="<?= e(app_url('app/editor.php?page=' . $page['id'])) ?>"><?= e($page['title']) ?></a><?php if ((int) $page['is_home'] === 1): ?><span class="cell-sub">Home page</span><?php endif; ?></td>
                  <td class="mono small">/<?= e($page['slug'] === 'home' ? '' : $page['slug']) ?></td>
                  <td><?= status_badge((string) $page['status']) ?></td>
                  <td class="text-right"><?= $sectionCount ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Visits — 14 days</h2></div>
        <div class="card-body">
          <?php if (array_sum(array_column($days, 'visits')) === 0): ?>
            <p class="small muted">No visits recorded yet. Visits are counted when someone opens your published website.</p>
          <?php else: ?>
            <div class="flex" style="align-items:flex-end;gap:4px;height:120px">
              <?php foreach ($days as $day): ?>
                <div style="flex:1;text-align:center">
                  <div title="<?= e($day['day']) ?>: <?= $day['visits'] ?> visits" style="height:<?= max(3, (int) round(($day['visits'] / $maxVisits) * 90)) ?>px;background:var(--brand-soft);border-radius:4px 4px 2px 2px"></div>
                  <div class="muted" style="font-size:.6rem;margin-top:4px"><?= e(date('d', strtotime($day['day']))) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Design &amp; domains</h2></div>
        <div class="card-body">
          <div class="side-nav">
            <a class="side-link" href="<?= e(app_url('app/appearance.php')) ?>"><?= icon('image') ?><span>Colours, fonts &amp; template</span></a>
            <a class="side-link" href="<?= e(app_url('app/domains.php')) ?>"><?= icon('globe') ?><span>Custom domain</span></a>
            <a class="side-link" href="<?= e(app_url('app/pages.php')) ?>"><?= icon('layout') ?><span>Pages &amp; navigation</span></a>
            <a class="side-link" href="<?= e(app_url('app/settings.php')) ?>"><?= icon('settings') ?><span>Business details</span></a>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2 class="mt-0">Rebuild</h2></div>
        <div class="card-body">
          <p class="small muted">Reset the starter pages from your current template. Custom section content on those pages will be replaced.</p>
          <form method="post" action="<?= e(app_url('app/website.php')) ?>" data-confirm="Rebuild all pages from the template? Current page content will be replaced.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 'icon icon-sm') ?> Rebuild starter pages</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php
layout_app_end(['scripts' => ['app.js']]);
