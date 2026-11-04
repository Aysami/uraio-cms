<?php
/**
 * Business onboarding wizard: category → details → template → publish.
 * A user can own multiple businesses, subject to their plan limits.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

$step = (string) ($_GET['step'] ?? 'category');
$businessId = (int) ($_GET['business'] ?? $_POST['business_id'] ?? 0);
$business = null;
if ($businessId > 0) {
    if (!user_can_access_business($businessId, (int) $user['id'])) {
        http_response_code(403);
        include BASE_PATH . '/includes/error-page.php';
        render_error_page(403, 'Business not available', 'You do not have access to this business.');
        exit;
    }
    $business = db_row(
        'SELECT b.*, c.name AS category_name FROM businesses b LEFT JOIN categories c ON c.id = b.category_id WHERE b.id = ?',
        [$businessId]
    );
}

$errors = [];
$selectedCategory = null;
$categorySlug = trim((string) ($_POST['category'] ?? $_GET['category'] ?? ''));
if ($categorySlug !== '') {
    $selectedCategory = category_by_slug($categorySlug);
}

$stepLabels = ['category' => 'Category', 'details' => 'Business details', 'template' => 'Template', 'done' => 'Publish'];
$stepIndex = array_search($step, array_keys($stepLabels), true);
if ($stepIndex === false) {
    $step = 'category';
    $stepIndex = 0;
}
if ($step === 'details' && !$selectedCategory && !empty($_SESSION['_setup_category_id'])) {
    $selectedCategory = category_by_id((int) $_SESSION['_setup_category_id']);
}

/* ---------- Step 1 → 2: category chosen ---------- */
if (is_post() && post('action') === 'choose_category') {
    verify_csrf();
    $slug = trim((string) post('category', ''));
    $category = category_by_slug($slug);
    if (!$category || $category['status'] !== 'active') {
        $errors['category'] = 'Choose a valid business category.';
    } else {
        $_SESSION['_setup_category_id'] = (int) $category['id'];
        redirect('app/setup.php?step=details');
    }
}

