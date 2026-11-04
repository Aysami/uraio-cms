<?php
/**
 * First-run installer. Creates the database, writes local credentials,
 * applies the schema and seeds the platform. Locks itself after success.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
require_once BASE_PATH . '/includes/bootstrap.php';

if (is_installed()) {
    layout_head('Already installed', ['noindex' => true, 'body_class' => 'auth-body']);
    ?>
    <div class="auth-card text-center">
      <span class="brand-mark brand-mark-lg" style="margin:0 auto 16px"><?= icon('check') ?></span>
      <h1>Uraio is installed</h1>
      <p class="auth-sub">The installer is locked. Remove <span class="mono">storage/installed.lock</span> only when you intentionally need to reinstall.</p>
      <a class="btn btn-primary btn-block" href="<?= e(app_url('index.php')) ?>">Open the platform</a>
      <a class="btn btn-ghost btn-block mt-2" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
    </div>
    <?php
    layout_end();
    exit;
}

$existingFile = BASE_PATH . '/config/credentials.php';
$existing = is_file($existingFile) ? require $existingFile : require BASE_PATH . '/config/credentials.example.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
$defaultUrl = $scheme . '://' . $host . $dir;

$values = [
    'db_host' => (string) ($existing['db']['host'] ?? '127.0.0.1'),
    'db_port' => (string) ($existing['db']['port'] ?? '3306'),
    'db_name' => (string) ($existing['db']['name'] ?? 'ubos'),
    'db_user' => (string) ($existing['db']['user'] ?? 'root'),
    'db_pass' => (string) ($existing['db']['pass'] ?? ''),
    'app_url' => (string) ($existing['app']['url'] ?? $defaultUrl),
    'site_name' => 'Uraio Business OS',
    'support_email' => 'support@uraio.com',
    'site_domain' => (string) ($existing['app']['site_domain'] ?? 'uraio.com'),
    'admin_name' => 'Uraio Administrator',
    'admin_email' => '',
];
// Leave the application URL empty: derive it from the request (host + subfolder).
$appUrl = (string) ($existing['app']['url'] ?? '');
$values['app_url'] = $appUrl !== '' ? $appUrl : $defaultUrl;
$errors = [];
$fatal = null;
$done = false;

if (is_post()) {
    verify_csrf();
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

    if ($values['db_host'] === '') $errors['db_host'] = 'Database host is required.';
    if ($values['db_port'] === '' || !ctype_digit($values['db_port'])) $errors['db_port'] = 'Port must be a number.';
    if ($values['db_name'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $values['db_name'])) $errors['db_name'] = 'Use letters, numbers and underscores only.';
    if ($values['db_user'] === '') $errors['db_user'] = 'Database user is required.';
    if ($values['app_url'] === '' || !filter_var($values['app_url'], FILTER_VALIDATE_URL)) $errors['app_url'] = 'Enter the full application URL.';
    if ($values['admin_name'] === '') $errors['admin_name'] = 'Administrator name is required.';
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) $errors['admin_email'] = 'Enter a valid administrator email.';
    $passwordErrors = password_errors($adminPassword);
    if ($passwordErrors) {
        $errors['admin_password'] = implode(' ', $passwordErrors);
    } elseif ($adminPassword !== $adminConfirm) {
        $errors['admin_password_confirm'] = 'Passwords do not match.';
    }

    $writable = [
        'config' => is_writable(BASE_PATH . '/config'),
        'storage' => is_writable(BASE_PATH . '/storage'),
        'uploads' => is_writable(BASE_PATH . '/uploads'),
        'logs' => is_writable(BASE_PATH . '/logs'),
    ];
    foreach ($writable as $dirName => $ok) {
        if (!$ok) {
            $errors['writable'] = 'The ' . $dirName . ' directory must be writable by the web server.';
        }
    }

    if (!$errors) {
        try {
            $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $values['db_host'], $values['db_port']);
            $server = new PDO($serverDsn, $values['db_user'], $values['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $dbName = $values['db_name'];
            $server->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $server = null;

            $appKey = bin2hex(random_bytes(32));
            $credentials = [
                'db' => [
                    'host' => $values['db_host'],
                    'port' => $values['db_port'],
                    'name' => $values['db_name'],
                    'user' => $values['db_user'],
                    'pass' => $values['db_pass'],
                ],
                'app' => [
                    'url' => rtrim($values['app_url'], '/'),
                    'env' => 'production',
                    'debug' => false,
                    'key' => $appKey,
                    'site_domain' => $values['site_domain'] !== '' ? $values['site_domain'] : 'uraio.com',
                    'support_email' => $values['support_email'] !== '' ? $values['support_email'] : 'support@uraio.com',
                ],
                'payments' => [
                    'paystack' => ['public' => '', 'secret' => ''],
                    'flutterwave' => ['public' => '', 'secret' => '', 'webhook_hash' => ''],
                ],
                'mail' => [
                    'from' => 'no-reply@' . ($values['site_domain'] !== '' ? $values['site_domain'] : 'uraio.com'),
                    'from_name' => $values['site_name'] !== '' ? $values['site_name'] : 'Uraio Business OS',
                    'smtp_host' => '',
                    'smtp_port' => 587,
                    'smtp_user' => '',
                    'smtp_pass' => '',
                    'smtp_secure' => 'tls',
                ],
                'ai' => [
                    'openai' => ['key' => '', 'model' => 'gpt-4o-mini'],
                    'gemini' => ['key' => '', 'model' => 'gemini-1.5-flash'],
                    'groq' => ['key' => '', 'model' => 'llama-3.1-8b-instant'],
                ],
            ];
            $export = "<?php\n/**\n * Local installation credentials. Generated by install.php.\n * Never commit this file. Restrict web access to /config.\n */\nreturn " . var_export($credentials, true) . ";\n";
            if (file_put_contents($existingFile, $export, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config/credentials.php. Check directory permissions.');
            }
            @chmod($existingFile, 0640);

            // The new credentials are read lazily by db(), so the rest of this
            // request runs against the freshly configured database.
            require_once BASE_PATH . '/config/seed.php';
            apply_schema();
            $report = seed_platform([
                'admin_email' => $values['admin_email'],
                'admin_password' => $adminPassword,
                'admin_name' => $values['admin_name'],
            ]);
            set_setting('site_name', $values['site_name'] !== '' ? $values['site_name'] : 'Uraio Business OS', 'general', true);
            set_setting('support_email', $values['support_email'] !== '' ? $values['support_email'] : 'support@uraio.com', 'general', true);

            if (!is_dir(BASE_PATH . '/storage')) {
                mkdir(BASE_PATH . '/storage', 0755, true);
            }
            file_put_contents(BASE_PATH . '/storage/installed.lock', json_encode([
                'installed_at' => date('c'),
                'version' => UBOS_VERSION,
                'admin' => $values['admin_email'],
            ], JSON_PRETTY_PRINT));
            @chmod(BASE_PATH . '/storage/installed.lock', 0640);

            activity_log('platform.installed', 'platform', null, ['version' => UBOS_VERSION]);
            $done = true;
        } catch (Throwable $e) {
            $fatal = $e->getMessage();
        }
    }
}

