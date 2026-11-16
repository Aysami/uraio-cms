<?php
/**
 * Public landing page. Data is read from the live platform database.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$categories = popular_categories(18);
if (!$categories) {
    $categories = db_all("SELECT * FROM categories WHERE status = 'active' AND parent_id IS NOT NULL ORDER BY sort_order ASC, name ASC LIMIT 18");
}
$categoryCount = (int) db_val("SELECT COUNT(*) FROM categories WHERE status = 'active' AND parent_id IS NOT NULL");
$groupCount = (int) db_val("SELECT COUNT(*) FROM categories WHERE status = 'active' AND parent_id IS NULL");
$moduleCount = (int) db_val("SELECT COUNT(*) FROM modules WHERE status = 'active'");
$plans = db_all("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY sort_order ASC, price_monthly ASC");
$templateCount = (int) db_val("SELECT COUNT(*) FROM website_templates WHERE status = 'active'");
$user = current_user();

layout_head('Run your business from one place', [
    'body_class' => 'landing-body',
    'description' => 'Uraio Business OS gives every business a category-aware dashboard, customer records, orders, bookings, payments and a published website.',
]);
layout_public_nav();
?>
<main>
  <section class="landing-hero">
    <div class="container hero-grid">
      <div>
        <span class="eyebrow"><?= icon('sparkles', 'icon icon-sm') ?> Built for <?= e($groupCount) ?> business groups</span>
        <h1 class="hero-title">The operating system for your <em>whole business</em>.</h1>
        <p class="hero-sub">Pick your category. Get a dashboard that fits. Manage customers, orders, bookings and payments, then publish a professional website — without stitching five tools together.</p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(app_url('app/dashboard.php')) ?>">Open your dashboard <?= icon('arrow-right', 'icon icon-sm') ?></a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(app_url('auth/register.php')) ?>">Start free <?= icon('arrow-right', 'icon icon-sm') ?></a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-lg" href="#categories">Browse <?= e(number_format($categoryCount)) ?> categories</a>
        </div>
        <div class="hero-proof">
          <span><?= icon('check') ?> No card required</span>
          <span><?= icon('check') ?> Website included</span>
          <span><?= icon('check') ?> Works on mobile</span>
        </div>
      </div>
      <div class="hero-visual" aria-hidden="true">
        <div class="hero-visual-top"><i></i><i></i><i></i></div>
        <div class="hero-visual-body">
          <div class="hero-mini-side">
            <div class="m-line active"></div>
            <div class="m-line"></div>
            <div class="m-line"></div>
            <div class="m-line" style="width:65%"></div>
            <div class="m-line" style="margin-top:22px"></div>
            <div class="m-line"></div>
            <div class="m-line" style="width:70%"></div>
          </div>
          <div class="hero-mini-main">
            <div class="m-head"></div>
            <div class="hero-mini-cards"><span></span><span></span><span></span></div>
            <div class="hero-mini-chart"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="landing-section alt" id="features">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">One platform</span>
        <h2>Everything a small business actually uses</h2>
        <p>Universal tools with category-specific modules layered on top. Turn modules on as your business grows.</p>
      </div>
      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-icon"><?= icon('users') ?></div>
          <h3>Customers &amp; CRM</h3>
          <p>Keep contact details, notes, order history and communication in one tenant-isolated record.</p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><?= icon('shopping-bag') ?></div>
          <h3>Orders &amp; inventory</h3>
          <p>Products, stock levels, order items, invoices and payment status that stay in sync.</p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><?= icon('calendar') ?></div>
          <h3>Bookings &amp; schedules</h3>
          <p>Take booking requests from your website and manage appointments or reservations.</p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><?= icon('globe') ?></div>
          <h3>Your own website</h3>
          <p>Choose a template, edit sections and publish to a Uraio subdomain or your custom domain.</p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><?= icon('chart') ?></div>
          <h3>Real analytics</h3>
          <p>Track website visits, customers, orders and revenue from your live database — never demo numbers.</p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><?= icon('shield') ?></div>
          <h3>Secure by design</h3>
          <p>Server-enforced tenant isolation, CSRF protection, prepared queries, audit logs and rate limiting.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="landing-section" id="how">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">From sign-up to live</span>
        <h2>Four steps to a working business</h2>
      </div>
      <div class="steps-grid">
        <div class="step-card"><div class="step-no">1</div><h3>Create your account</h3><p>Register with your email and verify it. Your workspace is ready immediately.</p></div>
        <div class="step-card"><div class="step-no">2</div><h3>Choose a category</h3><p>Search or browse <?= e(number_format($categoryCount)) ?> categories across <?= e($groupCount) ?> groups.</p></div>
        <div class="step-card"><div class="step-no">3</div><h3>Add business details</h3><p>Name, contact details, logo and description power both your dashboard and website.</p></div>
        <div class="step-card"><div class="step-no">4</div><h3>Pick a template and publish</h3><p>Start with a category-matched template, edit sections, then publish when ready.</p></div>
      </div>
    </div>
  </section>

  <section class="landing-section alt" id="categories">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow"><?= e(number_format($categoryCount)) ?> categories</span>
        <h2>A dashboard that speaks your business</h2>
        <p>Restaurants get menus and reservations. Hotels get rooms. Clinics get patients and appointments. Every business also gets the universal tools.</p>
      </div>
      <div class="category-showcase">
        <?php foreach ($categories as $category): ?>
          <a class="category-pill" href="<?= e(app_url('auth/register.php?category=' . urlencode($category['slug']))) ?>">
            <span aria-hidden="true"><?= e($category['icon'] ?: '🏷️') ?></span><?= e($category['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="text-center mt-3"><a class="btn btn-ghost" href="<?= e(app_url('auth/register.php')) ?>">Find your category <?= icon('arrow-right', 'icon icon-sm') ?></a></p>
    </div>
  </section>

  <section class="landing-section" id="pricing">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">Simple pricing</span>
        <h2>Start free, upgrade when you are ready</h2>
        <p>Every plan includes a website. Paid plans unlock a custom domain, more staff and higher limits.</p>
      </div>
      <div class="pricing-grid">
        <?php foreach ($plans as $plan): ?>
          <?php $features = json_decode_assoc($plan['features']); ?>
          <article class="plan-card<?= (int) $plan['is_popular'] === 1 ? ' is-popular' : '' ?>">
            <?php if ((int) $plan['is_popular'] === 1): ?><span class="popular-tag">Most popular</span><?php endif; ?>
            <strong><?= e($plan['name']) ?></strong>
            <div class="plan-price"><?= (float) $plan['price_monthly'] > 0 ? money((float) $plan['price_monthly'], $plan['currency']) : 'Free' ?><small><?= (float) $plan['price_monthly'] > 0 ? ' / month' : '' ?></small></div>
            <p class="small muted" style="margin:0"><?= e($plan['description']) ?></p>
            <ul class="plan-features">
              <?php foreach ($features as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
            </ul>
            <a class="btn <?= (int) $plan['is_popular'] === 1 ? 'btn-primary' : 'btn-ghost' ?> btn-block" href="<?= e(app_url('auth/register.php?plan=' . urlencode($plan['slug']))) ?>">
              <?= (float) $plan['price_monthly'] > 0 ? 'Choose ' . e($plan['name']) : 'Start free' ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="text-center small muted mt-3">Prices are shown in <?= e($plans[0]['currency'] ?? 'NGN') ?>. Billing cycles and coupons are configurable by the platform administrator.</p>
    </div>
  </section>

  <section class="landing-section alt" id="faq">
    <div class="container">
      <div class="section-head">
        <span class="eyebrow">Questions</span>
        <h2>Good to know</h2>
      </div>
      <div class="faq-list">
        <details class="faq-item"><summary>Can I manage more than one business?</summary><p>Yes. Your account can own or join multiple businesses. Each business has completely separate records and its own website. Paid plans increase how many businesses you can create.</p></details>
        <details class="faq-item"><summary>What happens to my data if I change category?</summary><p>Changing category never deletes records. It adds the modules that fit your new category, and your existing universal records remain untouched.</p></details>
        <details class="faq-item"><summary>Can I use my own domain?</summary><p>Paid plans support custom domains. Add the domain in the dashboard, point its DNS records to Uraio, then verify it. Your website continues to work on the Uraio subdomain while DNS propagates.</p></details>
        <details class="faq-item"><summary>Are payments handled securely?</summary><p>Paystack and Flutterwave payments are verified on the server. A plan is only activated after a verified gateway response or an administrator-approved manual payment. Browser success pages never activate a plan.</p></details>
        <details class="faq-item"><summary>Is there a mobile app?</summary><p>Uraio is an installable PWA. Open the site on your phone and choose “Add to Home Screen” for an app-like experience with offline fallback.</p></details>
        <details class="faq-item"><summary>How many templates are available?</summary><p>There are <?= e($templateCount) ?> starter templates mapped to business groups, with reusable sections you can edit, reorder, hide or remove.</p></details>
      </div>
    </div>
  </section>

  <section class="landing-section">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2>Ready to run your business in one place?</h2>
          <p>Create a free account. No card, no setup fee. Publish when you are ready.</p>
        </div>
        <a class="btn btn-primary btn-lg" href="<?= e(app_url('auth/register.php')) ?>">Create your account <?= icon('arrow-right', 'icon icon-sm') ?></a>
      </div>
    </div>
  </section>
</main>
<?php
layout_public_footer();
?>
<div class="install-banner" id="install-banner">
  <span class="brand-mark"><img src="<?= e(app_url('assets/images/logo.png')) ?>" alt="Uraio"></span>
  <div>
    <h4>Install Uraio on this device</h4>
    <p>Quick access from your home screen, with offline support.</p>
    <button class="btn btn-primary btn-sm mt-1" type="button" id="install-app">Install app</button>
  </div>
  <button class="close" type="button" id="install-dismiss" aria-label="Dismiss"><?= icon('x', 'icon icon-sm') ?></button>
</div>
<?php
layout_end(['scripts' => ['app.js']]);
