<?php
/**
 * Website appearance: template, colours, typography, branding and SEO.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$website = business_website($businessId);
if (!$website) {
    flash('info', 'Create your website before choosing a design.');
    redirect('app/website.php');
}

$errors = [];
$fontOptions = [
    'Sora' => 'Sora (modern geometric)',
    'Inter' => 'Inter (clean sans-serif)',
    'Manrope' => 'Manrope (friendly sans-serif)',
    'Space Grotesk' => 'Space Grotesk (technical)',
    'DM Serif Display' => 'DM Serif Display (editorial serif)',
    'Playfair Display' => 'Playfair Display (classic serif)',
    'Lora' => 'Lora (readable serif)',
];
$templates = db_all("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC");

if (is_post()) {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can change the website design.');
        redirect('app/appearance.php');
    }
    try {
        $templateId = (int) post('template_id', 0);
        $template = $templateId > 0 ? db_row("SELECT * FROM website_templates WHERE id = ? AND status = 'active'", [$templateId]) : null;
        if (!$template) {
            throw new InvalidArgumentException('Choose a valid template.');
        }
        if (!template_allowed_for_business($template, $business)) {
            throw new InvalidArgumentException('This template is available on a higher plan. Upgrade in Billing to use it.');
        }

        $primary = (string) post('primary_color', '#0f766e');
        $secondary = (string) post('secondary_color', '#0b1220');
        $accent = (string) post('accent_color', '#f59e0b');
        foreach (['primary' => $primary, 'secondary' => $secondary, 'accent' => $accent] as $label => $colour) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
                throw new InvalidArgumentException('Choose valid colours using the colour pickers.');
            }
        }
        $fontHeading = (string) post('font_heading', 'Sora');
        $fontBody = (string) post('font_body', 'Inter');
        if (!isset($fontOptions[$fontHeading]) || !isset($fontOptions[$fontBody])) {
            throw new InvalidArgumentException('Choose a valid font.');
        }
        $logoPosition = in_array(post('logo_position'), ['left', 'center', 'right'], true) ? (string) post('logo_position') : 'left';

        $logo = (string) ($business['logo'] ?? '');
        $cover = (string) ($business['cover'] ?? '');
        $ogImage = (string) ($website['og_image'] ?? '');
        if (!empty($_FILES['logo']['name'])) {
            $logo = upload_image('logo', 'branding') ?: $logo;
        }
        if (!empty($_FILES['cover']['name'])) {
            $cover = upload_image('cover', 'branding') ?: $cover;
        }
        if (!empty($_FILES['og_image']['name'])) {
            $ogImage = upload_image('og_image', 'branding') ?: $ogImage;
        }
        if (post('remove_logo') === '1') {
            $logo = '';
        }

        db_update('business_websites', [
            'template_id' => (int) $template['id'],
            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'accent_color' => $accent,
            'font_heading' => $fontHeading,
            'font_body' => $fontBody,
            'logo_position' => $logoPosition,
            'seo_title' => str_limit(trim((string) post('seo_title', '')), 190) ?: null,
            'seo_description' => str_limit(trim((string) post('seo_description', '')), 320) ?: null,
            'og_image' => $ogImage ?: null,
        ], 'id = :id', ['id' => $website['id']]);
        db_update('businesses', ['logo' => $logo ?: null, 'cover' => $cover ?: null], 'id = :id', ['id' => $businessId]);

        activity_log('website.appearance_updated', 'business_website', $website['id'], ['template' => $template['slug']], $businessId);
        flash('success', 'Design settings saved. Preview the result, then publish to update the live site.');
        redirect('app/appearance.php');
    } catch (InvalidArgumentException $e) {
        $errors['form'] = $e->getMessage();
    } catch (Throwable $e) {
        $errors['form'] = 'Could not save the design. Please try again.';
        error_log('appearance save failed: ' . $e->getMessage());
    }
    $website = business_website($businessId);
}

$templates = db_all("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC");
$business = require_business();

layout_app_start($business, 'appearance', 'Appearance', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Appearance</h1>
    <p>Choose the visual direction for <?= e($business['name']) ?>.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('site.php?preview=1&page=home')) ?>" target="_blank" rel="noopener"><?= icon('eye', 'icon icon-sm') ?> Preview</a>
    <a class="btn btn-primary" href="<?= e(app_url('app/editor.php')) ?>"><?= icon('edit', 'icon icon-sm') ?> Edit content</a>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['form']) ?></div></div><?php endif; ?>

<form method="post" action="<?= e(app_url('app/appearance.php')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid grid-2-1">
    <div>
      <div class="card mb-2">
        <div class="card-head"><div><h2 class="mt-0">Template</h2><p class="small muted" style="margin:4px 0 0">Switching template keeps your content and pages.</p></div></div>
        <div class="card-body">
          <div class="template-grid">
            <?php foreach ($templates as $template): ?>
              <?php $allowed = template_allowed_for_business($template, $business); ?>
              <label class="template-option<?= (int) $website['template_id'] === (int) $template['id'] ? ' is-selected' : '' ?><?= !$allowed ? ' is-locked' : '' ?>">
                <input type="radio" name="template_id" value="<?= (int) $template['id'] ?>" <?= checked((int) $website['template_id'] === (int) $template['id']) ?> <?= !$allowed ? 'disabled' : '' ?>>
                <span class="template-swatch template-swatch-<?= e($template['slug']) ?>"><span></span><span></span><span></span></span>
                <span class="template-name"><?= e($template['name']) ?><?php if (!empty($template['is_premium'])): ?> <span class="badge badge-warning">Premium</span><?php endif; ?></span>
                <span class="template-desc"><?= e($template['description'] ?? '') ?></span>
                <?php if (!$allowed): ?><span class="field-help">Requires a plan with all templates. <a class="link" href="<?= e(app_url('app/billing.php')) ?>">Upgrade</a></span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Brand &amp; images</h2></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="field field-col-6">
              <label for="logo">Logo</label>
              <?php if (!empty($business['logo'])): ?><img class="image-preview mb-1" src="<?= e(media_url($business['logo'])) ?>" alt="Current logo"><?php endif; ?>
              <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
              <span class="field-help">PNG, JPG, WebP or SVG, up to 5 MB. A square or wide logo works best.</span>
              <?php if (!empty($business['logo'])): ?><label class="switch-label mt-1"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label><?php endif; ?>
            </div>
            <div class="field field-col-6">
              <label for="cover">Cover image</label>
              <?php if (!empty($business['cover'])): ?><img class="image-preview mb-1" src="<?= e(media_url($business['cover'])) ?>" alt="Current cover"><?php endif; ?>
              <input id="cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
              <span class="field-help">Used in the hero and page headers. Recommended 1600 × 900.</span>
            </div>
            <div class="field field-col-6">
              <label for="og_image">Social sharing image</label>
              <?php if (!empty($website['og_image'])): ?><img class="image-preview mb-1" src="<?= e(media_url($website['og_image'])) ?>" alt="Current social image"><?php endif; ?>
              <input id="og_image" type="file" name="og_image" accept="image/png,image/jpeg,image/webp">
              <span class="field-help">Shown when your website is shared. 1200 × 630 is ideal.</span>
            </div>
            <div class="field field-col-6">
              <label for="logo_position">Logo position</label>
              <select id="logo_position" name="logo_position">
                <?php foreach (['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'] as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= selected($website['logo_position'], $value) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h2 class="mt-0">Search &amp; sharing</h2></div>
        <div class="card-body">
          <div class="field">
            <label for="seo_title">SEO title</label>
            <input id="seo_title" type="text" name="seo_title" maxlength="190" value="<?= e($website['seo_title'] ?? '') ?>" placeholder="<?= e($business['name']) ?> · <?= e($business['category_name'] ?? 'Business') ?>">
            <span class="field-help">Shown in browser tabs and search results. Around 60 characters is ideal.</span>
          </div>
          <div class="field">
            <label for="seo_description">SEO description</label>
            <textarea id="seo_description" name="seo_description" rows="3" maxlength="320" placeholder="Describe your business in one or two sentences."><?= e($website['seo_description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Colours</h2></div>
        <div class="card-body">
          <?php
          $colourFields = [
              'primary_color' => ['Primary', '#0f766e', 'Buttons, links and highlights'],
              'secondary_color' => ['Dark', '#0b1220', 'Headings, footer and dark sections'],
              'accent_color' => ['Accent', '#f59e0b', 'Small highlights and callouts'],
          ];
          foreach ($colourFields as $key => [$label, $fallback, $help]):
          ?>
            <div class="field">
              <label for="<?= e($key) ?>"><?= e($label) ?></label>
              <div class="flex">
                <input id="<?= e($key) ?>" type="color" name="<?= e($key) ?>" value="<?= e($website[$key] ?: $fallback) ?>" style="width:58px;height:44px;padding:4px">
                <input type="text" value="<?= e($website[$key] ?: $fallback) ?>" readonly aria-label="<?= e($label) ?> hex value" style="flex:1">
              </div>
              <span class="field-help"><?= e($help) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Typography</h2></div>
        <div class="card-body">
          <div class="field">
            <label for="font_heading">Headings</label>
            <select id="font_heading" name="font_heading">
              <?php foreach ($fontOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($website['font_heading'], $value) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="font_body">Body text</label>
            <select id="font_body" name="font_body">
              <?php foreach ($fontOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($website['font_body'], $value) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button class="btn btn-primary btn-block" type="submit"><?= icon('save', 'icon icon-sm') ?> Save design</button>
          <p class="small muted mt-2 mb-0">Changes apply to the preview immediately. Publish the website to make them live.</p>
        </div>
      </div>
    </div>
  </div>
</form>
<?php
layout_app_end(['scripts' => ['app.js']]);
