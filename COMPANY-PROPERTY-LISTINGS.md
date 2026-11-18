# Company Properties ? Admin Sale/Resale and Rent listings

Implemented locally on 18 November 2026.

## Admin workflow

Open Admin > Company Properties (`/public/admin/company-properties`). Each selected inventory row has **Create Listing** for Admins with properties.view, resale_listings.view, resale_listings.edit and resale_listings.approve.

Choose For Sale / Resale or For Rent; enter asking price or monthly rent and optional rental terms, description, Negotiable and Shalimar Agent. Add photos and confirm authorization, then **Publish Company Listing**. Rent requires a fresh verified Constructed maintenance status in every project; there are no commercial exceptions. New listing creation is explicit; viewing inventory never creates or publishes anything.

Below the inventory, **Company Listings** shows the latest 100 records. Manage an active listing to edit its content/photos and choose its main image. Purpose is fixed after creation. **Unpublish** requires resale_listings.deactivate and immediately removes public visibility. Historical rows/files remain; inactive listings are read-only. New files use the existing random legacy image format, are decoded/re-encoded, obey configured upload limits and are served from html/uploads/listings. This directory must be writable. The first uploaded image is the main image; change it from Manage after initial publication.

## Data and authorization

No new table or column is introduced. Existing portal_listings has nullable portal_customer_id; company records deliberately leave it NULL. No fabricated customer account, OTP, current-owner proof or ownership-verification badge is created. This is an explicit manual Admin publication with a dedicated origin marker and actual Admin audit actor.

GET /company-properties.php now accepts optional property_id and still enforces authenticated company scope and exact BINARY smp_active=yes. Both APIs return optional construction classification and serialized allowlisted Explorer snapshot. Only the selected property's originating source is fetched. Fresh selection and mapping are checked at form display and again at submission. Missing classification blocks Rent. Existing Head/Subhead/project visibility policy, enabled sources, representative eligibility, admin-unpublish restrictions and per-source/property/purpose unique active listing rules are reused. An already active customer or company listing blocks another offer of the same purpose.

The save locks the listing/mapping/duplicate identity, validates record_version for updates, writes publication + listing event + Admin audit atomically, and cleans up new photo files on failure. Public ListingPolicy adds an exact-revision admin_company_published event requirement for this explicit company origin, preserving the existing customer approval gate. Removed/replaced images remain for history; no automatic deletion occurs.

Company listing management uses its dedicated page. It does not route NULL-customer records through the existing customer review/proof workflow. No customer-owned property snapshot is altered. No agent SMS/WhatsApp notification is added or sent by this company-listing implementation.

## Routes

- GET /admin/company-properties/listing?source={local ERP source ID}&property={exact ERP inventory ID}
- POST /admin/company-properties/listing
- GET /admin/company-properties/listings/{listing ID}
- POST /admin/company-properties/listings/{listing ID}/unpublish

Existing Admin authentication, explicit permissions, CSRF filters, short-lived session-bound form contexts, server-side ERP re-fetch and parameterized database queries protect these actions. No browser-direct ERP credentials are used.

## Verification and limitations

All 12 changed PHP sources linted successfully. Both inline JavaScript blocks passed node --check. 19 focused validation/permission/publication-gate checks, 10 mock ERP summary/exact-identity checks and 3 fixture view-render checks passed.

Local database access failed with connection error 2002. Actual target DDL, database save/rollback/duplicate races, filesystem/database integration, authenticated browser flow and real ERP integration remain UNVERIFIED. Schema compatibility is based on inspected repository migrations (nullable customer, existing unique active property/purpose and event/audit tables); no SQL or migration was executed. No real listing was created and no message was sent. Verify the complete save/publish workflow in a controlled environment before live use.

Deployment package: deploy/company-property-listings/README.md. Live deployment completed on 18 November 2026; see verification below.


Live deployment completed on 18 November 2026 with explicit user authorization. Two ERP controller files and ten Portal files were backed up, server PHP linted, uploaded and SHA-256 verified. Backup: `D:/xampp/htdocs/shalimar-properties/.qa/live-backups/company-property-listings-20261118-070213/`; manifest: `.qa/company-property-listings-live-deployment.json`. Live read-only preflight confirmed PHP 8.3.6, GD, writable legacy image directory, required columns, InnoDB tables, nullable customer, auto ID and unique generated source/property/purpose key. Public and company listing SQL smoke queries passed; both Admin URLs redirected unauthenticated requests to login. No listing was created or published during deployment. Actual authenticated create/edit/photo/unpublish interactions remain unverified.
