# Company Properties single-table layout and shared Property Listings

Prepared locally on 18 November 2026. Uploaded live; see deployment verification below.

## What changed

- Company Rent form removes Security Deposit, Advance Rent and Lease Duration. Company-only server validation ignores these removed fields; monthly rent and furnishing remain. Customer rental forms/validation are unchanged. Existing saved records are not bulk-updated.
- Company Properties contains one inventory table. Exact source + property + purpose links show public Listed / Under Review / Not Public status. A single bounded local lookup covers loaded inventory; public status follows existing project/approval eligibility rather than a status string alone. Search/source/listed filters and page summary tiles are compact and scoped to the loaded page.
- Company listings now appear alongside customer listings in existing Admin > Property Listings (`/public/admin/listings`). The default tab is All. Approved offers appear in Approved too. Existing filters and server-side pagination are reused; unrelated NULL-customer legacy imports remain excluded.
- Company rows use company detail/management routes, while customer rows retain their normal review routes. No synthetic customer identity or ownership proof is created. Authorized readers can view company details without publish privileges; publishing still requires all existing Admin permissions. Unpublish remains versioned and CSRF protected. The company row uses Unpublish rather than routing to customer-only Delete.
- Saved company details remain readable if fresh inventory is unavailable; editing is disabled in that fallback. Publish still performs fresh ERP validation. After publish/unpublish, redirect to Property Listings. No separate Company Listings table remains on Company Properties.

## Exact FTP mappings

Back up the eight existing Portal destinations first. Merge the package into `/home/propertys_user11/www/` and replace only these files. Compare shared files against current live changes before replacement. No ERP upload is needed.

| Exact local package path | Exact live destination | FTP action |
| --- | --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Controllers/Admin/CompanyProperties.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyProperties.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Controllers/Admin/CompanyPropertyListings.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyPropertyListings.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Services/CompanyPropertyListing.php` | `/home/propertys_user11/www/app/Services/CompanyPropertyListing.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Services/AdminListingReviewReadModel.php` | `/home/propertys_user11/www/app/Services/AdminListingReviewReadModel.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Filters/AdminPermission.php` | `/home/propertys_user11/www/app/Filters/AdminPermission.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Views/admin/company_properties.php` | `/home/propertys_user11/www/app/Views/admin/company_properties.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Views/admin/company_listing_form.php` | `/home/propertys_user11/www/app/Views/admin/company_listing_form.php` | Replace after backup |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-listings-summary-cleanup/app/Views/admin/listing_review/index.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/index.php` | Replace after backup |

No SQL, migrations, .env edits or ERP API changes are required. No live data or previous listing records were changed during implementation. No listing or notification was created/sent.

## Verification

Eight changed PHP files passed lint. Both inline JavaScript blocks passed node --check. Twenty focused validation/permission/publication checks passed; six fixture render checks covered creation/edit/read-only forms, removed fields, a single inventory table, unlisted status and mixed company/customer listing routes. No authenticated browser or real database integration was performed for this follow-up.

The previous company-listing feature is already installed live; this is an incremental Portal-only update. Root report (documentation only; no FTP upload required): `D:/xampp/htdocs/shalimar-properties/COMPANY-PROPERTY-LISTINGS.md`.


Live upload completed on 18 November 2026 with explicit user authorization. Eight Portal files were backed up, compared, server PHP linted, uploaded and SHA-256 verified. Backup: `D:/xampp/htdocs/shalimar-properties/.qa/live-backups/company-listings-summary-cleanup-20261118-071237/`. Manifest: `.qa/company-listings-summary-cleanup-live-deployment.json`. Required schema/storage preflight passed. Live read-only public-listing, combined company/customer-listing and per-inventory listing-status SQL checks passed. Unauthenticated Company Properties and Property Listings URLs redirected to Admin login. No ERP files, SQL/migrations, listing records or notifications were changed/created by deployment. Authenticated visual/create/edit interactions remain unverified.
