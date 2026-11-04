<?php
/**
 * Configuration loader. Reads config/credentials.php once and exposes
 * dot-notation access through config('group.key', $default).
 */

function credentials(): array
{
    static $credentials = null;
    if ($credentials !== null) {
        return $credentials;
    }
    $file = BASE_PATH . '/config/credentials.php';
    if (!is_file($file)) {
        $file = BASE_PATH . '/config/credentials.example.php';
    }
    $credentials = require $file;
    return $credentials;
}

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $value = credentials();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $cache[$key] = $value;
}

function is_installed(): bool
{
    return is_file(BASE_PATH . '/storage/installed.lock');
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base = $scheme . '://' . $host . $dir;
    }
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function site_domain(): string
{
    return (string) config('app.site_domain', 'uraio.com');
}

/**
 * How public websites are addressed:
 *  - "path":      https://<host>/{slug}         — default, always works, no DNS needed
 *  - "path_s":    https://<host>/s/{slug}       — legacy path form, still supported
 *  - "subdomain": https://{slug}.<site_domain>  — needs a wildcard DNS record
 *  - "auto":      resolves to "path"
 */
function public_url_mode(): string
{
    $mode = strtolower(trim((string) config('app.public_url_mode', 'auto')));
    if (in_array($mode, ['path', 'path_s', 'subdomain'], true)) {
        return $mode;
    }
    return 'path';
}
