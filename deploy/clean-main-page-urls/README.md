# Clean main-page URLs

Local deployment package for clean public routes. The package mirrors the project tree under `app/` and `html/`.

Package folder: `D:\xampp\htdocs\shalimar-properties\deploy\clean-main-page-urls\`

## FTP source and destination

Upload/merge these package folders into `/home/propertys_user11/www/`:

| Local package file | Live destination |
| --- | --- |
| `app/Libraries/PublicWebsiteUrl.php` | `/home/propertys_user11/www/app/Libraries/PublicWebsiteUrl.php` |
| `app/Services/ProjectPageEditor.php` | `/home/propertys_user11/www/app/Services/ProjectPageEditor.php` |
| `html/.htaccess` | `/home/propertys_user11/www/html/.htaccess` |
| `html/assets/js/home.js` | `/home/propertys_user11/www/html/assets/js/home.js` |
| `html/blog.php` | `/home/propertys_user11/www/html/blog.php` |
| `html/events.php` | `/home/propertys_user11/www/html/events.php` |
| `html/includes/agencies-directory.php` | `/home/propertys_user11/www/html/includes/agencies-directory.php` |
| `html/includes/footer.php` | `/home/propertys_user11/www/html/includes/footer.php` |
| `html/includes/header.php` | `/home/propertys_user11/www/html/includes/header.php` |
| `html/includes/home/admin.php` | `/home/propertys_user11/www/html/includes/home/admin.php` |
| `html/includes/home/hero-d2.php` | `/home/propertys_user11/www/html/includes/home/hero-d2.php` |
| `html/includes/home/hero.php` | `/home/propertys_user11/www/html/includes/home/hero.php` |
| `html/includes/home/insights.php` | `/home/propertys_user11/www/html/includes/home/insights.php` |
| `html/includes/home/sections/accordion.php` | `/home/propertys_user11/www/html/includes/home/sections/accordion.php` |
| `html/includes/home/sections/chapters.php` | `/home/propertys_user11/www/html/includes/home/sections/chapters.php` |
| `html/includes/home/sections/cta_arch.php` | `/home/propertys_user11/www/html/includes/home/sections/cta_arch.php` |
| `html/includes/home/sections/cta_green.php` | `/home/propertys_user11/www/html/includes/home/sections/cta_green.php` |
| `html/includes/home/sections/featured.php` | `/home/propertys_user11/www/html/includes/home/sections/featured.php` |
| `html/includes/home/sections/market_band.php` | `/home/propertys_user11/www/html/includes/home/sections/market_band.php` |
| `html/includes/home/sections/market_bars.php` | `/home/propertys_user11/www/html/includes/home/sections/market_bars.php` |
| `html/includes/home/sections/market_min.php` | `/home/propertys_user11/www/html/includes/home/sections/market_min.php` |
| `html/includes/home/sections/rows.php` | `/home/propertys_user11/www/html/includes/home/sections/rows.php` |
| `html/includes/home/sections/signature.php` | `/home/propertys_user11/www/html/includes/home/sections/signature.php` |
| `html/includes/home/sections/spotlight.php` | `/home/propertys_user11/www/html/includes/home/sections/spotlight.php` |
| `html/includes/home/sections/tri.php` | `/home/propertys_user11/www/html/includes/home/sections/tri.php` |
| `html/includes/public-browse.php` | `/home/propertys_user11/www/html/includes/public-browse.php` |
| `html/includes/subprojects.php` | `/home/propertys_user11/www/html/includes/subprojects.php` |
| `html/insights.php` | `/home/propertys_user11/www/html/insights.php` |
| `html/listing.php` | `/home/propertys_user11/www/html/listing.php` |
| `html/project.php` | `/home/propertys_user11/www/html/project.php` |
| `html/projects.php` | `/home/propertys_user11/www/html/projects.php` |
| `html/sitemap.php` | `/home/propertys_user11/www/html/sitemap.php` |
| `html/wanted.php` | `/home/propertys_user11/www/html/wanted.php` |

## Routes

- `/home` maps to the existing `html/index.php` homepage.
- `/buy`, `/rent`, `/projects`, `/insights`, and `/agencies` map to their existing PHP pages.
- Existing `.php` URLs remain available. The rewrite rules do not redirect or disable them.
- No database migration or `.env` update is required.

The live `html/.htaccess` may contain hosting-specific rules. Merge the clean-route rules into the existing file rather than replacing unrelated hosting rules.
