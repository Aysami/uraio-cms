<?php
/**
 * Idempotent platform seed. Safe to run repeatedly.
 * Usage: php config/seed.php [admin-email] [admin-password] [admin-name]
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require_once BASE_PATH . '/includes/bootstrap.php';

function seed_platform(array $options = []): array
{
    $report = ['roles' => 0, 'permissions' => 0, 'modules' => 0, 'templates' => 0, 'categories' => 0, 'plans' => 0, 'emails' => 0, 'admin' => null];

    $roles = [
        ['super_admin', 'Super Admin', 'Full platform access including settings and security.'],
        ['admin', 'Administrator', 'Platform administration without destructive system settings.'],
        ['support_agent', 'Support Agent', 'Customer support and business assistance.'],
        ['content_manager', 'Content Manager', 'Categories, templates and content management.'],
        ['business_owner', 'Business Owner', 'Owns and manages one or more businesses.'],
        ['business_manager', 'Business Manager', 'Manages an assigned business.'],
        ['staff', 'Staff', 'Limited access to assigned business modules.'],
    ];
    foreach ($roles as [$slug, $name, $description]) {
        $existing = db_row('SELECT id FROM roles WHERE slug = ?', [$slug]);
        if ($existing) {
            db_update('roles', ['name' => $name, 'description' => $description], 'id = :id', ['id' => $existing['id']]);
        } else {
            db_insert('roles', ['slug' => $slug, 'name' => $name, 'description' => $description, 'is_system' => 1]);
            $report['roles']++;
        }
    }

    $permissions = [
        ['manage_platform', 'Manage platform'], ['manage_users', 'Manage users'], ['manage_businesses', 'Manage businesses'],
        ['manage_categories', 'Manage categories'], ['manage_templates', 'Manage website templates'],
        ['manage_subscriptions', 'Manage subscriptions'], ['manage_payments', 'Manage payments'],
        ['view_reports', 'View platform reports'], ['manage_settings', 'Manage platform settings'],
        ['manage_own_business', 'Manage own business'], ['manage_staff', 'Manage staff'],
        ['manage_billing', 'Manage billing'], ['view_analytics', 'View analytics'],
        ['publish_website', 'Publish website'], ['manage_orders', 'Manage orders'],
        ['manage_customers', 'Manage customers'], ['manage_content', 'Manage content'],
    ];
    foreach ($permissions as [$slug, $name]) {
        if (!db_val('SELECT id FROM permissions WHERE slug = ?', [$slug])) {
            db_insert('permissions', ['slug' => $slug, 'name' => $name, 'module' => 'core']);
            $report['permissions']++;
        }
    }
    $rolePermissionMap = [
        'super_admin' => array_column($permissions, 0),
        'admin' => ['manage_users', 'manage_businesses', 'manage_categories', 'manage_templates', 'manage_subscriptions', 'manage_payments', 'view_reports', 'manage_content'],
        'support_agent' => ['manage_businesses', 'view_reports'],
        'content_manager' => ['manage_categories', 'manage_templates', 'manage_content'],
        'business_owner' => ['manage_own_business', 'manage_staff', 'manage_billing', 'view_analytics', 'publish_website', 'manage_orders', 'manage_customers'],
        'business_manager' => ['manage_own_business', 'manage_staff', 'view_analytics', 'publish_website', 'manage_orders', 'manage_customers'],
        'staff' => ['manage_orders'],
    ];
    foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
        $roleId = db_val('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
        if (!$roleId) {
            continue;
        }
        foreach ($permissionSlugs as $permissionSlug) {
            $permissionId = db_val('SELECT id FROM permissions WHERE slug = ?', [$permissionSlug]);
            if ($permissionId) {
                db_exec('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permissionId]);
            }
        }
    }

    foreach (module_registry() as $slug => $module) {
        $data = [
            'name' => $module['name'] ?? ucfirst($slug),
            'description' => $module['description'] ?? null,
            'icon' => $module['icon'] ?? 'grid',
            'category_group' => $module['category_group'] ?? null,
            'is_core' => $slug === 'universal' ? 1 : 0,
            'status' => 'active',
            'version' => $module['version'] ?? '1.0.0',
            'config' => json_encode(['nav' => $module['nav'] ?? []]),
        ];
        $existing = db_row('SELECT id FROM modules WHERE slug = ?', [$slug]);
        if ($existing) {
            db_update('modules', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['slug'] = $slug;
            db_insert('modules', $data);
            $report['modules']++;
        }
    }

    $layouts = template_layouts();
    $sort = 0;
    foreach ($layouts as $slug => $layout) {
        $data = [
            'name' => $layout['name'],
            'description' => $layout['description'],
            'category_group' => $layout['group'],
            'layout' => json_encode($layout),
            'is_premium' => in_array($slug, ['slate', 'market'], true) ? 1 : 0,
            'is_default' => $slug === 'aurora' ? 1 : 0,
            'status' => 'active',
            'sort_order' => $sort++,
        ];
        $existing = db_row('SELECT id FROM website_templates WHERE slug = ?', [$slug]);
        if ($existing) {
            db_update('website_templates', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['slug'] = $slug;
            db_insert('website_templates', $data);
            $report['templates']++;
        }
    }

    $categoryFile = BASE_PATH . '/config/categories.json';
    if (is_file($categoryFile)) {
        $import = import_categories_from_json($categoryFile);
        $report['categories'] = $import['created'] + $import['updated'];
    }

    $plans = [
        [
            'slug' => 'free', 'name' => 'Free', 'description' => 'Start with one business and a professional Uraio subdomain website.',
            'price_monthly' => 0, 'price_quarterly' => 0, 'price_annual' => 0,
            'limits' => ['businesses' => 1, 'staff' => 2, 'products' => 25, 'custom_domain' => 0, 'advanced_analytics' => 0, 'ai_credits' => 20, 'templates' => 2],
            'features' => ['One business', 'Uraio subdomain website', 'Basic dashboard', '2 website templates', '20 AI credits / month'],
            'is_popular' => 0, 'sort_order' => 0,
        ],
        [
            'slug' => 'pro', 'name' => 'Pro', 'description' => 'For growing businesses that need a custom domain and more tools.',
            'price_monthly' => 5000, 'price_quarterly' => 13500, 'price_annual' => 50000,
            'limits' => ['businesses' => 1, 'staff' => 10, 'products' => 500, 'custom_domain' => 1, 'advanced_analytics' => 1, 'ai_credits' => 150, 'templates' => 5],
            'features' => ['Custom domain', 'All website templates', 'Advanced analytics', '150 AI credits / month', '10 staff records'],
            'is_popular' => 1, 'sort_order' => 1,
        ],
        [
            'slug' => 'premium', 'name' => 'Premium', 'description' => 'Advanced modules, more customisation and higher limits.',
            'price_monthly' => 12000, 'price_quarterly' => 32400, 'price_annual' => 120000,
            'limits' => ['businesses' => 2, 'staff' => 30, 'products' => 5000, 'custom_domain' => 1, 'advanced_analytics' => 1, 'ai_credits' => 500, 'templates' => 5],
            'features' => ['2 businesses', 'Advanced modules', '500 AI credits / month', '30 staff records', 'Priority email support'],
            'is_popular' => 0, 'sort_order' => 2,
        ],
        [
            'slug' => 'business', 'name' => 'Business', 'description' => 'Multi-business teams, integrations and priority support.',
            'price_monthly' => 30000, 'price_quarterly' => 81000, 'price_annual' => 300000,
            'limits' => ['businesses' => 10, 'staff' => 200, 'products' => 50000, 'custom_domain' => 1, 'advanced_analytics' => 1, 'ai_credits' => 2000, 'templates' => 5],
            'features' => ['10 businesses', 'Team management', 'Advanced integrations', '2,000 AI credits / month', 'Priority support'],
            'is_popular' => 0, 'sort_order' => 3,
        ],
    ];
    foreach ($plans as $plan) {
        $existing = db_row('SELECT id FROM subscription_plans WHERE slug = ?', [$plan['slug']]);
        $data = [
            'name' => $plan['name'], 'description' => $plan['description'],
            'price_monthly' => $plan['price_monthly'], 'price_quarterly' => $plan['price_quarterly'], 'price_annual' => $plan['price_annual'],
            'currency' => 'NGN', 'limits' => json_encode($plan['limits']), 'features' => json_encode($plan['features']),
            'is_popular' => $plan['is_popular'], 'status' => 'active', 'sort_order' => $plan['sort_order'],
        ];
        if ($existing) {
            db_update('subscription_plans', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['slug'] = $plan['slug'];
            $planId = db_insert('subscription_plans', $data);
            foreach ($plan['limits'] as $key => $value) {
                db_insert('subscription_features', ['plan_id' => $planId, 'feature_key' => $key, 'limit_value' => is_numeric($value) ? (int) $value : null, 'feature_value' => (string) $value]);
            }
            $report['plans']++;
        }
    }

    $emailTemplates = [
        ['welcome', 'Welcome email', 'Welcome to Uraio Business OS, {{name}}', '<p>Hello {{name}},</p><p>Your Uraio Business OS account is ready. Choose your business category, add your details and publish your website.</p><p><a href="{{app_url}}/app/setup.php">Continue setup</a></p>'],
        ['verify_email', 'Verify email', 'Verify your Uraio email address', '<p>Hello {{name}},</p><p>Confirm your email address to secure your account.</p><p><a href="{{verify_url}}">Verify my email</a></p><p>This link expires in 48 hours.</p>'],
        ['password_reset', 'Password reset', 'Reset your Uraio password', '<p>Hello {{name}},</p><p>We received a request to reset your password.</p><p><a href="{{reset_url}}">Choose a new password</a></p><p>This link expires in 1 hour. If you did not request it, ignore this email.</p>'],
        ['website_published', 'Website published', 'Your website is live, {{business}}', '<p>Hello {{name}},</p><p>Your website for <strong>{{business}}</strong> is now published.</p><p><a href="{{website_url}}">View your website</a></p>'],
        ['subscription_activated', 'Subscription activated', 'Your {{plan}} plan is active', '<p>Hello {{name}},</p><p>Your <strong>{{plan}}</strong> plan for {{business}} is now active.</p>'],
        ['subscription_expiring', 'Subscription expiring', 'Your Uraio subscription expires soon', '<p>Hello {{name}},</p><p>Your subscription for {{business}} expires on {{expiry_date}}. Renew to keep your website and tools online.</p>'],
        ['payment_received', 'Payment received', 'Payment received — {{reference}}', '<p>Hello {{name}},</p><p>We received your payment of {{amount}} for {{business}}.</p><p>Reference: {{reference}}</p>'],
        ['booking_received', 'Booking received', 'New booking for {{business}}', '<p>Hello {{name}},</p><p>{{customer}} requested a booking for {{date}}.</p><p><a href="{{dashboard_url}}">Open the dashboard</a></p>'],
        ['new_order', 'New order', 'New order for {{business}}', '<p>Hello {{name}},</p><p>Order {{reference}} has been placed for {{total}}.</p>'],
        ['new_customer', 'New customer', 'New customer for {{business}}', '<p>Hello {{name}},</p><p>{{customer}} has been added as a customer.</p>'],
        ['domain_connected', 'Domain connected', 'Your domain is connected', '<p>Hello {{name}},</p><p>{{domain}} is now connected to your website. DNS and SSL may take a short time to propagate.</p>'],
    ];
    foreach ($emailTemplates as [$slug, $name, $subject, $body]) {
        $existing = db_row('SELECT id FROM email_templates WHERE slug = ?', [$slug]);
        $data = ['name' => $name, 'subject' => $subject, 'body' => $body, 'variables' => json_encode(['name', 'business', 'app_url']), 'status' => 'active'];
        if ($existing) {
            db_update('email_templates', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['slug'] = $slug;
            db_insert('email_templates', $data);
            $report['emails']++;
        }
    }

    $settings = [
        ['site_name', 'Uraio Business OS', 'string', 'general', 1],
        ['support_email', 'support@uraio.com', 'string', 'general', 1],
        ['default_currency', 'NGN', 'string', 'general', 1],
        ['default_country', 'Nigeria', 'string', 'general', 1],
        ['registration_open', '1', 'boolean', 'general', 0],
        ['maintenance_mode', '0', 'boolean', 'general', 0],
        ['ai_monthly_limit', '20', 'integer', 'ai', 0],
        ['uraio_db_host', '', 'string', 'integration', 0],
        ['uraio_db_name', '', 'string', 'integration', 0],
        ['uraio_db_user', '', 'string', 'integration', 0],
        ['uraio_db_pass', '', 'string', 'integration', 0],
        ['uraio_db_port', '3306', 'integer', 'integration', 0],
        ['payment_gateways', '{}', 'json', 'payments', 0],
    ];
    foreach ($settings as [$key, $value, $type, $group, $public]) {
        $existing = db_row('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if (!$existing) {
            db_insert('settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_type' => $type, 'setting_group' => $group, 'is_public' => $public]);
        }
    }

    $aiProviders = [
        ['openai', 'OpenAI', 'gpt-4o-mini', 'https://api.openai.com/v1', 1],
        ['gemini', 'Google Gemini', 'gemini-1.5-flash', 'https://generativelanguage.googleapis.com/v1beta', 2],
        ['groq', 'Groq', 'llama-3.1-8b-instant', 'https://api.groq.com/openai/v1', 3],
    ];
    foreach ($aiProviders as [$slug, $name, $model, $base, $priority]) {
        if (!db_val('SELECT id FROM ai_providers WHERE slug = ?', [$slug])) {
            db_insert('ai_providers', ['slug' => $slug, 'name' => $name, 'model' => $model, 'base_url' => $base, 'status' => 'inactive', 'priority' => $priority]);
        }
    }

    if (!empty($options['admin_email']) && !empty($options['admin_password'])) {
        $existing = db_row('SELECT id FROM users WHERE email = ?', [strtolower($options['admin_email'])]);
        if (!$existing) {
            $roleId = db_val("SELECT id FROM roles WHERE slug = 'super_admin'");
            $userId = db_insert('users', [
                'uuid' => uuid(),
                'role_id' => $roleId,
                'name' => $options['admin_name'] ?? 'Uraio Administrator',
                'email' => strtolower($options['admin_email']),
                'password_hash' => password_hash_secure($options['admin_password']),
                'is_super_admin' => 1,
                'status' => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
            $report['admin'] = $userId;
        }
    }

    return $report;
}

function apply_schema(): void
{
    $sql = file_get_contents(BASE_PATH . '/config/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql could not be read.');
    }
    $statements = preg_split('/;\s*\n/', $sql) ?: [];
    $pdo = db();
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }
    apply_column_migrations();
}

/**
 * Additive, idempotent column migrations for installations created before a
 * column was introduced. Never drops or rewrites existing data.
 */
