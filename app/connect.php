<?php
/**
 * Uraio Connect: let third-party websites embed live widgets (and capture
 * bookings/messages) from this business — via a WP plugin or a script tag.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$website = business_website($businessId);

/* Ensure the business row includes the latest embed fields. */
$embed = db_row('SELECT id, embed_token, embed_origins, embed_enabled FROM businesses WHERE id = ?', [$businessId]);

if (is_post()) {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can manage website embeds.');
        redirect('app/connect.php');
    }
    $action = (string) post('action', '');

    if ($action === 'enable' && !($embed['embed_enabled'] ?? false)) {
        $token = embed_issue_token($businessId);
        activity_log('connect.enabled', 'business', $businessId, ['token_prefix' => substr($token, 0, 8)], $businessId);
        flash('success', 'Connect is live. Copy the snippets below into your other websites.');
        redirect('app/connect.php');
    }
    if ($action === 'disable') {
        embed_revoke_token($businessId);
        activity_log('connect.disabled', 'business', $businessId, [], $businessId);
        flash('success', 'Connect disabled. Existing embeds will stop working.');
        redirect('app/connect.php');
    }
    if ($action === 'regenerate') {
        embed_revoke_token($businessId);
        $token = embed_issue_token($businessId);
        activity_log('connect.token_regenerated', 'business', $businessId, [], $businessId);
        flash('warning', 'Token regenerated. Update the snippets on your other websites.');
        redirect('app/connect.php');
    }
    if ($action === 'origins') {
        $lines = preg_split('/[,\n]+/', (string) post('origins', '')) ?: [];
        $stored = embed_origins_set($lines);
        db_update('businesses', ['embed_origins' => $stored], 'id = :id', ['id' => $businessId]);
        activity_log('connect.origins_updated', 'business', $businessId, ['count' => count(json_decode($stored, true) ?: [])], $businessId);
        flash('success', 'Allowed origins saved.');
        redirect('app/connect.php');
    }
}

$embed = db_row('SELECT id, embed_token, embed_origins, embed_enabled FROM businesses WHERE id = ?', [$businessId]);
$token = (string) ($embed['embed_token'] ?? '');
$enabled = (bool) ($embed['embed_enabled'] ?? false);
$origins = embed_origins($embed ?: []);
$full = array_merge($business, ['embed_token' => $token]);
$brandHidden = embed_branding_hidden($business);
$downloadUrl = app_url('connect/downloads/uraio-connect.zip');

$widgets = [
    ['key' => 'menu', 'label' => 'Menu / Item list', 'source' => 'Menu items (restaurants)', 'note' => 'Auto-pulls active menu items, featured first.', 'height' => 560],
    ['key' => 'products', 'label' => 'Product grid', 'source' => 'Product catalogue', 'note' => 'Top 12 active products with prices.', 'height' => 560],
    ['key' => 'hours', 'label' => 'Opening hours & address', 'source' => 'Business opening hours', 'note' => 'Colour-coded open/closed per day.', 'height' => 320],
    ['key' => 'contact', 'label' => 'Contact form', 'source' => 'Website contact form', 'note' => "Messages land in your Uraio inbox.", 'height' => 420],
    ['key' => 'booking', 'label' => 'Booking form', 'source' => 'Bookings', 'note' => 'Requests arrive in your Uraio inbox for confirmation.', 'height' => 480],
    ['key' => 'reviews', 'label' => 'Reviews', 'source' => 'Approved reviews', 'note' => 'Latest approved reviews with star ratings.', 'height' => 520],
    ['key' => 'whatsapp', 'label' => 'WhatsApp button', 'source' => 'WhatsApp number', 'note' => 'One-tap chat button with your number.', 'height' => 72],
];

