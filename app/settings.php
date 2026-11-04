<?php
/**
 * Business settings: profile, public address, categories, contact details,
 * opening hours and social links. Changing a category never deletes records.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$errors = [];
$canManage = in_array($business['member_role'] ?? '', ['owner', 'manager'], true) || is_admin_user($user);

$timezones = [
    'Africa/Lagos' => 'West Africa Time — Lagos',
    'Africa/Accra' => 'Greenwich Mean Time — Accra',
    'Africa/Nairobi' => 'East Africa Time — Nairobi',
    'Africa/Johannesburg' => 'South Africa Standard Time — Johannesburg',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Central European Time — Paris',
    'America/New_York' => 'New York',
    'America/Chicago' => 'Chicago',
    'America/Los_Angeles' => 'Los Angeles',
    'Asia/Dubai' => 'Dubai',
    'UTC' => 'UTC',
];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$socialKeys = [
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'x' => 'X / Twitter',
    'linkedin' => 'LinkedIn',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'website' => 'Other website',
];
$openingHours = json_decode_assoc($business['opening_hours'] ?? null);
$socials = json_decode_assoc($business['socials'] ?? null);

/* How the slug field is presented: prefix, or prefix + suffix for subdomain mode. */
$slugPrefix = app_url(public_url_mode() === 'path_s' ? 's' : '') . '/';
$slugSuffix = public_url_mode() === 'subdomain' ? '.' . site_domain() : '';

function settings_slug_unique(string $slug, int $ignoreId): string
{
    return unique_slug('businesses', 'slug', $slug, $ignoreId);
}