/* ---------- Step 2: create business ---------- */
if (is_post() && post('action') === 'create_business') {
    verify_csrf();
    $categoryId = (int) ($_SESSION['_setup_category_id'] ?? post('category_id', 0));
    $category = category_by_id($categoryId);
    if (!$category) {
        flash('warning', 'Choose a category first.');
        redirect('app/setup.php?step=category');
    }
    if (user_owned_business_count((int) $user['id']) >= user_business_limit((int) $user['id'])) {
        $errors['form'] = 'Your current plan allows ' . user_business_limit((int) $user['id']) . ' business(es). Upgrade to add another.';
    }

    $details = [
        'name' => trim((string) post('name', '')),
        'description' => trim((string) post('description', '')),
        'email' => strtolower(trim((string) post('email', ''))),
        'phone' => trim((string) post('phone', '')),
        'whatsapp' => trim((string) post('whatsapp', '')),
        'country' => trim((string) post('country', 'Nigeria')),
        'state' => trim((string) post('state', '')),
        'city' => trim((string) post('city', '')),
        'address' => trim((string) post('address', '')),
        'currency' => trim((string) post('currency', 'NGN')),
    ];
    $errors += validate($details, [
        'name' => 'required|min:2|max:190',
        'description' => 'max:2000',
        'email' => 'email|max:190',
        'phone' => 'phone',
        'whatsapp' => 'phone',
        'country' => 'required|max:80',
        'city' => 'max:120',
        'address' => 'max:500',
    ]);
    if (!array_key_exists($details['currency'], currency_options())) {
        $errors['currency'] = 'Choose a supported currency.';
    }

    if (!$errors) {
        try {
            $logo = upload_image('logo', 'businesses');
            $pdo = db();
            $ownTransaction = !$pdo->inTransaction();
            if ($ownTransaction) {
                $pdo->beginTransaction();
            }
            $slug = unique_slug('businesses', 'slug', $details['name']);
            $newId = db_insert('businesses', [
                'uuid' => uuid(),
                'owner_id' => $user['id'],
                'name' => $details['name'],
                'slug' => $slug,
                'category_id' => $category['id'],
                'description' => $details['description'] ?: null,
                'email' => $details['email'] ?: null,
                'phone' => $details['phone'] ?: null,
                'whatsapp' => $details['whatsapp'] ?: $details['phone'] ?: null,
                'country' => $details['country'],
                'state' => $details['state'] ?: null,
                'city' => $details['city'] ?: null,
                'address' => $details['address'] ?: null,
                'logo' => $logo,
                'currency' => $details['currency'],
                'status' => 'active',
                'onboarding_step' => 'template',
            ]);
            db_insert('business_members', [
                'business_id' => $newId,
                'user_id' => $user['id'],
                'role' => 'owner',
                'status' => 'active',
            ]);
            db_insert('business_categories', [
                'business_id' => $newId,
                'category_id' => $category['id'],
                'is_primary' => 1,
            ]);
            activate_category_modules($newId, (int) $category['id']);
            create_free_subscription($newId, (int) $user['id']);
            $created = db_row(
                'SELECT b.*, c.name AS category_name FROM businesses b LEFT JOIN categories c ON c.id = b.category_id WHERE b.id = ?',
                [$newId]
            );
            create_website_for_business($created);
            if ($ownTransaction) {
                $pdo->commit();
            }

            $_SESSION['active_business_id'] = $newId;
            unset($_SESSION['_setup_category_id']);
            activity_log('business.created', 'business', $newId, ['category' => $category['slug']], $newId);
            flash('success', 'Business created. Choose a starting template for your website.');
            redirect('app/setup.php?step=template&business=' . $newId);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['form'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not create the business. Please try again.';
            if (!($e instanceof InvalidArgumentException)) {
                error_log('business create failed: ' . $e->getMessage());
            }
        }
    }
    $selectedCategory = $category;
    $step = 'details';
    $stepIndex = 1;
}

/* ---------- Step 3: choose template ---------- */
if (is_post() && post('action') === 'choose_template' && $business) {
    verify_csrf();
    $templateId = (int) post('template_id', 0);
    $template = db_row("SELECT * FROM website_templates WHERE id = ? AND status = 'active'", [$templateId]);
    if (!$template) {
        $errors['template'] = 'Choose a valid template.';
    } elseif (!template_allowed_for_business($template, $business)) {
        $errors['template'] = 'That template is available on a higher plan.';
    } else {
        try {
            create_website_for_business($business, $templateId, true);
            db_update('businesses', ['onboarding_step' => 'done'], 'id = :id', ['id' => $business['id']]);
            activity_log('website.template_selected', 'business_website', null, ['template' => $template['slug']], (int) $business['id']);
            flash('success', 'Template applied. Review your website and publish when ready.');
            redirect('app/setup.php?step=done&business=' . $business['id']);
        } catch (Throwable $e) {
            $errors['template'] = 'Could not apply that template. Please try again.';
            error_log('template apply failed: ' . $e->getMessage());
        }
    }
}

/* ---------- Step 4: publish ---------- */
if (is_post() && post('action') === 'publish' && $business) {
    verify_csrf();
    if (!can('publish_website') && !is_admin_user($user)) {
        $errors['form'] = 'Your role cannot publish websites.';
    } elseif (publish_website($business)) {
        notify_business_owners((int) $business['id'], 'Website published', $business['name'] . ' is now live.', 'app/website.php', 'website');
        send_email_template('website_published', (string) $user['email'], [
            'name' => $user['name'],
            'business' => $business['name'],
            'website_url' => business_public_url($business),
        ]);
        flash('success', 'Your website is live. Share it with your customers.');
        redirect('app/dashboard.php?business=' . $business['id']);
    } else {
        $errors['form'] = 'The website could not be published. Open the website builder and try again.';
    }
}

if ($step === 'template' && !$business) {
    redirect('app/setup.php');
}
if ($step === 'done' && !$business) {
    redirect('app/setup.php');
}

$categories = db_all(
    "SELECT c.*, p.name AS parent_name, p.icon AS parent_icon
     FROM categories c JOIN categories p ON p.id = c.parent_id
     WHERE c.status = 'active' AND p.status = 'active'
     ORDER BY p.sort_order ASC, c.sort_order ASC, c.name ASC"
);
$groups = db_all("SELECT * FROM categories WHERE status = 'active' AND parent_id IS NULL ORDER BY sort_order ASC, name ASC");
$templates = db_all("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC");
$businessPlan = $business ? business_plan($business) : subscription_plan('free');

layout_head('Set up your business', ['noindex' => true]);
?>
<div class="content" style="max-width:1180px">
  <div class="page-head">
    <div>
      <div class="breadcrumb"><a href="<?= e(app_url('app/dashboard.php')) ?>">Dashboard</a> <span>/</span> <span>New business</span></div>
      <h1>Set up your business</h1>
      <p>Four quick steps. You can change everything later.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="<?= e(app_url('app/dashboard.php')) ?>">Save and exit</a>
    </div>
  </div>

  <?php layout_flashes(); ?>
  <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

  <div class="wizard-steps">
    <?php $i = 0; foreach ($stepLabels as $key => $label): ?>
      <?php $state = $i < $stepIndex ? 'is-done' : ($i === $stepIndex ? 'is-active' : ''); ?>
      <span class="wizard-step <?= $state ?>"><span class="step-num"><?= $i < $stepIndex ? '✓' : $i + 1 ?></span><?= e($label) ?></span>
      <?php if ($i < count($stepLabels) - 1): ?><span class="wizard-divider"></span><?php endif; ?>
      <?php $i++; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($step === 'category'): ?>
    <div class="card">
      <div class="card-head">
        <div><h2 class="mt-0">What kind of business is this?</h2><p class="small muted" style="margin:4px 0 0">Your category decides which modules and starter template appear.</p></div>
      </div>
      <div class="card-body">
        <?php if (!empty($errors['category'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['category']) ?></div></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('app/setup.php')) ?>" id="category-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="choose_category">
          <div class="flex mb-2" style="flex-wrap:wrap">
            <div class="search-input grow"><?= icon('search') ?><input type="search" id="category-search" placeholder="Search categories…" aria-label="Search categories"></div>
            <select id="category-group-filter" aria-label="Filter by group" style="max-width:260px">
              <option value="">All groups</option>
              <?php foreach ($groups as $group): ?>
                <option value="<?= (int) $group['id'] ?>"><?= e($group['icon'] . ' ' . $group['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="category-grid" id="category-grid">
            <?php foreach ($categories as $category): ?>
              <label class="category-option<?= $selectedCategory && (int) $selectedCategory['id'] === (int) $category['id'] ? ' is-selected' : '' ?>"
                     data-name="<?= e(mb_strtolower($category['name'] . ' ' . $category['parent_name'])) ?>"
                     data-group="<?= (int) $category['parent_id'] ?>">
                <input type="radio" name="category" value="<?= e($category['slug']) ?>" <?= $selectedCategory && (int) $selectedCategory['id'] === (int) $category['id'] ? 'checked' : '' ?> required>
                <span class="cat-check">✓</span>
                <span class="cat-icon"><?= e($category['icon'] ?: '🏷️') ?></span>
                <strong><?= e($category['name']) ?></strong>
                <small><?= e($category['parent_name']) ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="small muted mt-2" id="category-count"><?= count($categories) ?> categories available</p>
          <div class="form-actions">
            <button class="btn btn-primary btn-lg" type="submit">Continue to business details <?= icon('arrow-right', 'icon icon-sm') ?></button>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($step === 'details'): ?>
    <div class="card">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Business details</h2>
          <p class="small muted" style="margin:4px 0 0">Selected category: <strong><?= e($selectedCategory['icon'] ?? '') ?> <?= e($selectedCategory['name'] ?? '') ?></strong> · <a class="link" href="<?= e(app_url('app/setup.php?step=category')) ?>">change</a></p>
        </div>
      </div>
      <div class="card-body">
        <form method="post" action="<?= e(app_url('app/setup.php?step=details')) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_business">
          <input type="hidden" name="category_id" value="<?= (int) ($selectedCategory['id'] ?? 0) ?>">
          <div class="form-grid">
            <div class="field field-col-8">
              <label for="name">Business name</label>
              <input id="name" name="name" value="<?= e(post('name', '')) ?>" required autofocus>
              <?= render_field_error($errors, 'name') ?>
              <small class="field-help">This also becomes your website address, e.g. <span id="slug-preview"><?= e(slugify((string) post('name', 'your-business'))) ?></span>.</small>
            </div>
            <div class="field field-col-4">
              <label for="currency">Currency</label>
              <select id="currency" name="currency">
                <?php foreach (currency_options() as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= selected(post('currency', 'NGN'), $code) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= render_field_error($errors, 'currency') ?>
            </div>
            <div class="field field-col-12">
              <label for="description">Short description</label>
              <textarea id="description" name="description" rows="3" maxlength="2000" placeholder="What does your business do?"><?= e(post('description', '')) ?></textarea>
              <?= render_field_error($errors, 'description') ?>
              <small class="field-help">Used on your website and in search results.</small>
            </div>
            <div class="field field-col-6">
              <label for="email">Business email</label>
              <input id="email" name="email" type="email" value="<?= e(post('email', (string) $user['email'])) ?>">
              <?= render_field_error($errors, 'email') ?>
            </div>
            <div class="field field-col-6">
              <label for="phone">Phone</label>
              <input id="phone" name="phone" type="tel" value="<?= e(post('phone', '')) ?>">
              <?= render_field_error($errors, 'phone') ?>
            </div>
            <div class="field field-col-6">
              <label for="whatsapp">WhatsApp number</label>
              <input id="whatsapp" name="whatsapp" type="tel" value="<?= e(post('whatsapp', '')) ?>" placeholder="Optional">
              <?= render_field_error($errors, 'whatsapp') ?>
            </div>
            <div class="field field-col-6">
              <label for="logo">Logo</label>
              <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
              <small class="field-help">Square works best. Max 5 MB.</small>
            </div>
            <div class="field field-col-6">
              <label for="country">Country</label>
              <select id="country" name="country">
                <?php foreach (country_options() as $country): ?>
                  <option value="<?= e($country) ?>" <?= selected(post('country', 'Nigeria'), $country) ?>><?= e($country) ?></option>
                <?php endforeach; ?>
              </select>
              <?= render_field_error($errors, 'country') ?>
            </div>
            <div class="field field-col-6">
              <label for="state">State / region</label>
              <input id="state" name="state" value="<?= e(post('state', '')) ?>">
              <?= render_field_error($errors, 'state') ?>
            </div>
            <div class="field field-col-6">
              <label for="city">City</label>
              <input id="city" name="city" value="<?= e(post('city', '')) ?>">
              <?= render_field_error($errors, 'city') ?>
            </div>
            <div class="field field-col-6">
              <label for="address">Street address</label>
              <input id="address" name="address" value="<?= e(post('address', '')) ?>">
              <?= render_field_error($errors, 'address') ?>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary btn-lg" type="submit">Create business <?= icon('arrow-right', 'icon icon-sm') ?></button>
            <a class="btn btn-ghost" href="<?= e(app_url('app/setup.php?step=category')) ?>">Back</a>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($step === 'template'): ?>
    <div class="card">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Choose a starting template</h2>
          <p class="small muted" style="margin:4px 0 0">Pick a layout for <strong><?= e($business['name']) ?></strong>. You can edit every section afterwards.</p>
        </div>
        <span class="chip"><?= e($businessPlan['name'] ?? 'Free') ?> plan</span>
      </div>
      <div class="card-body">
        <?php if (!empty($errors['template'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['template']) ?></div></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('app/setup.php?step=template&business=' . $business['id'])) ?>" id="template-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="choose_template">
          <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
          <div class="template-grid">
            <?php foreach ($templates as $template): ?>
              <?php $allowed = template_allowed_for_business($template, $business); ?>
              <label class="template-card<?= !$allowed ? ' is-locked' : '' ?>" style="<?= !$allowed ? 'opacity:.62' : '' ?>">
                <input type="radio" name="template_id" value="<?= (int) $template['id'] ?>" <?= !$allowed ? 'disabled' : '' ?> required>
                <?php if (!empty($template['is_premium'])): ?><span class="template-badge badge badge-warning"><?= $allowed ? 'Premium' : 'Upgrade' ?></span><?php endif; ?>
                <div class="template-preview <?= e($template['slug']) ?>">
                  <div class="tp-bar"></div><div class="tp-line"></div><div class="tp-line short"></div>
                  <div class="tp-block"><span></span><span></span><span></span></div>
                </div>
                <div class="template-meta">
                  <strong><?= e($template['name']) ?></strong>
                  <small><?= e($template['description']) ?></small>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ((int) ($businessPlan['price_monthly'] ?? 0) === 0): ?>
            <div class="help-box mt-3"><strong>On the Free plan</strong> you can use the standard templates. Upgrade later to unlock every template and custom domains.</div>
          <?php endif; ?>
          <div class="form-actions">
            <button class="btn btn-primary btn-lg" type="submit">Apply template <?= icon('arrow-right', 'icon icon-sm') ?></button>
            <a class="btn btn-ghost" href="<?= e(app_url('app/setup.php?step=done&business=' . $business['id'])) ?>">Skip for now</a>
          </div>
        </form>
      </div>
    </div>

  <?php else: ?>
    <?php $website = business_website((int) $business['id']); $home = website_home_page((int) $business['id']); ?>
    <div class="grid grid-2-1">
      <div class="card">
        <div class="card-head"><h2 class="mt-0"><?= icon('check', 'icon icon-sm') ?> You’re ready to publish</h2></div>
        <div class="card-body">
          <p class="muted">Your business <strong><?= e($business['name']) ?></strong> is set up with a starter website. You can publish now and keep editing later — changes go live when you save and publish.</p>
          <ul class="check-list mt-2">
            <li>Category: <?= e($business['category_name'] ?? 'Business') ?></li>
            <li>Dashboard modules activated</li>
            <li>Starter website created (<?= e($website['template_name'] ?? 'Aurora') ?>)</li>
            <li>Free subscription active</li>
          </ul>
          <div class="form-actions">
            <form method="post" action="<?= e(app_url('app/setup.php?step=done&business=' . $business['id'])) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="publish">
              <input type="hidden" name="business_id" value="<?= (int) $business['id'] ?>">
              <button class="btn btn-primary btn-lg" type="submit"><?= icon('globe', 'icon icon-sm') ?> Publish website</button>
            </form>
            <a class="btn btn-ghost" href="<?= e(app_url('app/website.php?business=' . $business['id'])) ?>">Open builder</a>
            <a class="btn btn-ghost" href="<?= e(app_url('app/dashboard.php?business=' . $business['id'])) ?>">Go to dashboard</a>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h2 class="mt-0">Website preview</h2><span class="badge badge-<?= ($website['status'] ?? '') === 'published' ? 'success' : 'neutral' ?>"><?= e(ucfirst($website['status'] ?? 'draft')) ?></span></div>
        <div class="card-body">
          <div class="template-preview <?= e($website['template_slug'] ?? 'aurora') ?>" style="height:190px;border-radius:12px">
            <div class="tp-bar"></div><div class="tp-line"></div><div class="tp-line short"></div>
            <div class="tp-block"><span></span><span></span><span></span></div>
          </div>
          <p class="small muted mt-2">Home page: <strong><?= e($home['title'] ?? 'Home') ?></strong><br>Address: <span class="mono"><?= e(business_public_url($business)) ?></span></p>
          <?php if ($website && $website['status'] === 'published'): ?>
            <a class="btn btn-soft btn-block" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View live website</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
layout_end(['scripts' => ['app.js', 'setup.js']]);