function apply_column_migrations(): void
{
    $migrations = [
        'modules' => [
            'sort_order' => "ALTER TABLE `modules` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `status`",
        ],
        'businesses' => [
            'embed_token' => "ALTER TABLE `businesses` ADD COLUMN `embed_token` VARCHAR(64) NULL UNIQUE AFTER `published_at`",
            'embed_origins' => "ALTER TABLE `businesses` ADD COLUMN `embed_origins` TEXT NULL AFTER `embed_token`",
            'embed_enabled' => "ALTER TABLE `businesses` ADD COLUMN `embed_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `embed_origins`",
        ],
        'messages' => [
            'source' => "ALTER TABLE `messages` ADD COLUMN `source` VARCHAR(32) NOT NULL DEFAULT 'website' AFTER `status`",
        ],
    ];
    foreach ($migrations as $table => $columns) {
        foreach ($columns as $column => $ddl) {
            $exists = db_val(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
            if ((int) $exists === 0) {
                db_exec($ddl);
            }
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $options = [];
    if (isset($argv[1], $argv[2])) {
        $options = ['admin_email' => $argv[1], 'admin_password' => $argv[2], 'admin_name' => $argv[3] ?? 'Uraio Administrator'];
    }
    try {
        apply_schema();
        $report = seed_platform($options);
        echo "Seed complete: " . json_encode($report) . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
