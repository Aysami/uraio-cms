<?php
/**
 * Copy this file to config/credentials.php and edit the values,
 * or use /install.php which generates it for you.
 *
 * config/credentials.php is ignored by Git and must never be committed.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'uraio',
        'user' => 'root',
        'pass' => '',
    ],
    'app' => [
        'url' => 'http://localhost:8888/uraio',   // exact public base URL, no trailing slash
        'env' => 'local',                        // local | staging | production
        'debug' => true,                          // false in production
        'key' => 'replace-with-a-64-character-random-string',
        'site_domain' => 'localhost',              // used for slug.<domain> public sites
        'public_url_mode' => 'path',              // path = /{slug} | path_s = /s/{slug} | subdomain needs wildcard DNS
        'support_email' => 'support@example.com',
    ],
    'payments' => [
        'paystack' => ['public' => '', 'secret' => ''],
        'flutterwave' => ['public' => '', 'secret' => '', 'webhook_hash' => ''],
    ],
    'mail' => [
        'from' => 'no-reply@example.com',
        'from_name' => 'Uraio',
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
