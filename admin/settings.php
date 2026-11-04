<?php
/**
 * Admin: system settings.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/admin.php';
$user = require_admin_login();
$errors = [];

if (is_post()) {
    verify_csrf();
    $section = (string) post('section', 'general');

    if ($section === 'general') {
        $siteName = trim((string) post('site_name', ''));
        $supportEmail = trim((string) post('support_email', ''));
        $defaultCurrency = (string) post('default_currency', 'NGN');
        $defaultCountry = (string) post('default_country', 'Nigeria');
        $registrationOpen = !empty(post('registration_open'));
        $maintenanceMode = !empty(post('maintenance_mode'));
        $appKey = trim((string) post('app_key', ''));

        if ($siteName === '') {
            $errors['site_name'] = 'Site name is required.';
        }
        if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['support_email'] = 'Valid support email is required.';
        }
        if (!array_key_exists($defaultCurrency, currency_options())) {
            $errors['default_currency'] = 'Invalid currency.';
        }
        if (!in_array($defaultCountry, country_options(), true)) {
            $errors['default_country'] = 'Invalid country.';
        }
        if ($appKey !== '' && strlen($appKey) < 32) {
            $errors['app_key'] = 'App key should be at least 32 characters for security.';
        }

        if (!$errors) {
            set_setting('site_name', $siteName);
            set_setting('support_email', $supportEmail);
            set_setting('default_currency', $defaultCurrency);
            set_setting('default_country', $defaultCountry);
            set_setting('registration_open', $registrationOpen ? '1' : '0');
            set_setting('maintenance_mode', $maintenanceMode ? '1' : '0');
            if ($appKey !== '') {
                set_setting('app_key', $appKey);
            }
            flash('success', 'General settings saved.');
            redirect('admin/settings.php');
        }
    }

    if ($section === 'ai') {
        $monthlyLimit = max(0, (int) post('ai_monthly_limit', 20));
        $openaiKey = trim((string) post('openai_key', ''));
        $geminiKey = trim((string) post('gemini_key', ''));
        $groqKey = trim((string) post('groq_key', ''));

        set_setting('ai_monthly_limit', (string) $monthlyLimit, 'ai');
        if ($openaiKey !== '') set_setting('openai_key', $openaiKey, 'ai');
        if ($geminiKey !== '') set_setting('gemini_key', $geminiKey, 'ai');
        if ($groqKey !== '') set_setting('groq_key', $groqKey, 'ai');

        // Also update credentials.php if writable
        $credFile = BASE_PATH . '/config/credentials.php';
        if (is_writable($credFile)) {
            $cred = require $credFile;
            if ($openaiKey !== '') $cred['ai']['openai']['key'] = $openaiKey;
            if ($geminiKey !== '') $cred['ai']['gemini']['key'] = $geminiKey;
            if ($groqKey !== '') $cred['ai']['groq']['key'] = $groqKey;
            file_put_contents($credFile, '<?php\nreturn ' . var_export($cred, true) . ';\n');
        }

        flash('success', 'AI settings saved.');
        redirect('admin/settings.php');
    }

    if ($section === 'payments') {
        $paystackPublic = trim((string) post('paystack_public', ''));
        $paystackSecret = trim((string) post('paystack_secret', ''));
        $flutterwavePublic = trim((string) post('flutterwave_public', ''));
        $flutterwaveSecret = trim((string) post('flutterwave_secret', ''));
        $flutterwaveHash = trim((string) post('flutterwave_hash', ''));

        $gateways = json_decode_assoc(setting('payment_gateways', '{}'));
        if ($paystackPublic !== '') $gateways['paystack']['public'] = $paystackPublic;
        if ($paystackSecret !== '') $gateways['paystack']['secret'] = $paystackSecret;
        if ($flutterwavePublic !== '') $gateways['flutterwave']['public'] = $flutterwavePublic;
        if ($flutterwaveSecret !== '') $gateways['flutterwave']['secret'] = $flutterwaveSecret;
        if ($flutterwaveHash !== '') $gateways['flutterwave']['webhook_hash'] = $flutterwaveHash;

        set_setting('payment_gateways', json_encode($gateways), 'payments');

        $credFile = BASE_PATH . '/config/credentials.php';
        if (is_writable($credFile)) {
            $cred = require $credFile;
            if ($paystackPublic !== '') $cred['payments']['paystack']['public'] = $paystackPublic;
            if ($paystackSecret !== '') $cred['payments']['paystack']['secret'] = $paystackSecret;
            if ($flutterwavePublic !== '') $cred['payments']['flutterwave']['public'] = $flutterwavePublic;
            if ($flutterwaveSecret !== '') $cred['payments']['flutterwave']['secret'] = $flutterwaveSecret;
            if ($flutterwaveHash !== '') $cred['payments']['flutterwave']['webhook_hash'] = $flutterwaveHash;
            file_put_contents($credFile, '<?php\nreturn ' . var_export($cred, true) . ';\n');
        }

        flash('success', 'Payment gateway settings saved.');
        redirect('admin/settings.php');
    }

    if ($section === 'mail') {
        $from = trim((string) post('mail_from', ''));
        $fromName = trim((string) post('mail_from_name', ''));
        $smtpHost = trim((string) post('smtp_host', ''));
        $smtpPort = max(1, (int) post('smtp_port', 587));
        $smtpUser = trim((string) post('smtp_user', ''));
        $smtpPass = trim((string) post('smtp_pass', ''));
        $smtpSecure = (string) post('smtp_secure', 'tls');

        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors['mail_from'] = 'Valid from email is required.';
        }
        if ($fromName === '') {
            $errors['mail_from_name'] = 'From name is required.';
        }

        if (!$errors) {
            $credFile = BASE_PATH . '/config/credentials.php';
            if (is_writable($credFile)) {
                $cred = require $credFile;
                $cred['mail']['from'] = $from;
                $cred['mail']['from_name'] = $fromName;
                $cred['mail']['smtp_host'] = $smtpHost;
                $cred['mail']['smtp_port'] = $smtpPort;
                $cred['mail']['smtp_user'] = $smtpUser;
                $cred['mail']['smtp_pass'] = $smtpPass;
                $cred['mail']['smtp_secure'] = $smtpSecure;
                file_put_contents($credFile, '<?php\nreturn ' . var_export($cred, true) . ';\n');
            }
            flash('success', 'Mail settings saved.');
            redirect('admin/settings.php');
        }
    }

    if ($section === 'uraio') {
        $uraioHost = trim((string) post('uraio_db_host', ''));
        $uraioName = trim((string) post('uraio_db_name', ''));
        $uraioUser = trim((string) post('uraio_db_user', ''));
        $uraioPass = trim((string) post('uraio_db_pass', ''));
        $uraioPort = max(1, (int) post('uraio_db_port', 3306));

        set_setting('uraio_db_host', $uraioHost, 'integration');
        set_setting('uraio_db_name', $uraioName, 'integration');
        set_setting('uraio_db_user', $uraioUser, 'integration');
        set_setting('uraio_db_pass', $uraioPass, 'integration');
        set_setting('uraio_db_port', (string) $uraioPort, 'integration');

        $credFile = BASE_PATH . '/config/credentials.php';
        if (is_writable($credFile)) {
            $cred = require $credFile;
            // Not typically stored in credentials for security
        }

        flash('success', 'Uraio integration settings saved.');
        redirect('admin/settings.php');
    }
}

$currentSettings = [
    'site_name' => setting('site_name', 'Uraio Business OS'),
    'support_email' => setting('support_email', 'support@uraio.com'),
    'default_currency' => setting('default_currency', 'NGN'),
    'default_country' => setting('default_country', 'Nigeria'),
    'registration_open' => (bool) setting('registration_open', true),
    'maintenance_mode' => (bool) setting('maintenance_mode', false),
    'ai_monthly_limit' => (int) setting('ai_monthly_limit', 20),
    'payments' => json_decode_assoc(setting('payment_gateways', '{}')),
    'mail' => [
        'from' => config('mail.from', 'no-reply@uraio.com'),
        'from_name' => config('mail.from_name', 'Uraio Business OS'),
        'smtp_host' => config('mail.smtp_host', ''),
        'smtp_port' => config('mail.smtp_port', 587),
        'smtp_user' => config('mail.smtp_user', ''),
        'smtp_pass' => config('mail.smtp_pass', ''),
        'smtp_secure' => config('mail.smtp_secure', 'tls'),
    ],
    'ai' => [
        'openai' => config('ai.openai', ['key' => '', 'model' => 'gpt-4o-mini']),
        'gemini' => config('ai.gemini', ['key' => '', 'model' => 'gemini-1.5-flash']),
        'groq' => config('ai.groq', ['key' => '', 'model' => 'llama-3.1-8b-instant']),
    ],
    'uraio' => [
        'db_host' => setting('uraio_db_host', ''),
        'db_name' => setting('uraio_db_name', ''),
        'db_user' => setting('uraio_db_user', ''),
        'db_pass' => setting('uraio_db_pass', ''),
        'db_port' => (int) setting('uraio_db_port', 3306),
    ],
];

$credFile = BASE_PATH . '/config/credentials.php';
$credWritable = is_writable($credFile);

admin_page_start($user, 'settings', 'System settings', 'Configure the platform');
?>
<div class="page-head">
  <div>
    <h1>System settings</h1>
    <p>Global configuration for Uraio Business OS.</p>
  </div>
</div>

<?php admin_flashes(); ?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?= icon('alert') ?><div><?= e(reset($errors)) ?></div></div>
<?php endif; ?>

<?php if (!$credWritable): ?>
  <div class="alert alert-warning"><?= icon('alert') ?><div><strong>config/credentials.php is not writable.</strong> Settings marked with <span class="badge badge-warning">*</span> will only persist in the database. Make the file writable to also update the credentials file for CLI scripts.</div></div>
<?php endif; ?>

<div class="grid grid-2-1">
  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">General</h2></div>
      <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="general">
        <div class="form-grid">
          <div class="field">
            <label for="site_name">Site name</label>
            <input type="text" id="site_name" name="site_name" value="<?= e($currentSettings['site_name']) ?>" required>
            <?= render_field_error($errors, 'site_name') ?>
          </div>
          <div class="field">
            <label for="support_email">Support email</label>
            <input type="email" id="support_email" name="support_email" value="<?= e($currentSettings['support_email']) ?>" required>
            <?= render_field_error($errors, 'support_email') ?>
          </div>
          <div class="field">
            <label for="default_currency">Default currency</label>
            <select id="default_currency" name="default_currency">
              <?php foreach (currency_options() as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= selected($currentSettings['default_currency'], $code) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= render_field_error($errors, 'default_currency') ?>
          </div>
          <div class="field">
            <label for="default_country">Default country</label>
            <select id="default_country" name="default_country">
              <?php foreach (country_options() as $c): ?>
                <option value="<?= e($c) ?>" <?= selected($currentSettings['default_country'], $c) ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
            <?= render_field_error($errors, 'default_country') ?>
          </div>
        </div>
        <div class="form-grid">
          <div class="field field-check">
            <input type="checkbox" id="registration_open" name="registration_open" value="1" <?= $currentSettings['registration_open'] ? 'checked' : '' ?>>
            <label for="registration_open">Allow public registration</label>
          </div>
          <div class="field field-check">
            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= $currentSettings['maintenance_mode'] ? 'checked' : '' ?>>
            <label for="maintenance_mode">Maintenance mode</label>
          </div>
        </div>
        <div class="field">
          <label for="app_key"><span class="badge badge-warning">*</span> Application key (encryption)</label>
          <input type="password" id="app_key" name="app_key" placeholder="Leave blank to keep current" autocomplete="new-password">
          <span class="field-help">32+ character key for encrypting stored secrets. Changing this will invalidate existing encrypted data.</span>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save general settings</button>
        </div>
      </form>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">AI integrations</h2></div>
      <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="ai">
        <div class="form-grid">
          <div class="field">
            <label for="ai_monthly_limit">Monthly AI credit limit per business</label>
            <input type="number" id="ai_monthly_limit" name="ai_monthly_limit" value="<?= e($currentSettings['ai_monthly_limit']) ?>" min="0" max="10000">
          </div>
        </div>
        <h3 style="margin:24px 0 12px">Providers <span class="badge badge-warning">*</span></h3>
        <div class="form-grid">
          <div class="field">
            <label for="openai_key">OpenAI API key</label>
            <input type="password" id="openai_key" name="openai_key" placeholder="<?= $credWritable ? 'Enter to update' : 'Stored in database only' ?>" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="gemini_key">Google Gemini API key</label>
            <input type="password" id="gemini_key" name="gemini_key" placeholder="<?= $credWritable ? 'Enter to update' : 'Stored in database only' ?>" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="groq_key">Groq API key</label>
            <input type="password" id="groq_key" name="groq_key" placeholder="<?= $credWritable ? 'Enter to update' : 'Stored in database only' ?>" autocomplete="new-password">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save AI settings</button>
        </div>
      </form>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Payment gateways <span class="badge badge-warning">*</span></h2></div>
      <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="payments">
        <h3>Paystack</h3>
        <div class="form-grid">
          <div class="field">
            <label for="paystack_public">Public key</label>
            <input type="text" id="paystack_public" name="paystack_public" value="<?= e($currentSettings['payments']['paystack']['public'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="paystack_secret">Secret key</label>
            <input type="password" id="paystack_secret" name="paystack_secret" placeholder="Enter to update" autocomplete="new-password">
          </div>
        </div>
        <h3 style="margin-top:24px">Flutterwave</h3>
        <div class="form-grid">
          <div class="field">
            <label for="flutterwave_public">Public key</label>
            <input type="text" id="flutterwave_public" name="flutterwave_public" value="<?= e($currentSettings['payments']['flutterwave']['public'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="flutterwave_secret">Secret key</label>
            <input type="password" id="flutterwave_secret" name="flutterwave_secret" placeholder="Enter to update" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="flutterwave_hash">Webhook hash</label>
            <input type="text" id="flutterwave_hash" name="flutterwave_hash" value="<?= e($currentSettings['payments']['flutterwave']['webhook_hash'] ?? '') ?>">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save payment settings</button>
        </div>
      </form>
    </div>
  </div>

  <div>
    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Email delivery <span class="badge badge-warning">*</span></h2></div>
      <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="mail">
        <div class="form-grid">
          <div class="field">
            <label for="mail_from">From email</label>
            <input type="email" id="mail_from" name="mail_from" value="<?= e($currentSettings['mail']['from']) ?>" required>
            <?= render_field_error($errors, 'mail_from') ?>
          </div>
          <div class="field">
            <label for="mail_from_name">From name</label>
            <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($currentSettings['mail']['from_name']) ?>" required>
            <?= render_field_error($errors, 'mail_from_name') ?>
          </div>
        </div>
        <h3 style="margin:24px 0 12px">SMTP (optional — uses PHP mail() if empty)</h3>
        <div class="form-grid">
          <div class="field">
            <label for="smtp_host">SMTP host</label>
            <input type="text" id="smtp_host" name="smtp_host" value="<?= e($currentSettings['mail']['smtp_host']) ?>">
          </div>
          <div class="field">
            <label for="smtp_port">Port</label>
            <input type="number" id="smtp_port" name="smtp_port" value="<?= e($currentSettings['mail']['smtp_port']) ?>" min="1" max="65535">
          </div>
          <div class="field">
            <label for="smtp_user">Username</label>
            <input type="text" id="smtp_user" name="smtp_user" value="<?= e($currentSettings['mail']['smtp_user']) ?>">
          </div>
          <div class="field">
            <label for="smtp_pass">Password</label>
            <input type="password" id="smtp_pass" name="smtp_pass" placeholder="Enter to update" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="smtp_secure">Security</label>
            <select id="smtp_secure" name="smtp_secure">
              <option value="tls" <?= selected($currentSettings['mail']['smtp_secure'], 'tls') ?>>TLS (STARTTLS)</option>
              <option value="ssl" <?= selected($currentSettings['mail']['smtp_secure'], 'ssl') ?>>SSL</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save mail settings</button>
        </div>
      </form>
    </div>

    <div class="card mb-2">
      <div class="card-head"><h2 class="mt-0">Uraio Smart Discovery integration</h2></div>
      <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="uraio">
        <div class="alert alert-warning"><?= icon('alert') ?><div>These credentials are used by the controlled read-only adapter. The adapter never writes to the discovery database.</div></div>
        <div class="form-grid">
          <div class="field">
            <label for="uraio_db_host">Database host</label>
            <input type="text" id="uraio_db_host" name="uraio_db_host" value="<?= e($currentSettings['uraio']['db_host']) ?>">
          </div>
          <div class="field">
            <label for="uraio_db_name">Database name</label>
            <input type="text" id="uraio_db_name" name="uraio_db_name" value="<?= e($currentSettings['uraio']['db_name']) ?>">
          </div>
          <div class="field">
            <label for="uraio_db_user">Username</label>
            <input type="text" id="uraio_db_user" name="uraio_db_user" value="<?= e($currentSettings['uraio']['db_user']) ?>">
          </div>
          <div class="field">
            <label for="uraio_db_pass">Password</label>
            <input type="password" id="uraio_db_pass" name="uraio_db_pass" placeholder="Enter to update" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="uraio_db_port">Port</label>
            <input type="number" id="uraio_db_port" name="uraio_db_port" value="<?= e($currentSettings['uraio']['db_port']) ?>" min="1" max="65535">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon('save', 'icon icon-sm') ?> Save Uraio settings</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><h2 class="mt-0">Email templates</h2></div>
      <div class="card-body">
        <p class="small muted">Manage notification email templates.</p>
        <a class="btn btn-primary btn-block" href="<?= e(app_url('admin/email-templates.php')) ?>"><?= icon('mail', 'icon icon-sm') ?> Edit email templates</a>
      </div>
    </div>
  </div>
</div>
<?php
admin_page_end(['scripts' => ['app.js']]);