# Deployment Guide

How to take Uraio Business OS from this repository to a staging or production host.

---

## 1. Requirements on the host

| Requirement | Notes |
| --- | --- |
| PHP 8.1+ | 8.3 recommended |
| Extensions | `pdo_mysql`, `openssl`, `curl`, `fileinfo`, `mbstring`, `json`, `gd` (gd optional but recommended) |
| MySQL 8 / MariaDB 10 | InnoDB, utf8mb4 |
| Apache | with `mod_rewrite`; `mod_headers` and `mod_expires` recommended |
| HTTPS | required for secure cookies, PWA install and payments |
| Writable dirs | `storage/`, `uploads/`, `logs/`, `config/` |

On cPanel, set the PHP version per directory via **MultiPHP Manager** and keep the host's PHP handler lines at the bottom of your root `.htaccess`. The repository does not ship handler lines so they never fight your host.

---

## 2. File layout

Recommended: deploy **into the public web root** (e.g. `public_html`) or a subdirectory (`public_html/ubos`).

If you deploy to a **subdirectory**, uncomment and set one line in the root `.htaccess`:

```apache
RewriteBase /ubos/
```

The app auto-detects its base path from `app.url` in `config/credentials.php`, so set that to your real public URL either way.

---

## 3. Deploy

### Option A — FTPS script (recommended)

```bash
cp deploy/ftps-config.staging.json.example deploy/ftps-config.staging.json
# edit host, port, user, pass, path, web_url
./deploy/deploy.sh staging --dry-run    # preview the file list
./deploy/deploy.sh staging              # upload
```

The script uses explicit TLS, excludes secrets/runtime/dev files, preserves `uploads/`, `logs/` and `storage/` on the server, and verifies SHA-256 of key files after upload.

`deploy/ftps-config.*.json` is git-ignored. Never commit it.

### Option B — cPanel File Manager

Zip the project (excluding `config/credentials.php`, `storage/`, `logs/`, `tests/`, `deploy/`, `.git/`), upload, and extract into the target directory. Then create `config/credentials.php` on the server as described below.

---

## 4. Configure the application

1. Copy `config/credentials.example.php` to `config/credentials.php` on the server and fill in real values, **or** run the installer once (see below).
2. Set at minimum:

```php
'url'  => 'https://create.uraio.com',   // real public URL, no trailing slash
'env'  => 'production',
'debug'=> false,                         // must be false in production
'key'  => '<64+ random characters>',
```

3. Generate the app key:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

---

## 5. Install or migrate the database

### Fresh install (browser)

Open `https://your-domain/install.php`, complete the wizard. It writes credentials, creates the database, applies the schema and seeds data, then writes `storage/installed.lock`.

After a successful install, **delete `install.php`** from the server. The root `.htaccess` also refuses to serve it once `storage/installed.lock` exists, but deleting it is best practice.

### Fresh install (CLI)

```bash
cp config/credentials.example.php config/credentials.php   # then edit
php config/seed.php admin@example.com 'StrongPass123' 'Administrator'
touch storage/installed.lock
chmod 640 storage/installed.lock
```

### Updating an existing install

Deploy the new files. The seeder is idempotent and the schema step is additive — run it once after deploying schema changes:

```bash
php config/seed.php
```

It never drops tables or columns and never overwrites business data. **Never** load `seed.sql`-style data over a live database.

---

## 6. Post-deploy checks

Run the preflight checker:

```bash
php tests/preflight.php
```

or open `https://your-domain/tests/preflight.php`. It checks PHP, extensions, writable paths, config sanity (URL, env, debug, app key), database connectivity and security files. **Fix all `[FAIL]` items.** Then delete `tests/` from the server.

Then verify by hand:

- [ ] Landing page loads over HTTPS.
- [ ] Register → login → onboarding completes.
- [ ] Choose a category, create a business, publish a website.
- [ ] Public site is reachable at `/{slug}` (legacy `/s/{slug}` also works), and on a verified custom domain once DNS is set.
- [ ] Admin login works and shows correct metrics.
- [ ] `robots.txt` and `sitemap.xml` resolve.
- [ ] PWA installs and opens in standalone mode.
- [ ] A test payment completes and activates the plan **only after** verification.

---

## 7. Webhooks and payments

Set these in the gateway dashboards:

- Paystack: `https://your-domain/api/webhooks/paystack.php`
- Flutterwave: `https://your-domain/api/webhooks/flutterwave.php`

Then add the keys and signing secret in **Admin → Settings → Payment gateways**. Signatures are verified; unsigned or mismatched calls are rejected.

---

## 8. Domains

1. Business owner adds the domain in **App → Domains** (requires a paid plan).
2. Add the TXT record `_uraio.<domain>` with the shown value.
3. Add `CNAME <domain> → sites.uraio.com` (and `www`).
4. Owner clicks **Verify DNS**.
5. Support issues SSL for the hostname; the app only routes verified domains.

---

## 9. Rollback

- **Files:** redeploy the previous commit, or restore from your host's backup / the pre-deploy copy.
- **Database:** restore the pre-deploy dump. Take one before every schema change:

```bash
mysqldump -h HOST -u USER -p DBNAME > backup-$(date +%F-%H%M).sql
```

Schema changes in this project are additive, so a code rollback rarely needs a data rollback.

---

## 10. Go-live checklist

- [ ] `app.env = production`, `app.debug = false`
- [ ] Real HTTPS URL in `app.url`; `site_domain` set correctly
- [ ] Strong, unique app key; database password not blank
- [ ] `install.php` and `tests/` removed from the server
- [ ] `storage/installed.lock` present and not web-readable
- [ ] Security headers present (check response headers)
- [ ] Payment webhooks configured and a test transaction passes
- [ ] Admin password changed from any seeded value
- [ ] Database backup scheduled
- [ ] Preflight shows no failures