layout_app_start($business, 'connect', 'Connect', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Uraio Connect</h1>
    <p>Embed live content — and capture bookings and messages — on your other websites.</p>
  </div>
  <div class="page-actions">
    <?php if ($website): ?>
      <a class="btn btn-ghost" href="<?= e(business_public_url($business)) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View site</a>
    <?php endif; ?>
  </div>
</div>

<?php layout_flashes(); ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Embed token</h2>
          <p class="small muted" style="margin:4px 0 0">The token tells Uraio which business to load on your other site. It is safe to show publicly — it cannot be used to change anything.</p>
        </div>
        <?= $enabled ? status_badge('active') : status_badge('inactive') ?>
      </div>
      <div class="card-body">
        <?php if (!$enabled): ?>
          <div class="empty-state" style="padding:28px 12px">
            <div class="empty-icon"><?= icon('code') ?></div>
            <h3>Connect is off</h3>
            <p>Generate an embed token to start embedding widgets on your other websites.</p>
            <form method="post" action="<?= e(app_url('app/connect.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="enable">
              <button class="btn btn-primary" type="submit"><?= icon('plus', 'icon icon-sm') ?> Enable Connect</button>
            </form>
          </div>
        <?php else: ?>
          <div class="field">
            <label for="embed-token">Your embed token</label>
            <div class="flex">
              <input type="text" id="embed-token" readonly value="<?= e($token) ?>" style="flex:1">
              <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($token) ?>">Copy</button>
            </div>
            <span class="field-help">Never share your password — but this token is safe to put in public HTML.</span>
          </div>
          <div class="form-actions" style="margin-top:12px">
            <form method="post" action="<?= e(app_url('app/connect.php')) ?>" data-confirm="Regenerating the token will break every embed on your other websites. Continue?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="regenerate">
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh', 'icon icon-sm') ?> Regenerate token</button>
            </form>
            <form method="post" action="<?= e(app_url('app/connect.php')) ?>" data-confirm="Disable Connect? Every embed on your other websites will stop working immediately.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="disable">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)"><?= icon('x', 'icon icon-sm') ?> Disable</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($enabled): ?>
      <div class="card mb-2">
        <div class="card-head"><h2 class="mt-0">Allowed websites</h2></div>
        <form method="post" action="<?= e(app_url('app/connect.php')) ?>" class="card-body">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="origins">
          <div class="field">
            <label for="origins">One domain per line (or comma separated)</label>
            <textarea id="origins" name="origins" rows="4" placeholder="mybusiness.com&#10;another-site.com&#10;*.mybusiness.com"><?= e(implode("\n", $origins)) ?></textarea>
            <?php if (!$origins): ?>
              <div class="alert alert-warning" style="margin-top:10px"><?= icon('alert') ?><div>The list is empty, so <strong>any website</strong> can embed this business. Add at least one domain to lock it down.</div></div>
            <?php endif; ?>
            <span class="field-help">Wildcards supported: <code>*.mybusiness.com</code> matches all sub-domains. The token itself identifies the business.</span>
          </div>
          <button class="btn btn-primary btn-sm" type="submit"><?= icon('save', 'icon icon-sm') ?> Save origins</button>
        </form>
      </div>

      <div class="card">
        <div class="card-head"><h2 class="mt-0">Using WordPress?</h2></div>
        <div class="card-body">
          <p class="small">Install the <strong>Uraio Connect</strong> WordPress plugin to get shortcodes instead of raw embeds.</p>
          <a class="btn btn-primary btn-block" href="<?= e($downloadUrl) ?>" download><?= icon('download', 'icon icon-sm') ?> Download WordPress plugin</a>
          <p class="small muted" style="margin-top:10px">Shortcodes: <code>[uraio_menu]</code> · <code>[uraio_products]</code> · <code>[uraio_hours]</code> · <code>[uraio_contact]</code> · <code>[uraio_booking]</code> · <code>[uraio_reviews]</code> · <code>[uraio_whatsapp]</code></p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($enabled): ?>
    <div>
      <div class="card">
        <div class="card-head"><h2 class="mt-0">Widgets</h2></div>
        <div class="card-body" style="padding:12px">
          <?php foreach ($widgets as $widget): ?>
            <?php $widgetPreview = embed_widget_url($full, $widget['key']); ?>
            <div class="card mb-2" style="background:var(--surface-2);border:1px solid var(--line)">
              <div class="card-head" style="padding:12px 14px">
                <div>
                  <strong style="font-size:.85rem"><?= e($widget['label']) ?></strong>
                  <div class="small muted" style="margin:2px 0 0"><?= e($widget['source']) ?> — <?= e($widget['note']) ?></div>
                </div>
                <span class="badge badge-info" style="white-space:nowrap">height <?= (int) $widget['height'] ?>px</span>
              </div>
              <div class="card-body" style="padding:12px 14px">
                <div class="tabs" style="margin-bottom:10px">
                  <button class="tab is-active" type="button" data-tab-group="wg-<?= e($widget['key']) ?>" data-tab-target="wg-<?= e($widget['key']) ?>-html">iframe</button>
                  <button class="tab" type="button" data-tab-group="wg-<?= e($widget['key']) ?>" data-tab-target="wg-<?= e($widget['key']) ?>-js">Plain JS</button>
                  <button class="tab" type="button" data-tab-group="wg-<?= e($widget['key']) ?>" data-tab-target="wg-<?= e($widget['key']) ?>-wp">WordPress</button>
                </div>
                <div class="tab-panel is-active" id="wg-<?= e($widget['key']) ?>-html">
                  <textarea readonly rows="2" class="mono" style="font-size:.72rem;width:100%" onfocus="this.select()"><?= e(embed_iframe_snippet($full, $widget['key'])) ?></textarea>
                </div>
                <div class="tab-panel" id="wg-<?= e($widget['key']) ?>-js">
                  <textarea readonly rows="3" class="mono" style="font-size:.72rem;width:100%" onfocus="this.select()"><?= e(embed_js_snippet($full, $widget['key'])) ?></textarea>
                </div>
                <div class="tab-panel" id="wg-<?= e($widget['key']) ?>-wp">
                  <textarea readonly rows="1" class="mono" style="font-size:.78rem;width:100%" onfocus="this.select()"><?= e('[uraio_' . $widget['key'] . ']') ?></textarea>
                </div>
                <div class="flex" style="gap:8px;margin-top:8px">
                  <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e(embed_iframe_snippet($full, $widget['key'])) ?>"><?= icon('copy', 'icon icon-sm') ?> Copy</button>
                  <a class="btn btn-ghost btn-sm" href="<?= e($widgetPreview) ?>" target="_blank" rel="noopener"><?= icon('eye', 'icon icon-sm') ?> Preview</a>
                </div>
                <iframe src="<?= e($widgetPreview) ?>" loading="lazy" width="100%" height="<?= (int) $widget['height'] ?>" style="border:0;border-radius:10px;margin-top:10px;background:#fff" title="<?= e($widget['label']) ?>"></iframe>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$brandHidden): ?>
            <div class="alert alert-info"><?= icon('info') ?><div>Free plans show a small <strong>“Powered by Uraio”</strong> credit in the embed footer. <a class="link" href="<?= e(app_url('app/billing.php')) ?>">Upgrade to remove it</a>.</div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div>
      <div class="card">
        <div class="card-head"><h2 class="mt-0">What you can embed</h2></div>
        <div class="card-body">
          <p class="small">A tiny badge on your other websites: the <strong>menu</strong>, <strong>products</strong>, <strong>hours</strong>, <strong>opening days</strong>, <strong>reviews</strong> and <strong>contact/booking forms</strong> that live in your Uraio dashboard.</p>
          <p class="small">Works on any website — WordPress via our plugin, or plain HTML/JS with a single <code>&lt;script&gt;</code> line. Every booking and message lands immediately in your Uraio inbox.</p>
          <ul class="check-list">
            <li>One token per business — revocable anytime</li>
            <li>Locked to your websites only (allowlist)</li>
            <li>No extra logins for your customers</li>
            <li>Zero extra hosting or databases</li>
          </ul>
          <?php if (!$website): ?>
            <p class="small muted">Connect works even before you publish your Uraio site — the widgets read from your catalogue, not the public website.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);