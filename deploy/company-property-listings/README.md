# Company property listing option ? exact deployment paths

Admin page: https://shalimarproperties.com/public/admin/company-properties

Package prepared locally on 18 November 2026. **Uploaded live on 18 November 2026; see deployment verification below.** This extends the already-installed Company Properties API/page.

## FTP upload

Back up every destination first. Replace the two ERP controller files first, then merge the package's portal folder into /home/propertys_user11/www/. Replace only these 12 files. Do not replace private API configuration or .env. Shared route/policy files should be compared with current live versions before replacement if another developer has changed them.

| Exact local package path | Exact live destination |
| --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Config/Routes.php` | `/home/propertys_user11/www/app/Config/Routes.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Filters/AdminPermission.php` | `/home/propertys_user11/www/app/Filters/AdminPermission.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Controllers/Admin/CompanyProperties.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyProperties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Controllers/Admin/CompanyPropertyListings.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyPropertyListings.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Services/ExplorerErpClient.php` | `/home/propertys_user11/www/app/Services/ExplorerErpClient.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Services/CompanyPropertyListing.php` | `/home/propertys_user11/www/app/Services/CompanyPropertyListing.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Services/CompanyPropertyImages.php` | `/home/propertys_user11/www/app/Services/CompanyPropertyImages.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Services/ListingPolicy.php` | `/home/propertys_user11/www/app/Services/ListingPolicy.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Views/admin/company_properties.php` | `/home/propertys_user11/www/app/Views/admin/company_properties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/portal/app/Views/admin/company_listing_form.php` | `/home/propertys_user11/www/app/Views/admin/company_listing_form.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/smp-api/src/CompanyPropertiesController.php` | `/home/sgc/www/html/smp-api/src/CompanyPropertiesController.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-property-listings/zalmi-smp-api/src/CompanyPropertiesController.php` | `/home/zalmipk_user/www/html/smp-api/src/CompanyPropertiesController.php` |

## Behavior and requirements

Company Properties > Create Listing > Sale/Resale or Rent > price/rent + description + agent + optional photos > Publish. Existing company offers are managed from Company Listings on the same page. Only authorized Admins can publish; Rent requires fresh verified Constructed status without exceptions. Only exact smp_active=yes properties from the authenticated ERP company can be selected. Existing public project/mapping rules and duplicate prevention remain enforced.

No SQL/migration or environment edit is required by the source design. This uses existing portal_listings, listing_events and admin_audit_logs; nullable portal_customer_id is required and existing source/property/purpose uniqueness must be present. If target schema differs from current repository migrations, stop and verify it; do not run migrations automatically.

Ensure /home/propertys_user11/www/html/uploads/listings/ is writable by PHP. Photos are decoded/re-encoded within existing count/size limits. Existing uploaded files are not part of this package. Existing Company Properties API bootstrap/routes, PropertyClassification, CurrentBookingRules and InventoryFieldDiscovery must already be installed (they are reused, not modified in this package).

Company offers have no customer login/OTP identity. They are manually authorized by the Admin; public visibility is bound to the exact audited revision. No ownership verification badge is fabricated. No assignment SMS/WhatsApp is implemented in this flow.

## Checks and remaining verification

12 PHP lint checks and 2 JavaScript syntax checks passed. 19 focused logic/permission checks + 10 mock ERP checks + 3 fixture form-render checks passed. Local DB is unavailable (connection error 2002), so actual target DDL, transaction save/rollback/races, authenticated browser and ERP integration remain unverified. No listing/account was created, no SQL/migration was executed and no message was sent.

Report (local documentation, no FTP upload required): D:/xampp/htdocs/shalimar-properties/COMPANY-PROPERTY-LISTINGS.md.

After deployment, verify permissions/CSRF, real source mappings, fresh selection, photo storage, Sale/Rent duplicate handling, Constructed-only Rent, exact public revision, main image and Unpublish in a controlled test. Do not claim these live checks have already passed.


Live deployment completed on 18 November 2026 with explicit user authorization. Two ERP controller files and ten Portal files were backed up, server PHP linted, uploaded and SHA-256 verified. Backup: `D:/xampp/htdocs/shalimar-properties/.qa/live-backups/company-property-listings-20261118-070213/`; manifest: `.qa/company-property-listings-live-deployment.json`. Live read-only preflight confirmed PHP 8.3.6, GD, writable legacy image directory, required columns, InnoDB tables, nullable customer, auto ID and unique generated source/property/purpose key. Public and company listing SQL smoke queries passed; both Admin URLs redirected unauthenticated requests to login. No listing was created or published during deployment. Actual authenticated create/edit/photo/unpublish interactions remain unverified.
