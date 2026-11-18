# SEO-friendly public URLs

Status: **Prepared locally; not uploaded to live.**

This change adds readable aliases to the existing public project and property pages:

- Project: https://shalimarproperties.com/project/zalmi-x
- Property: https://shalimarproperties.com/property/SP-2611181-gulberg-city-304-apartment

The property reference is the lookup key; the descriptive slug is only for readability. Existing links such as project.php?project=zalmi-x and listing.php?id=62 continue to work. They are not redirected, and their canonical tag points to the new URL. The sitemap now lists public project and listing SEO URLs.

## Files and live destinations

Upload the mirrored files in this package to the matching live paths:

| Local source | Live destination |
| --- | --- |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\app\Libraries\PublicWebsiteUrl.php | /home/propertys_user11/www/app/Libraries/PublicWebsiteUrl.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\app\Services\PublicListingSearch.php | /home/propertys_user11/www/app/Services/PublicListingSearch.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\app\Libraries\PortalCatalogStore.php | /home/propertys_user11/www/app/Libraries/PortalCatalogStore.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\.htaccess | /home/propertys_user11/www/html/.htaccess |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\includes\bootstrap.php | /home/propertys_user11/www/html/includes/bootstrap.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\includes\header.php | /home/propertys_user11/www/html/includes/header.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\includes\home\hero-b.php | /home/propertys_user11/www/html/includes/home/hero-b.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\insights.php | /home/propertys_user11/www/html/insights.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\listing.php | /home/propertys_user11/www/html/listing.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\project.php | /home/propertys_user11/www/html/project.php |
| D:\xampp\htdocs\shalimar-properties\deploy\seo-friendly-urls\html\sitemap.php | /home/propertys_user11/www/html/sitemap.php |

## FTP steps

1. Back up each existing destination file before replacing it.
2. If /home/propertys_user11/www/html/.htaccess already exists, download a backup and merge only the two SEO RewriteRule lines from the package into the existing file. Preserve its other hosting/security rules. If it does not exist, upload the package .htaccess.
3. Upload/replace the remaining files at the exact destinations above, preserving the folder structure.
4. Check /project/zalmi-x, a generated /property/SP-...-... link, the old project.php?project=zalmi-x and listing.php?id=62, and /sitemap.php.

No SQL, .env, migration, or ERP change is required. Apache mod_rewrite and .htaccess overrides must be enabled for the aliases; the original PHP URLs remain usable if rewriting is unavailable.
