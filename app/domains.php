<?php
/**
 * Custom domains: add a domain, prove ownership with a DNS TXT record, and
 * choose the primary address. Nothing is marked verified without DNS proof.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$business = require_business();
$businessId = (int) $business['id'];
$website = business_website($businessId);
$canCustomDomain = business_can('custom_domain', $business);
$errors = [];

function normalize_domain_input(string $input): string
{
    $input = strtolower(trim($input));
    if ($input === '') {
        return '';
    }
    if (str_contains($input, '://')) {
        $input = (string) (parse_url($input, PHP_URL_HOST) ?? '');
    }
    $input = preg_replace('#^www\.#', '', rtrim($input, '.'));
    return preg_replace('/[^a-z0-9.-]/', '', (string) $input);
}

function domain_is_valid(string $domain): bool
{
    return $domain !== ''
        && strlen($domain) <= 190
        && filter_var($domain, FILTER_VALIDATE_IP) === false
        && (bool) preg_match('/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
}

function domain_txt_matches(string $domain, string $token): bool
{
    $records = @dns_get_record('_uraio.' . $domain, DNS_TXT);
    if (!is_array($records)) {
        return false;
    }
    foreach ($records as $record) {
        $values = $record['txt'] ?? ($record['entries'] ?? []);
        foreach ((array) $values as $value) {
            if (is_string($value) && hash_equals($token, trim($value, "\"' "))) {
                return true;
            }
        }
    }
    return false;
}

function domain_dns_state(string $domain): array
{
    $cname = @dns_get_record($domain, DNS_CNAME);
    $a = @dns_get_record($domain, DNS_A);
    $aaaa = @dns_get_record($domain, DNS_AAAA);
    return [
        'cname' => is_array($cname) ? array_column($cname, 'target') : [],
        'a' => is_array($a) ? array_column($a, 'ip') : [],
        'aaaa' => is_array($aaaa) ? array_column($aaaa, 'ipv6') : [],
    ];
}

function verify_domain_row(array $domain): bool
{
    if (($domain['status'] ?? '') === 'verified') {
        return true;
    }
    $token = (string) ($domain['verification_token'] ?? '');
    if ($token === '' || !domain_txt_matches((string) $domain['domain'], $token)) {
        return false;
    }
    db_update('website_domains', [
        'status' => 'verified',
        'verified_at' => date('Y-m-d H:i:s'),
        'ssl_status' => 'pending',
    ], 'id = :id', ['id' => $domain['id']]);
    if (!(int) db_val('SELECT COUNT(*) FROM website_domains WHERE business_id = ? AND is_primary = 1', [$domain['business_id']])) {
        db_update('website_domains', ['is_primary' => 1], 'id = :id', ['id' => $domain['id']]);
    }
    return true;
}

/* ---------- Actions ---------- */
if (is_post()) {
    verify_csrf();
    if (!in_array($business['member_role'] ?? '', ['owner', 'manager'], true) && !is_admin_user($user)) {
        flash('danger', 'Only an owner or manager can manage domains.');
        redirect('app/domains.php');
    }
    $action = (string) post('action', '');
    $domainId = (int) post('domain_id', 0);
    $row = $domainId > 0
        ? db_row('SELECT * FROM website_domains WHERE id = ? AND business_id = ?', [$domainId, $businessId])
        : null;

    if ($action === 'add') {
        if (!$canCustomDomain) {
            $errors['domain'] = 'Custom domains are available on paid plans. Upgrade in Billing first.';
        } else {
            $domain = normalize_domain_input((string) post('domain', ''));
            $siteBase = strtolower(site_domain());
            if (!domain_is_valid($domain)) {
                $errors['domain'] = 'Enter a valid domain, for example yourbusiness.com.';
            } elseif ($domain === $siteBase || str_ends_with($domain, '.' . $siteBase)) {
                $errors['domain'] = 'Use a domain you own. ' . $siteBase . ' addresses are managed by Uraio.';
            } elseif (db_val('SELECT id FROM website_domains WHERE domain = ?', [$domain])) {
                $errors['domain'] = 'That domain is already connected to a business. Contact support if you believe this is a mistake.';
            } elseif ((int) db_val('SELECT COUNT(*) FROM website_domains WHERE business_id = ?', [$businessId]) >= 5) {
                $errors['domain'] = 'You can connect up to 5 domains per business. Remove one first.';
            } else {
                $token = 'uraio-verify=' . bin2hex(random_bytes(12));
                $newId = db_insert('website_domains', [
                    'business_id' => $businessId,
                    'domain' => $domain,
                    'type' => 'custom',
                    'is_primary' => 0,
                    'verification_token' => $token,
                    'status' => 'pending',
                    'ssl_status' => 'none',
                    'dns_instructions' => 'Add a TXT record named _uraio.' . $domain . ' with the verification value. Then point the domain to sites.' . site_domain() . ' using a CNAME (or contact support for an A record if your registrar does not allow apex CNAMEs).',
                ]);
                activity_log('domain.added', 'website_domain', $newId, ['domain' => $domain], $businessId);
                flash('info', 'Domain added. Add the TXT record below, then click Verify DNS.');
                redirect('app/domains.php?highlight=' . $newId);
            }
        }
    }

    if ($action === 'verify' && $row) {
        if (verify_domain_row($row)) {
            activity_log('domain.verified', 'website_domain', $domainId, ['domain' => $row['domain']], $businessId);
            send_email_template('domain_connected', (string) $user['email'], [
                'name' => $user['name'],
                'business' => $business['name'],
                'domain' => $row['domain'],
            ]);
            flash('success', 'DNS proof found. ' . $row['domain'] . ' is verified. Your hosting provider still needs to issue SSL for it; support will confirm when it is live.');
        } else {
            db_update('website_domains', ['status' => 'failed'], 'id = :id', ['id' => $domainId]);
            flash('danger', 'We could not find the TXT record yet. DNS can take up to 48 hours to propagate. Add the record and try again.');
        }
        redirect('app/domains.php');
    }

    if ($action === 'refresh' && $row) {
        if ($row['status'] === 'verified') {
            flash('info', 'That domain is already verified.');
        } elseif (verify_domain_row($row)) {
            activity_log('domain.verified', 'website_domain', $domainId, ['domain' => $row['domain']], $businessId);
            flash('success', 'DNS proof found. ' . $row['domain'] . ' is now verified.');
        } else {
            flash('warning', 'The TXT record is still not visible. Check the name and value, then try again.');
        }
        redirect('app/domains.php');
    }

    if ($action === 'primary' && $row) {
        if ($row['status'] !== 'verified') {
            flash('danger', 'Verify the domain before making it primary.');
        } else {
            db_exec('UPDATE website_domains SET is_primary = 0 WHERE business_id = ?', [$businessId]);
            db_update('website_domains', ['is_primary' => 1], 'id = :id', ['id' => $domainId]);
            activity_log('domain.primary_set', 'website_domain', $domainId, ['domain' => $row['domain']], $businessId);
            flash('success', $row['domain'] . ' is now the primary address for your website.');
        }
        redirect('app/domains.php');
    }

    if ($action === 'remove' && $row) {
        db_exec('DELETE FROM website_domains WHERE id = ? AND business_id = ?', [$domainId, $businessId]);
        activity_log('domain.removed', 'website_domain', $domainId, ['domain' => $row['domain']], $businessId);
        flash('success', 'Domain removed. You can connect it again later.');
        redirect('app/domains.php');
    }
}

