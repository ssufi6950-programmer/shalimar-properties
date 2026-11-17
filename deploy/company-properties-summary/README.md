# Combined Company Properties summary

Admin: `/public/admin/company-properties`.

One combined SGC + Zalmi table with project, Head/Subhead names, actual size unit, type/nature, floor and subtype, current booking primary owner, ERP inventory/booking IDs, sector/block and inventory status. Search/source controls filter the loaded page. Separate per-source Next links keep remote pagination bounded (50 per source); page counts are not ERP-wide totals.

Only exact smp_active=yes records are fetched. Booking enrichment is restricted to these selected IDs and the configured company. Owner is shown only when exactly one unlocked booking exists and passes existing CurrentBookingRules. Missing booking shows No current booking; multiple or invalid candidates show Unconfirmed. This is the primary booking owner, not a co-owner list. Owner phones/CNIC/financial fields are never fetched. No local inventory import or publication occurs.

## Exact FTP paths

Back up destinations first. Replace the two ERP controller files before the two Portal files. The original Company Properties endpoints and routes must already be installed (previous package). Existing CurrentBookingRules, catalog configuration and property_unit conventions are reused. No new SQL, schema, credentials or environment edits.

| Full local package file | Full live destination |
| --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties-summary/portal/app/Controllers/Admin/CompanyProperties.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyProperties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties-summary/portal/app/Views/admin/company_properties.php` | `/home/propertys_user11/www/app/Views/admin/company_properties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties-summary/smp-api/src/CompanyPropertiesController.php` | `/home/sgc/www/html/smp-api/src/CompanyPropertiesController.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties-summary/zalmi-smp-api/src/CompanyPropertiesController.php` | `/home/zalmipk_user/www/html/smp-api/src/CompanyPropertiesController.php` |

## Verification / status

PHP lint passed for all four changed source files; JavaScript syntax passed. Eight mocked summary checks passed: catalog names, actual units, floor/subtype, current owner, ambiguous owner, absent owner, cancelled booking exclusion, selection/status preservation. Real ERP data and authenticated browser rendering were not verified. Local only; no live upload performed for this follow-up.
