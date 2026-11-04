# Uraio

**Self-hosted website CMS and business operating system.** Build and publish a
website with menus, products, bookings, reviews, contact forms and a full PWA —
then manage everything from one admin. Runs on your laptop, your own server, or
any shared host with PHP and MySQL. It is the open-source core that powers
[create.uraio.com](https://create.uraio.com), published for anyone who wants to
host their own.

No framework. No Composer. No npm build. Plain PHP 8 + PDO + MySQL.

[![CI](https://github.com/Aysami/uraio/actions/workflows/ci.yml/badge.svg)](https://github.com/Aysami/uraio/actions/workflows/ci.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-7a86e8.svg)](https://www.php.net)

---

## What you get

- **Website builder** — reusable templates, six pages per business, live preview,
  custom domain support, SEO fields, sitemap and `robots.txt`.
- **Category-aware modules** — a restaurant gets a menu, a store gets products, a
  service business gets bookings, and so on. 259 categories are seeded.
- **Everything a small business needs in one box** — products & orders, bookings,
  reviews, opening hours, contact forms, WhatsApp, PWA manifest and service worker.
- **Multi-business by design** — one install can serve several businesses with strict
  tenant isolation, or you can use it for exactly one.
- **Admin area** — users, businesses, plans, payments, settings, audit log, email templates.
- **Uraio Connect** — embed live widgets on third-party sites (plain JS loader, iframes,
  or a [WordPress plugin](https://github.com/Aysami/uraio-connect-wordpress)).
- **One-click installer** — upload the zip, open `/install.php`, fill in the form, done.

---

## Requirements

| | |
|---|---|
| PHP | 8.1 or newer |
| Database | MySQL 8 (or MariaDB 10.6+) |
| Extensions | `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `gd`, `json` |
| Web server | Apache with `mod_rewrite` (or nginx — see [docs/SELF-HOSTING.md](docs/SELF-HOSTING.md)) |
| Writable | `config/`, `storage/`, `logs/`, `uploads/` |

---

## Quick start (on your own server)

1. Download **`uraio-v1.0.2.zip`** from the
   [latest release](https://github.com/Aysami/uraio/releases/latest).
2. Unzip it and upload the contents to your server's document root (or a subdirectory).
3. Visit **`https://your-domain.example/install.php`**.
4. Fill in the database details and create your administrator account.
5. Sign in and start building — full walkthrough in
   [docs/SELF-HOSTING.md](docs/SELF-HOSTING.md).

> Prefer no browser on a headless server? Use the CLI installer:
> `cp config/credentials.example.php config/credentials.php`, edit the three
> database values, then `php config/seed.php you@example.com 'a-long-password' 'Full Name'`.

## Quick start (local, macOS with MAMP)

```bash
git clone https://github.com/Aysami/uraio.git
cd uraio
cp config/credentials.example.php config/credentials.php
# MAMP's MySQL is on port 8889 with user root, password root (or as you set it).
mysql -h 127.0.0.1 -P 8889 -u root -proot -e "CREATE DATABASE uraio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php config/seed.php you@example.com 'a-long-password' 'Full Name'
# Point your web server at this folder, then open /install.php or run the app.
```

## Installing into a subfolder

Apache works automatically. If you install at `https://example.com/uraio/`, the
app is fully aware of the subfolder and serves business sites at
`/uraio/{slug}`. See [docs/SELF-HOSTING.md](docs/SELF-HOSTING.md) for the nginx
configuration and domain-wide installs.

---

## How it is built

```
includes/    Bootstrap, PDO helpers, auth, RBAC, tenant resolution, rendering engines
app/         The owner dashboard: setup, pages, products, orders, bookings, settings…
admin/       Platform administration
auth/        Register, login, verify, reset password
api/         Internal JSON API used by the dashboard
modules/     One folder per module (menu, products, bookings, reviews, …)
connect/     Uraio Connect widgets, loader and WordPress integration
config/      Schema (schema.sql), seed/migrations (seed.php), credentials example
site.php     Public site renderer (custom domain → subdomain → /{slug} → preview)
```

Key rules: prepared statements everywhere, strict tenant scoping on every query,
CSRF tokens on every form, Argon2id password hashing, output escaping, uploads
validated by MIME type and extension.

## Configuration

All runtime configuration lives in **`config/credentials.php`** (never committed).
Copy `config/credentials.example.php` to get a documented template.

| Key | Meaning |
|---|---|
| `db.host/port/name/user/pass` | MySQL connection |
| `app.url` | Public base URL of the installation |
| `app.site_domain` | Base for `{slug}.domain` public sites |
| `app.public_url_mode` | `path` (default, `/{slug}`), `path_s` (`/s/{slug}`) or `subdomain` |
| `mail.*` | SMTP for transactional email (optional) |
| `payments.*` | Paystack / Flutterwave keys (optional) |
| `ai.*` | OpenAI / Gemini / Groq keys (optional) |

## Updating

For minor updates: download the latest release zip, replace the files on your
server, then `php config/seed.php` once (it is idempotent and additive). The
installer is locked after installation so it cannot be re-run accidentally.

## Contributing

Issues and PRs welcome. Keep changes compatible with **PHP 8.1+**, no Composer,
no npm build step, and run the linter:
`find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0 | xargs -0 -n1 php -l`.

## License

[GPL-2.0-or-later](LICENSE). You may use it commercially, but derivative works must
remain open under the same license.

---

**Hosted version:** if you don't want to run your own server, the same software is
online at **[create.uraio.com](https://create.uraio.com)**.