if (is_post()) {
    verify_csrf();
    if (!$canManage) {
        flash('danger', 'Only an owner or manager can change business settings.');
        redirect('app/settings.php');
    }
    $action = (string) post('action', 'profile');

    if ($action === 'profile') {
        $name = trim((string) post('name', ''));
        $slugInput = trim((string) post('slug', ''));
        $email = trim((string) post('email', ''));
        $phone = trim((string) post('phone', ''));
        $whatsapp = trim((string) post('whatsapp', ''));
        $description = trim((string) post('description', ''));
        $address = trim((string) post('address', ''));
        $city = trim((string) post('city', ''));
        $state = trim((string) post('state', ''));
        $country = trim((string) post('country', ''));
        $currency = (string) post('currency', 'NGN');
        $timezone = (string) post('timezone', 'Africa/Lagos');
        $status = (string) post('status', 'active');

        if ($name === '') {
            $errors['name'] = 'Business name is required.';
        } elseif (mb_strlen($name) > 190) {
            $errors['name'] = 'Business name must be 190 characters or fewer.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        if ($whatsapp !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $whatsapp)) {
            $errors['whatsapp'] = 'Enter a valid WhatsApp number.';
        }
        if ($slugInput !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugInput)) {
            $errors['slug'] = 'Use lowercase letters, numbers and hyphens only.';
        }
        if ($slugInput !== '' && business_slug_is_reserved(slugify($slugInput))) {
            $errors['slug'] = 'That public address is reserved. Choose another one.';
        }
        if (!array_key_exists($currency, currency_options())) {
            $errors['currency'] = 'Choose a supported currency.';
        }
        if (!array_key_exists($timezone, $timezones)) {
            $errors['timezone'] = 'Choose a supported timezone.';
        }
        if (!in_array($status, ['active', 'closed'], true)) {
            $errors['status'] = 'Choose a valid business status.';
        }
        if (mb_strlen($description) > 3000) {
            $errors['description'] = 'Description must be 3,000 characters or fewer.';
        }

        $primaryCategoryId = (int) post('category_id', 0);
        $primaryCategory = $primaryCategoryId > 0 ? category_by_id($primaryCategoryId) : null;
        if (!$primaryCategory || $primaryCategory['status'] !== 'active') {
            $errors['category_id'] = 'Choose a valid primary category.';
        }
        $additionalIds = array_values(array_unique(array_map('intval', (array) (post('additional_categories', []) ?: []))));
        $additionalIds = array_values(array_filter($additionalIds, fn ($id) => $id > 0 && $id !== $primaryCategoryId));

        if (!$errors) {
            $slug = settings_slug_unique($slugInput !== '' ? $slugInput : $name, $businessId);
            $slugChanged = $slug !== $business['slug'];
            db_update('businesses', [
                'name' => str_limit($name, 190),
                'slug' => $slug,
                'category_id' => $primaryCategoryId,
                'description' => $description !== '' ? $description : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'state' => $state !== '' ? $state : null,
                'country' => $country !== '' ? $country : 'Nigeria',
                'currency' => $currency,
                'timezone' => $timezone,
                'status' => $status,
            ], 'id = :id', ['id' => $businessId]);

            // Categories are additive: replacing the selection keeps every record
            // and module the business already has.
            db_exec('DELETE FROM business_categories WHERE business_id = ?', [$businessId]);
            db_exec('INSERT IGNORE INTO business_categories (business_id, category_id, is_primary) VALUES (?, ?, 1)', [$businessId, $primaryCategoryId]);
            foreach ($additionalIds as $categoryId) {
                $extra = category_by_id($categoryId);
                if ($extra && $extra['status'] === 'active') {
                    db_exec('INSERT IGNORE INTO business_categories (business_id, category_id, is_primary) VALUES (?, ?, 0)', [$businessId, $categoryId]);
                }
            }
            activate_category_modules($businessId, $primaryCategoryId);

            activity_log('business.settings_updated', 'business', $businessId, [
                'category' => $primaryCategory['slug'],
                'additional_categories' => count($additionalIds),
                'slug_changed' => $slugChanged,
            ], $businessId);
            if ($slugChanged) {
                notify_business_owners($businessId, 'Website address changed', 'The public address for ' . $name . ' is now ' . business_public_url(['slug' => $slug, 'id' => $businessId]) . '.', 'app/domains.php', 'website');
            }
            flash('success', 'Business profile saved.' . ($slugChanged ? ' Your public address changed to ' . business_public_url(['slug' => $slug, 'id' => $businessId]) . '.' : ''));
            redirect('app/settings.php');
        }
        $openingHours = [];
        $socials = [];
        foreach ($days as $day) {
            $openingHours[$day] = [
                'closed' => !empty(post('closed_' . strtolower($day))),
                'open' => (string) post('open_' . strtolower($day), ''),
                'close' => (string) post('close_' . strtolower($day), ''),
            ];
        }
        foreach (array_keys($socialKeys) as $key) {
            $socials[$key] = trim((string) post('social_' . $key, ''));
        }
    }

    if ($action === 'hours') {
        $newHours = [];
        foreach ($days as $day) {
            $key = strtolower($day);
            $closed = !empty(post('closed_' . $key));
            $open = (string) post('open_' . $key, '');
            $close = (string) post('close_' . $key, '');
            foreach (['open' => $open, 'close' => $close] as $label => $time) {
                if (!$closed && $time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
                    $errors['hours'] = 'Use HH:MM for opening and closing times.';
                }
            }
            $newHours[$day] = ['closed' => $closed, 'open' => $open, 'close' => $close];
        }
        if (!$errors) {
            db_update('businesses', ['opening_hours' => json_encode($newHours)], 'id = :id', ['id' => $businessId]);
            activity_log('business.hours_updated', 'business', $businessId, [], $businessId);
            flash('success', 'Opening hours saved.');
            redirect('app/settings.php');
        }
        $openingHours = $newHours;
    }

    if ($action === 'socials') {
        $newSocials = [];
        foreach ($socialKeys as $key => $label) {
            $value = trim((string) post('social_' . $key, ''));
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors['social_' . $key] = 'Enter a full URL, including https://.';
            }
            $newSocials[$key] = $value;
        }
        if (!$errors) {
            db_update('businesses', ['socials' => json_encode($newSocials)], 'id = :id', ['id' => $businessId]);
            activity_log('business.socials_updated', 'business', $businessId, [], $businessId);
            flash('success', 'Social links saved.');
            redirect('app/settings.php');
        }
        $socials = $newSocials;
    }
}

/* ---------- Data for the form ---------- */
$categories = all_categories();
$grouped = [];
foreach ($categories as $category) {
    if ($category['parent_id']) {
        $grouped[(int) $category['parent_id']][] = $category;
    }
}
$parents = array_values(array_filter($categories, fn ($category) => empty($category['parent_id'])));
$selectedAdditional = array_map(
    'intval',
    array_column(db_all('SELECT category_id FROM business_categories WHERE business_id = ? AND is_primary = 0', [$businessId]), 'category_id')
);
$openingHours = $openingHours ?: [];
$socials = $socials ?: [];
$primaryCategoryId = (int) ($_POST['category_id'] ?? $business['category_id']);

