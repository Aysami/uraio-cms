# Self-hosting Uraio

This guide gets Uraio running on your own computer or your own server. Total time:
about five minutes.

## Requirements

| Requirement | Notes |
|---|---|
| PHP 8.1+ | With `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `gd`, `json` |
| MySQL 8 / MariaDB 10.6+ | An empty database and a user with `CREATE DATABASE` rights |
| Apache + `mod_rewrite` | nginx also works (see [nginx](#nginx) below) |
| Writable directories | `config/`, `storage/`, `logs/`, `uploads/` |

Every one of these is checked for you on the install screen.

---

## 1. Install into a web server

1. Download **`uraio-v1.0.5.zip`** from the [latest release](https://github.com/Aysami/uraio-cms/releases/latest).
2. Unzip it. You will see an `uraio/` folder.
3. Upload the **contents** of that folder to your document root
   (`public_html`, `htdocs`, or wherever Apache serves from).
   - For a subdirectory install, upload it into `/your-domain.com/uraio/` instead.
4. Make these folders writable by the web server:
   `chmod 755 config storage logs uploads` (or `775` if your host uses a different
   PHP user). The installer will refuse to run otherwise and tells you which
   directory failed.
5. Open `https://your-domain.com/install.php` and fill in the form:
   - database host / port (usually `127.0.0.1:3306`), name, user, password;
   - **Application URL** — the exact public URL, no trailing slash;
   - the administrator account you want.
6. Click **Install Uraio**. The installer creates the database, applies the schema,
   seeds 259 categories / 13 modules / 5 templates, writes `config/credentials.php`
   with a fresh random `app.key`, locks itself and shows you the sign-in button.

That's it. Sign in, open **Admin → Setup** or your dashboard, create a business and
publish it.

## 2. Local install (MAMP on macOS)

```bash
brew install mamp # or download MAMP from mamp.info
```

1. Start MAMP. Put the unzipped `uraio/` folder into `MAMP/htdocs/uraio/`.
2. Note MAMP's MySQL port (usually **8889**) and reset credentials if needed
   (`root` / `root`).
3. Either use the browser installer (`http://localhost:8888/uraio/install.php`) or the
   CLI installer:
   ```bash
   mysql -h 127.0.0.1 -P 8889 -u root -proot \
     -e "CREATE DATABASE uraio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   cp config/credentials.example.php config/credentials.php
   # edit db.host/port/name/user/pass in credentials.php
   php config/seed.php you@example.com 'a-long-password' 'Full Name'
   ```
4. Open the site.

## 3. nginx

Apache + `.htaccess` works out of the box. On nginx, `.htaccess` is ignored, so add
the equivalent rules to the server block and tell Uraio about the subdirectory if any:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/uraio;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Public business sites at /{slug} and /s/{slug} (legacy)
    location ~ ^/s/([a-z0-9-]+)(/.*)?$ {
        try_files $uri $uri/ /site.php?__tenant=$1$2&$query_string;
    }

    location ~* \.(css|js|png|jpe?g|webp|svg|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public";
    }
}
```

The pretty `/{slug}` form relies on the rewrite reaching `site.php?__tenant=…`
**only when the path is not a real file or directory** (the `.htaccess` in the zip
does the same). Keep `/assets`, `/admin`, `/app`, `/auth`, `/connect` etc. unmatched.

## 4. Configuration

After installation, edit `config/credentials.php`. Every value is documented in
`config/credentials.example.php`. The most useful ones:

| Key | Example | Notes |
|---|---|---|
| `app.url` | `https://shop.example.com` | No trailing slash |
| `app.site_domain` | `example.com` | Used for `slug.example.com` public sites |
| `app.public_url_mode` | `path` | `path` = `/{slug}`, `path_s` = `/s/{slug}`, `subdomain` needs wildcard DNS |
| `mail.smtp_host/port/user/pass` | `smtp.example.com`, `587` | Set to send verification, password reset and notifications |
| `payments.paystack.*` | keys | Optional |
| `ai.openai.key` | `sk-…` | Optional |

## 5. Updating

1. Download the new release zip and unzip it over your installation.
2. Do **not** overwrite `config/credentials.php` — the release does not contain one.
3. Run `php config/seed.php` once after every update. It is idempotent and only adds
   missing tables/columns, so it is safe on a live site.
4. Clear the PWA cache in any logged-in browsers if you changed `sw.js`
   (a version bump does this automatically).

Take a database dump before every update: `mysqldump -u user -p uraio > uraio.sql`.

## 6. Backups

You need two things to recreate an installation:

- the **database**: `mysqldump -u user -p uraio > uraio.sql`
- the **uploads** folder: `tar -czf uploads.tgz uploads/`
- and `config/credentials.php` (it contains your secrets).

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| `install.php` shows "already installed" | `storage/installed.lock` exists. Remove it only if you are reinstalling. |
| **500** on every page | PHP error in `logs/`. Most often a PHP version < 8.1 or `pdo_mysql` missing. |
| Install button is disabled | One of the checks in the "Server requirements" panel failed — usually a writable directory. |
| Business sites at `/{slug}` return 404 | `mod_rewrite` off. Enable it (`a2enmod rewrite`) or use the nginx rules above. |
| `/uraio/{slug}` works but CSS is missing | Set `RewriteBase /uraio/` in `.htaccess` (or install at the domain root). |
| Upload fails | `uploads/` not writable or a host limit (`upload_max_filesize`, `post_max_size`). |
| Emails not arriving | Configure `mail.smtp_*` in `config/credentials.php`; until then mail is logged to `logs/`. |

## 8. FAQ

**Is this the same code as create.uraio.com?** Yes — same engine, published for
self-hosting. The hosted platform adds nothing you can't run yourself.

**Can I run this on shared hosting?** Yes. Upload, open `install.php`, done.

**Do I need the WordPress plugin?** Only if you want Uraio widgets on an existing
WordPress site. The CMS includes the full website builder already.