$domains = db_all('SELECT * FROM website_domains WHERE business_id = ? ORDER BY is_primary DESC, id DESC', [$businessId]);
$highlight = (int) query('highlight', 0);
$subdomainUrl = business_public_url($business);

layout_app_start($business, 'domains', 'Domains', $business['name']);
?>
<div class="page-head">
  <div>
    <h1>Domains</h1>
    <p>Connect a domain you own to <?= e($business['name']) ?>. Uraio verifies ownership before routing traffic.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-ghost" href="<?= e($subdomainUrl) ?>" target="_blank" rel="noopener"><?= icon('external', 'icon icon-sm') ?> View current site</a>
  </div>
</div>

<?php layout_flashes(); ?>
<?php if (!empty($errors['domain'])): ?><div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['domain']) ?></div></div><?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head">
        <div>
          <h2 class="mt-0">Your addresses</h2>
          <p class="small muted" style="margin:4px 0 0">The primary verified domain is used for links, emails and previews.</p>
        </div>
      </div>
      <div class="card-body">
        <div class="field">
          <label>Uraio address</label>
          <div class="flex">
            <input type="text" readonly value="<?= e($subdomainUrl) ?>" style="flex:1">
            <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($subdomainUrl) ?>">Copy</button>
          </div>
          <span class="field-help">Always available while your website is published.</span>
        </div>
      </div>
      <?php if (!$domains): ?>
        <div class="card-body"><p class="small muted" style="margin:0">No custom domains connected yet.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Domain</th><th>Status</th><th>SSL</th><th>Primary</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($domains as $domain): ?>
                <tr<?= $highlight === (int) $domain['id'] ? ' class="is-highlighted"' : '' ?>>
                  <td>
                    <span class="cell-main"><?= e($domain['domain']) ?></span>
                    <span class="cell-sub">Added <?= e(nice_date($domain['created_at'])) ?><?= $domain['verified_at'] ? ' · Verified ' . e(nice_date($domain['verified_at'])) : '' ?></span>
                  </td>
                  <td><?= status_badge((string) $domain['status']) ?></td>
                  <td><?= status_badge((string) $domain['ssl_status']) ?></td>
                  <td><?= (int) $domain['is_primary'] === 1 ? '<span class="badge badge-success">Primary</span>' : '<span class="muted small">—</span>' ?></td>
                  <td>
                    <div class="table-actions">
                      <?php if ($domain['status'] !== 'verified'): ?>
                        <form method="post" action="<?= e(app_url('app/domains.php')) ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="verify">
                          <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                          <button class="btn btn-soft btn-sm" type="submit">Verify DNS</button>
                        </form>
                      <?php elseif ((int) $domain['is_primary'] !== 1): ?>
                        <form method="post" action="<?= e(app_url('app/domains.php')) ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="primary">
                          <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                          <button class="btn btn-ghost btn-sm" type="submit">Make primary</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="<?= e(app_url('app/domains.php')) ?>" data-confirm="Remove <?= e($domain['domain']) ?> from this business?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" aria-label="Remove domain"><?= icon('trash', 'icon icon-sm') ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php foreach ($domains as $domain): ?>
      <?php if ($domain['status'] !== 'verified' || $highlight === (int) $domain['id']): ?>
        <?php $dnsState = domain_dns_state((string) $domain['domain']); ?>
        <div class="card mb-2">
          <div class="card-head">
            <div>
              <h2 class="mt-0">Connect <?= e($domain['domain']) ?></h2>
              <p class="small muted" style="margin:4px 0 0">Step 1 proves you own the domain. Step 2 points traffic to Uraio.</p>
            </div>
            <?= status_badge((string) $domain['status']) ?>
          </div>
          <div class="card-body">
            <h3 style="font-size:.95rem">1. Add this TXT record</h3>
            <div class="table-wrap mb-2">
              <table class="table">
                <thead><tr><th>Type</th><th>Name / host</th><th>Value</th></tr></thead>
                <tbody>
                  <tr>
                    <td class="mono">TXT</td>
                    <td class="mono">_uraio.<?= e($domain['domain']) ?></td>
                    <td>
                      <div class="flex">
                        <code class="mono small" style="flex:1;word-break:break-all"><?= e($domain['verification_token']) ?></code>
                        <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($domain['verification_token']) ?>">Copy</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="small muted">Some registrars want only <code>_uraio</code> as the host because they append the domain automatically. Keep the value exactly as shown.</p>

            <h3 style="font-size:.95rem;margin-top:22px">2. Point the domain to Uraio</h3>
            <div class="table-wrap mb-2">
              <table class="table">
                <thead><tr><th>Type</th><th>Name / host</th><th>Target</th></tr></thead>
                <tbody>
                  <tr><td class="mono">CNAME</td><td class="mono"><?= e($domain['domain']) ?></td><td class="mono">sites.<?= e(site_domain()) ?></td></tr>
                  <tr><td class="mono">CNAME</td><td class="mono">www.<?= e($domain['domain']) ?></td><td class="mono">sites.<?= e(site_domain()) ?></td></tr>
                </tbody>
              </table>
            </div>
            <p class="small muted">If your registrar does not allow a CNAME on the root domain, contact support for the A/ALIAS target. Do not change nameservers unless support asks you to.</p>

            <div class="alert <?= ($dnsState['cname'] || $dnsState['a'] || $dnsState['aaaa']) ? 'alert-info' : 'alert-warning' ?>">
              <?= icon('info') ?>
              <div>
                <?php if ($dnsState['cname'] || $dnsState['a'] || $dnsState['aaaa']): ?>
                  DNS is resolving for this domain. Current target: <span class="mono"><?= e(implode(', ', array_merge($dnsState['cname'], $dnsState['a'], $dnsState['aaaa']))) ?></span>.
                <?php else: ?>
                  DNS is not resolving yet. Records can take up to 48 hours to propagate.
                <?php endif; ?>
              </div>
            </div>

            <div class="form-actions">
              <form method="post" action="<?= e(app_url('app/domains.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
                <button class="btn btn-primary" type="submit"><?= icon('refresh', 'icon icon-sm') ?> Verify DNS</button>
              </form>
              <?php if ($domain['status'] === 'verified'): ?>
                <span class="badge badge-success">Verified <?= e(nice_date($domain['verified_at'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Add a domain</h2></div>
      <div class="card-body">
        <?php if (!$canCustomDomain): ?>
          <div class="alert alert-warning"><?= icon('lock') ?><div>Custom domains are available on Pro, Premium and Business plans.</div></div>
          <a class="btn btn-primary btn-block" href="<?= e(app_url('app/billing.php')) ?>">Upgrade plan</a>
        <?php else: ?>
          <form method="post" action="<?= e(app_url('app/domains.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="field">
              <label for="domain">Your domain</label>
              <input type="text" id="domain" name="domain" placeholder="yourbusiness.com" required autocomplete="off">
              <span class="field-help">Enter the domain without https:// or www. You must control its DNS records.</span>
            </div>
            <button class="btn btn-primary btn-block" type="submit"><?= icon('plus', 'icon icon-sm') ?> Add domain</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">How verification works</h2></div>
      <div class="card-body">
        <ol class="check-list" style="list-style:none;padding:0">
          <li>We give you a unique TXT value for the domain.</li>
          <li>You add it at your registrar. We check DNS directly.</li>
          <li>Once verified, support issues SSL for the hostname.</li>
          <li>Only then does the custom domain serve your website.</li>
        </ol>
        <p class="small muted" style="margin-bottom:0">We never claim a domain is live just because it was typed in. Unverified domains are never routed.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Need help?</h2></div>
      <div class="card-body">
        <p class="small muted">Send us your registrar and a screenshot of the DNS page and we will guide you.</p>
        <a class="btn btn-ghost btn-block" href="mailto:<?= e((string) setting('support_email', 'support@uraio.com')) ?>?subject=Domain%20help%20for%20<?= e(rawurlencode((string) $business['name'])) ?>"><?= icon('mail', 'icon icon-sm') ?> Email support</a>
      </div>
    </div>
  </div>
</div>
<?php
layout_app_end(['scripts' => ['app.js']]);