layout_app_start($business, 'settings', 'Settings', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Business settings</h1>
    <p>Details used across your dashboard, website and customer messages.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e(app_url('app/appearance.php')) ?>"><?= icon('image', 'icon icon-sm') ?> Logo &amp; design</a>
    <a class="btn btn-ghost" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View site</a>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><?= icon('alert') ?><div><?= e(reset($errors)) ?></div></div>
<?php endif; ?>
<?php if (!$canManage): ?>
  <div class="alert alert-warning"><?= icon('lock') ?><div>You can view these settings, but only an owner or manager can change them.</div></div>
<?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Business profile</h2>
          <p class="small muted" style="margin:4px 0 0">Public details customers see on your website.</p>
        </div>
      </div>
      <form method="post" action="<?= e(app_url('app/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <fieldset <?= $canManage ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
          <div class="form-grid">
            <div class="field">
              <label for="name">Business name</label>
              <input type="text" id="name" name="name" value="<?= old('name', $business['name']) ?>" maxlength="190" required>
              <?= render_field_error($errors, 'name') ?>
            </div>
            <div class="field">
              <label for="slug">Public address</label>
              <div class="input-prefix">
                <span><?= e($slugPrefix) ?></span>
                <input type="text" id="slug" name="slug" value="<?= old('slug', $business['slug']) ?>" pattern="[a-z0-9-]+" autocomplete="off">
                <?php if ($slugSuffix !== ''): ?><span class="input-suffix"><?= e($slugSuffix) ?></span><?php endif; ?>
              </div>
              <?= render_field_error($errors, 'slug') ?>
              <span class="field-help">Lowercase letters, numbers and hyphens. Changing this changes your website link. A few words (such as <code>admin</code> or <code>app</code>) are reserved.</span>
            </div>
            <div class="field">
              <label for="email">Public email</label>
              <input type="email" id="email" name="email" value="<?= old('email', $business['email'] ?? '') ?>" autocomplete="email">
              <?= render_field_error($errors, 'email') ?>
            </div>
            <div class="field">
              <label for="phone">Phone</label>
              <input type="text" id="phone" name="phone" value="<?= old('phone', $business['phone'] ?? '') ?>" autocomplete="tel">
              <?= render_field_error($errors, 'phone') ?>
            </div>
            <div class="field">
              <label for="whatsapp">WhatsApp</label>
              <input type="text" id="whatsapp" name="whatsapp" value="<?= old('whatsapp', $business['whatsapp'] ?? '') ?>" placeholder="+234 800 000 0000">
              <?= render_field_error($errors, 'whatsapp') ?>
            </div>
            <div class="field">
              <label for="status">Business status</label>
              <select id="status" name="status">
                <option value="active" <?= selected(old('status', $business['status']), 'active') ?>>Open / active</option>
                <option value="closed" <?= selected(old('status', $business['status']), 'closed') ?>>Temporarily closed</option>
              </select>
              <?= render_field_error($errors, 'status') ?>
            </div>
          </div>

          <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="3000" placeholder="What you do, who you serve and what makes you different."><?= old('description', $business['description'] ?? '') ?></textarea>
            <?= render_field_error($errors, 'description') ?>
          </div>

          <div class="form-grid">
            <div class="field">
              <label for="address">Street address</label>
              <input type="text" id="address" name="address" value="<?= old('address', $business['address'] ?? '') ?>">
            </div>
            <div class="field">
              <label for="city">City</label>
              <input type="text" id="city" name="city" value="<?= old('city', $business['city'] ?? '') ?>">
            </div>
            <div class="field">
              <label for="state">State / region</label>
              <input type="text" id="state" name="state" value="<?= old('state', $business['state'] ?? '') ?>">
            </div>
            <div class="field">
              <label for="country">Country</label>
              <select id="country" name="country">
                <?php foreach (country_options() as $country): ?>
                  <option value="<?= e($country) ?>" <?= selected(old('country', $business['country'] ?? 'Nigeria'), $country) ?>><?= e($country) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="currency">Currency</label>
              <select id="currency" name="currency">
                <?php foreach (currency_options() as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= selected(old('currency', $business['currency']), $code) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= render_field_error($errors, 'currency') ?>
            </div>
            <div class="field">
              <label for="timezone">Timezone</label>
              <select id="timezone" name="timezone">
                <?php foreach ($timezones as $tz => $label): ?>
                  <option value="<?= e($tz) ?>" <?= selected(old('timezone', $business['timezone']), $tz) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?= render_field_error($errors, 'timezone') ?>
            </div>
          </div>

          <div class="divider"></div>
          <h3>Categories</h3>
          <p class="small muted">Your primary category sets the dashboard and starter website. Additional categories help customers find you. Changing categories never deletes your existing records.</p>
          <div class="form-grid">
            <div class="field">
              <label for="category_id">Primary category</label>
              <select id="category_id" name="category_id" required>
                <option value="">Choose a category</option>
                <?php foreach ($parents as $parent): ?>
                  <optgroup label="<?= e($parent['name']) ?>">
                    <?php foreach ($grouped[(int) $parent['id']] ?? [] as $child): ?>
                      <option value="<?= (int) $child['id'] ?>" <?= selected($primaryCategoryId, $child['id']) ?>><?= e($child['name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <?= render_field_error($errors, 'category_id') ?>
            </div>
            <div class="field">
              <label for="additional_categories">Additional categories</label>
              <select id="additional_categories" name="additional_categories[]" multiple size="8">
                <?php foreach ($parents as $parent): ?>
                  <optgroup label="<?= e($parent['name']) ?>">
                    <?php foreach ($grouped[(int) $parent['id']] ?? [] as $child): ?>
                      <option value="<?= (int) $child['id'] ?>" <?= in_array((int) $child['id'], $selectedAdditional, true) ? 'selected' : '' ?>><?= e($child['name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <span class="field-help">Hold Ctrl/Cmd to select more than one.</span>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save profile</button>
          </div>
        </fieldset>
      </form>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Opening hours</h2></div>
      <form method="post" action="<?= e(app_url('app/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="hours">
        <fieldset <?= $canManage ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Day</th><th>Closed</th><th>Opens</th><th>Closes</th></tr></thead>
              <tbody>
                <?php foreach ($days as $day): ?>
                  <?php $key = strtolower($day); $value = $openingHours[$day] ?? []; ?>
                  <tr>
                    <td class="strong"><?= e($day) ?></td>
                    <td><input type="checkbox" name="closed_<?= e($key) ?>" value="1" <?= !empty($value['closed']) ? 'checked' : '' ?>></td>
                    <td><input type="time" name="open_<?= e($key) ?>" value="<?= e($value['open'] ?? '09:00') ?>"></td>
                    <td><input type="time" name="close_<?= e($key) ?>" value="<?= e($value['close'] ?? '17:00') ?>"></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save hours</button>
          </div>
        </fieldset>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Social links</h2></div>
      <form method="post" action="<?= e(app_url('app/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="socials">
        <fieldset <?= $canManage ? '' : 'disabled' ?> style="border:0;padding:0;margin:0">
          <div class="form-grid">
            <?php foreach ($socialKeys as $key => $label): ?>
              <div class="field">
                <label for="social_<?= e($key) ?>"><?= e($label) ?></label>
                <input type="url" id="social_<?= e($key) ?>" name="social_<?= e($key) ?>" value="<?= e($socials[$key] ?? '') ?>" placeholder="https://">
                <?= render_field_error($errors, 'social_' . $key) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save social links</button>
          </div>
        </fieldset>
      </form>
    </div>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">At a glance</h2></div>
      <div class="card-body">
        <div class="flex mb-2">
          <?= business_logo_html($business, 'avatar avatar-lg') ?>
          <div>
            <div class="strong"><?= e($business['name']) ?></div>
            <div class="small muted"><?= e($business['category_name'] ?? 'Business') ?></div>
          </div>
        </div>
        <div class="small muted">Public address</div>
        <div class="mono small mb-2" style="word-break:break-all"><?= e(business_public_url($business)) ?></div>
        <div class="small muted">Plan</div>
        <div class="strong mb-2"><?= e(business_plan($business)['name'] ?? 'Free') ?></div>
        <a class="btn btn-soft btn-block" href="<?= e(app_url('app/appearance.php')) ?>"><?= icon('image', 'icon icon-sm') ?> Change logo &amp; design</a>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Categories &amp; modules</h2></div>
      <div class="card-body">
        <p class="small muted">Changing category adds the modules for the new category. Existing modules and records stay available so nothing is lost.</p>
        <a class="btn btn-ghost btn-block" href="<?= e(app_url('app/dashboard.php')) ?>"><?= icon('grid', 'icon icon-sm') ?> See dashboard modules</a>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Danger zone</h2></div>
      <div class="card-body">
        <p class="small muted">Closing your business hides the public website. Your data is kept. To delete the business permanently, contact support.</p>
        <a class="btn btn-ghost btn-block" href="mailto:<?= e((string) setting('support_email', 'support@uraio.com')) ?>?subject=Business%20settings%20for%20<?= e(rawurlencode((string) $business['name'])) ?>"><?= icon('mail', 'icon icon-sm') ?> Contact support</a>
      </div>
    </div>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
