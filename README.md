# Shalimar Properties

Real estate website and CodeIgniter 4 customer/admin portal, with ERP Sources, property listings, Sale/Rent submissions, OTP verification, agency integration and market reporting.

## Requirements

- PHP 8.2 or later compatible with the locked Composer dependencies, with MySQLi, PDO MySQL, cURL, Intl, Mbstring and Fileinfo extensions.
- MySQL/MariaDB and Composer.
- Apache with rewrite support for the existing XAMPP/hosting directory layout.

## Fresh local setup

1. Clone the repository and run `composer install` in its root.
2. Copy `.env.example` to `.env`, then configure your own database, URLs and private integration settings. Existing installations must retain their original `.env`, including the customer identity key.
3. Copy `html/includes/config.example.php` to `html/includes/config.php`. Archived standalone demos under `html/samples` need their own corresponding `includes/config.php`, if used.
4. Create an empty local database and grant your configured database account access. For a **new local database only**, run `php spark migrate` and `php spark db:seed AdminSecuritySeeder`. Create the administrator using `php spark admin:create-superuser` and its interactive prompts. Follow module-specific documents for other seeders and integrations. Do not run these commands against production merely to deploy source files.
5. Ensure `writable` is writable by PHP. Existing private upload/session/cache data belongs outside Git. The public pages are under `html/`; the CodeIgniter entry point is `public/index.php`, with the existing bridge under `html/public/`.

With the existing XAMPP project layout and Apache on port 8081, the public website is `http://localhost:8081/shalimar-properties/html/index.php`. Configure `app.baseURL` and `app.publicSiteURL` for the actual deployment before using portal links.

Customer access is disabled in the example environment until a private identity key, ERP configuration and delivery settings are supplied. Do not enable OTP previews for real customer use. The SGC and Zalmi ERP applications are separate projects; this repository contains the Portal integration clients, not those external ERP installations.

## Repository contents

- `app/`: CodeIgniter controllers, services, views, configuration, migrations and seeders.
- `html/`: Public website, shared includes, static assets and archived design samples.
- `public/`: CodeIgniter entry point and portal assets.
- `database/sql/`: Reviewed SQL references; these are not automatic production imports.
- Root Markdown documents: module behavior, integration contracts and implementation notes. For current market counting rules, see [ERP-MARKET-TRANSFER-DATE.md](ERP-MARKET-TRANSFER-DATE.md).

Git excludes `.env`, local `includes/config.php` files, file-based runtime datasets, database backups, uploaded files, logs, sessions, caches, Composer dependencies, `.qa` test runtimes/artifacts, generated `deploy` bundles and obsolete `live-files` patch copies. These remain on the existing local machine; pushing Git does not deploy the live websites or copy their business databases. Generated deployment ZIPs referenced by historical documents must be obtained separately or rebuilt from the current source.