$requirements = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL driver' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'Mbstring' => extension_loaded('mbstring'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'GD (image tools)' => extension_loaded('gd'),
    'JSON' => extension_loaded('json'),
    'config/ writable' => is_writable(BASE_PATH . '/config'),
    'storage/ writable' => is_writable(BASE_PATH . '/storage'),
    'uploads/ writable' => is_writable(BASE_PATH . '/uploads'),
    'logs/ writable' => is_writable(BASE_PATH . '/logs'),
];
$requirementsOk = !in_array(false, $requirements, true);

layout_head('Install', ['noindex' => true, 'body_class' => 'auth-body', 'styles' => []]);
?>
<div class="auth-split" style="max-width:1060px">
  <aside class="auth-aside">
    <div>
      <a class="brand" href="<?= e(app_url('index.php')) ?>">
        <span class="brand-mark"><?= icon('sparkles') ?></span>
        <span>Uraio Business OS</span>
      </a>
      <h2>Your business, one operating system.</h2>
      <p>Install the platform, then create your account and publish your first business website.</p>
      <ul class="check-list mt-3">
        <li>One database, strict tenant isolation</li>
        <li>Category-aware dashboards and modules</li>
        <li>Website builder with reusable templates</li>
        <li>PWA-ready on phone and desktop</li>
      </ul>
    </div>
    <p class="small" style="color:rgba(255,255,255,.6)">Uraio Business OS v<?= e(UBOS_VERSION) ?></p>
  </aside>
  <div class="auth-form-side">
    <span class="brand">
      <span class="brand-mark brand-mark-sm"><?= icon('sparkles') ?></span>
      <span>Uraio</span>
    </span>

    <?php if ($done): ?>
      <h1>Installation complete</h1>
      <p class="auth-sub">The platform is ready. Sign in with the administrator account you just created.</p>
      <div class="alert alert-success"><?= icon('check') ?><div>Database seeded, installer locked and your administrator account created.</div></div>
      <a class="btn btn-primary btn-block btn-lg" href="<?= e(app_url('auth/login.php')) ?>">Go to sign in</a>
    <?php else: ?>
      <h1>Install Uraio</h1>
      <p class="auth-sub">Connect your database and create the first administrator. This takes about a minute.</p>

      <?php if ($fatal): ?>
        <div class="alert alert-danger"><?= icon('alert') ?><div><strong>Installation failed.</strong><br><?= e($fatal) ?></div></div>
      <?php endif; ?>
      <?php if (!empty($errors['writable'])): ?>
        <div class="alert alert-danger"><?= icon('alert') ?><div><?= e($errors['writable']) ?></div></div>
      <?php endif; ?>

      <details class="faq-item mb-2" <?= $requirementsOk ? '' : 'open' ?>>
        <summary>Server requirements</summary>
        <div style="padding:0 16px 14px">
          <?php foreach ($requirements as $label => $ok): ?>
            <div class="flex-between small" style="padding:4px 0">
              <span><?= e($label) ?></span>
              <span class="badge badge-<?= $ok ? 'success' : 'danger' ?>"><?= $ok ? 'OK' : 'Missing' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </details>

      <form method="post" action="<?= e(app_url('install.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field field-col-8">
            <label for="db_host">Database host</label>
            <input id="db_host" name="db_host" value="<?= e($values['db_host']) ?>" required>
            <?= render_field_error($errors, 'db_host') ?>
          </div>
          <div class="field field-col-4">
            <label for="db_port">Port</label>
            <input id="db_port" name="db_port" value="<?= e($values['db_port']) ?>" inputmode="numeric" required>
            <?= render_field_error($errors, 'db_port') ?>
          </div>
          <div class="field field-col-6">
            <label for="db_name">Database name</label>
            <input id="db_name" name="db_name" value="<?= e($values['db_name']) ?>" required>
            <?= render_field_error($errors, 'db_name') ?>
          </div>
          <div class="field field-col-6">
            <label for="db_user">Database user</label>
            <input id="db_user" name="db_user" value="<?= e($values['db_user']) ?>" required>
            <?= render_field_error($errors, 'db_user') ?>
          </div>
          <div class="field field-col-12">
            <label for="db_pass">Database password</label>
            <input id="db_pass" name="db_pass" type="password" value="<?= e($values['db_pass']) ?>" autocomplete="new-password">
          </div>
          <div class="field field-col-12">
            <label for="app_url">Application URL</label>
            <input id="app_url" name="app_url" type="url" value="<?= e($values['app_url']) ?>" required>
            <small class="field-help">No trailing slash. Example: https://app.uraio.com</small>
            <?= render_field_error($errors, 'app_url') ?>
          </div>
          <div class="field field-col-6">
            <label for="site_name">Platform name</label>
            <input id="site_name" name="site_name" value="<?= e($values['site_name']) ?>">
          </div>
          <div class="field field-col-6">
            <label for="site_domain">Business subdomain root</label>
            <input id="site_domain" name="site_domain" value="<?= e($values['site_domain']) ?>">
            <small class="field-help">Businesses publish at slug.this-domain.</small>
          </div>
          <div class="field field-col-12">
            <label for="support_email">Support email</label>
            <input id="support_email" name="support_email" type="email" value="<?= e($values['support_email']) ?>">
          </div>
        </div>

        <div class="divider"></div>
        <h3>Administrator account</h3>
        <div class="form-grid">
          <div class="field field-col-6">
            <label for="admin_name">Full name</label>
            <input id="admin_name" name="admin_name" value="<?= e($values['admin_name']) ?>" required>
            <?= render_field_error($errors, 'admin_name') ?>
          </div>
          <div class="field field-col-6">
            <label for="admin_email">Email</label>
            <input id="admin_email" name="admin_email" type="email" value="<?= e($values['admin_email']) ?>" required>
            <?= render_field_error($errors, 'admin_email') ?>
          </div>
          <div class="field field-col-6">
            <label for="admin_password">Password</label>
            <input id="admin_password" name="admin_password" type="password" minlength="8" required autocomplete="new-password">
            <?= render_field_error($errors, 'admin_password') ?>
          </div>
          <div class="field field-col-6">
            <label for="admin_password_confirm">Confirm password</label>
            <input id="admin_password_confirm" name="admin_password_confirm" type="password" minlength="8" required autocomplete="new-password">
            <?= render_field_error($errors, 'admin_password_confirm') ?>
          </div>
        </div>

        <button class="btn btn-primary btn-lg btn-block" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>
          <?= icon('download', 'icon icon-sm') ?> Install Uraio
        </button>
        <p class="small muted text-center mt-2">After installation, delete or protect <span class="mono">install.php</span> if you are on shared hosting.</p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php
layout_end();
