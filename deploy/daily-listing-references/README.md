# Daily listing references ? new listings only

Prepared locally on 18 November 2026. Not deployed live.

## Approved behavior

New references are SP- + YYMMDD + the daily serial without zero padding: SP-2611181, SP-2611182; the next Pakistan calendar day starts SP-2611191. The serial is shared across new customer/company Sale/Rent listings and ERP sources. Customer references are allocated at submission; company references are allocated at creation/publication. Approval/edit/republication of the same record retains its existing saved reference. Existing SP-64-style references remain unchanged. No historical backfill or record update is included.

Numeric listing IDs, foreign keys, assignment identities, protected media IDs and URLs are unchanged. The saved payload.ref is displayed in Admin review/list/detail/delete, Company Properties status, customer lists/details/success and assignment SMS. Public cards, agency and dealer views already consume saved payload.ref.

## Allocation and consistency

ListingReference acquires a database-scoped MariaDB/MySQL advisory lock before the listing transaction, derives the next serial from the maximum already-saved matching reference for that date, and holds the lock through commit/rollback. Both current creation services use the same lock order. An uncommitted/failed creation does not consume a serial. Every current application deletion is retained, so its serial remains used. No schema changes or separate counter table are required. The host must support GET_LOCK/RELEASE_LOCK; a busy/unavailable lock fails allocation rather than falling back to ID-based references. Manual database writes or alternate code that bypasses these creation services cannot claim this locking guarantee.

## Exact FTP paths

Back up all listed Portal files first. Upload the new ListingReference.php service before the callers/views. Merge the package into /home/propertys_user11/www/ and replace only listed files. Compare shared files with live changes before upload. No ERP upload, SQL/migration, .env change or existing-reference conversion is required.

| Exact local package path | Exact live destination | FTP action |
| --- | --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Controllers/Admin/CompanyPropertyListings.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyPropertyListings.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Controllers/Admin/ListingReview.php` | `/home/propertys_user11/www/app/Controllers/Admin/ListingReview.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Services/AgentAssignmentSms.php` | `/home/propertys_user11/www/app/Services/AgentAssignmentSms.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Services/CompanyPropertyListing.php` | `/home/propertys_user11/www/app/Services/CompanyPropertyListing.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Services/ResaleSubmission.php` | `/home/propertys_user11/www/app/Services/ResaleSubmission.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/admin/company_properties.php` | `/home/propertys_user11/www/app/Views/admin/company_properties.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/admin/listing_review/delete.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/delete.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/admin/listing_review/detail.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/detail.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/admin/listing_review/index.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/index.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/customer/components/listing_actions.php` | `/home/propertys_user11/www/app/Views/customer/components/listing_actions.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/customer/resale/live_details.php` | `/home/propertys_user11/www/app/Views/customer/resale/live_details.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/customer/resale/live_index.php` | `/home/propertys_user11/www/app/Views/customer/resale/live_index.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Views/customer/resale/submitted.php` | `/home/propertys_user11/www/app/Views/customer/resale/submitted.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/daily-listing-references/app/Services/ListingReference.php` | `/home/propertys_user11/www/app/Services/ListingReference.php` | Replace after backup |

## Validation

All changed PHP sources passed lint. Eleven mocked reference checks cover first serial, lock contention protocol, same-day increment, next-day reset, suffix 10, rollback behavior and legacy display preservation. Existing six view fixtures and twenty company validation/permission/publication checks passed. These do not constitute a real concurrent database integration test. No live database/reference writes, real listing creation or messages were performed.

Root report (documentation only): D:/xampp/htdocs/shalimar-properties/LISTING-REFERENCES.md. No live upload performed for this task.
