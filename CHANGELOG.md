# Changelog — Uraio (open-source)

All notable changes to the packaged, self-hostable distribution are recorded here.
This file follows [Keep a Changelog](https://keepachangelog.com/).

## [1.0.3] — 2026-11-04

### Fixed
- **Fresh installs 404:** the example `config/credentials.php` shipped a hard-coded `app.url`, so every unmatched route (including the redirect to `install.php`) pointed at a non-existent URL on your machine. The application URL now derives from the request (host and subfolder); the installer pre-fills it for you.
- `install.php` pre-fills the application URL from the detected request rather than from the example file.

## [1.0.2] — 2026-11-04

### Added
- First public release of **Uraio**, the self-hostable core of Uraio Business OS.
- **One-click installer** (`/install.php`): creates the database, writes `config/credentials.php`,
  applies the schema, seeds 259 categories / 13 modules / 5 templates, creates your administrator
  and locks itself.
- Website builder, category-aware modules, products, bookings, reviews, contact forms, PWA
  and Uraio Connect widgets, working out of the box after a fresh install.
- `docs/SELF-HOSTING.md` — server, subdirectory and local installs, nginx configuration,
  updates and troubleshooting.
- GPL-2.0-or-later license and a public GitHub repository with CI.

### Changed
- `config/credentials.example.php` now points at a local database by default.
- `deploy/` and internal project notes are not part of the distribution.

### Security
- `storage/` and `logs/` ship `.htaccess` deny rules; `uploads/` ships a rule that blocks
  execution of scripts and forbids directory indexes.
- No credentials are included in the distribution.
