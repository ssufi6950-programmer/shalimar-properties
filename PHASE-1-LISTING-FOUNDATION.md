# Phase 1 of 6 — listing foundation and ERP classification

Completed locally on 11 November 2026. Phase 2 has not been started. This phase adds Sale/Rent and separate review state, without a Rent selector, new approval route, new admin workflow or interface redesign. The latest task supersedes the earlier direct-publication requirement for **new** listings.

## Inspected schema and preservation

| Actual table | Relevant existing fields and relationships |
| --- | --- |
| `portal_listings` | `id`, `project_slug`, `mapping_id`, `portal_customer_id`, `erp_source_id`, `erp_property_id`, `status`, `deal`, JSON `payload`, `asking_price`, `selling_method`, agent/agency references, `submission_key`, `resale_verification_id`, `owner_verification`, `record_version`, dates. The existing generated `active_erp_property` has unique index `unique_active_erp_property`. |
| `portal_owned_properties` | Local owner snapshot keyed by ERP source/property; `owner_reference` and `portal_customer_id` associate verified ownership. It is separate from publication. |
| `resale_photos` | Private file metadata; `listing_id` and `portal_customer_id` foreign keys, submission/session binding, checksum, expiry. Payload image markers retain photo order. |
| `listing_events` | Listing foreign key, actor, event, previous/next record snapshots, one-use authorization reference and timestamp. |
| `resale_otp_challenges` | Protected draft/proof, customer/session binding, verification/consumption, `authorization_scope`, expiry and attempt limits. |
| ERP catalog/directory | Existing source, Head/Subhead, mapping, agent/agency and authorization tables are reused. No migration to those tables. |

The existing lifecycle fields still control live/pending/cancelled/unlisted/sold/deal states. Existing references, payloads and ownership/OTP/photo/history associations were preserved.

The local upgrade was preceded by a full private portal SQL backup at `writable/backups/before-listing-foundation-20261110-210713.sql`. A comparison of all original business columns across **31 tables** passed after migration; only the two new fields, generated slot and migration bookkeeping changed. The backup is private and is not included in the FTP package.

Local result: **43 listings**, **9 photo associations**, **9 lifecycle events**. Existing payload purposes yielded **35 Sale** and **8 existing Rent** records. Existing resale listings became Sale; existing Rent records were preserved as Rent. Original lifecycle counts are unchanged: 29 live/available, 2 live/rented, 4 live/sold, 4 cancelled/available, 4 pending/available. No Sold/cancelled record was reopened.

## Migration and canonical rules

Migration: `app/Database/Migrations/2026-11-11-000001_CreateListingPurposeReviewFoundation.php`.

| Field | Definition |
| --- | --- |
| `listing_purpose` | `ENUM('sale','rent')`, case-sensitive ASCII, not null, default `sale`. Canonical services reject any other input. Legacy `payload.purpose='resale'` remains a presentation value, not an authoritative purpose. |
| `review_state` | `ENUM('under_review','approved','rejected','needs_changes')`, case-sensitive ASCII, not null, default `under_review`. Independent of purpose and lifecycle. |
| `active_erp_property` | Existing stored generated column/index retained, with key `erp_source_id:erp_property_id:listing_purpose`. Active/pending lifecycle rows with review Under Review/Approved/Needs Changes reserve their purpose slot. Terminal/rejected history does not. |

Backfill captures the pre-migration maximum ID. Only existing IDs are grandfathered. Live/approved/active and Sold publications receive Approved review state; rejected records stay Rejected. Explicit historical publication events or a nonempty publication timestamp on a nonpending record also establish prior approval. Other pending/unknown records remain Under Review. Malformed historical JSON is not rewritten. All prior statuses and timestamps remain intact.

Public availability now requires **review Approved + valid purpose + active status/deal + the existing ERP source/project/mapping and exact Head/Subhead `'yes'` gates**. The same approval gate covers public catalog/detail lookup, seller contact, public photo delivery, Agency Portal active listings and public eligibility shown in Admin. Customer active counts and lifecycle actions also use review state.

After fresh current-owner OTP/final verification, a **new** submission is saved as `status='pending', review_state='under_review'`, with `customer_submitted` history and no publication timestamp. It stays private. Existing approved listings retain their protected Edit and customer-withdrawn Republish workflows; listing ID, purpose, ownership proof, fresh OTP, version and fingerprint checks remain authoritative. A republish cannot bypass a rejected/unpublished/terminal restriction.

Legacy Approve/Re-open routes remain blocked. The legacy writer cannot forge review approval or change an existing purpose. Existing public URLs remain unchanged. Minimal submit/status wording reflects the new requirement; this is not a UI redesign. No admin approval endpoint has been introduced, so new submissions intentionally wait for a later approval-workflow phase.

Concurrent duplicate prevention uses the existing transaction locks and the database unique generated slot. A Sale and Rent may coexist for one source/property. Two simultaneous same-purpose submissions cannot both commit. Plot number is never the identity. Rejected, sold, cancelled and other historical records remain stored.

## ERP constructed classification

The SGC Core PHP/schema was inspected. The user confirmed Zalmi live is a copy of SGC; only Zalmi's API repository is available locally, so its separate live Core PHP/database schema was not independently inspected.

| Actual ERP field | Meaning used |
| --- | --- |
| `m_property_maintain.property_id` | Joins `pro_inventory.id`, not booking ID. |
| `m_property_maintain.company_id` | Must also match inventory company. |
| `m_property_maintain.status` | Exact stored values `plot`, `constructed`, `under_constructed`. Only exact `constructed` maps to House. |
| `active`, `exclude` | Only exact `active='yes' AND exclude='no'` maintenance records are considered current. Multiple current rows are ambiguous. |
| Maintenance `property_type` | Building configuration (`single_story`, `double_story`, `tower`); not used as the constructed status. |

Both accessible API folders now expose additive fields on the existing authenticated POST endpoints `/v1/customer-owned-properties.php` and `/v1/verify-current-booking.php`: effective `property_type`, original `inventory_property_type`, nullable raw `maintenance_status`, and `classification_status`.

Verified constructed becomes House. Other actual inventory classifications such as Apartment, Shop, Tower, Office and Plot are preserved. Missing schema, missing rows, duplicate rows and unexpected values produce explicit unavailable/not_recorded/ambiguous/unknown metadata without inventing House or defaulting unknown properties to Plot. A missing base type becomes generic Property. Rent is not limited to constructed property.

The properties endpoint makes one prepared read-only classification batch query per existing page. Current-booking classification is added only after successful current-owner verification. No booking filters, bearer authentication or string `smp_active` rules were relaxed. APIs use their existing SELECT-only connection; the portal still accesses ERP data through authenticated APIs, never direct ERP MySQL. ERP business records were not modified.

## Exact application files

New portal files:

- `app/Database/Migrations/2026-11-11-000001_CreateListingPurposeReviewFoundation.php`
- `app/Services/ListingPolicy.php`
- `app/Services/ErpPropertyClassification.php`

Updated portal files:

- `app/Controllers/Customer/Resale.php`
- `app/Helpers/customer_helper.php`
- `app/Libraries/PortalCatalogStore.php`
- `app/Services/AdminPortalData.php`
- `app/Services/AgencyPortalListings.php`
- `app/Services/CustomerListingReadModel.php`
- `app/Services/CustomerPortfolio.php`
- `app/Services/LegacyListingPolicy.php`
- `app/Services/ListingLifecycle.php`
- `app/Services/PublicListingContact.php`
- `app/Services/ResaleOwnershipOtp.php`
- `app/Services/ResalePhotos.php`
- `app/Services/ResaleSubmission.php`
- `app/Views/customer/resale/live_create.php`
- `app/Views/customer/resale/live_index.php`
- `app/Views/customer/resale/public_preview.php`
- `app/Views/customer/resale/submitted.php`
- `public/assets/customer/js/resale-live.js`
- `public/assets/customer/js/resale-preview.js`

In **each** of `D:/xampp/htdocs/sgcacc/smp-api/` and `D:/xampp/htdocs/sgcacc/zalmi-smp-api/`:

- New `src/PropertyClassification.php`.
- Updated `index.php`, `src/CustomerController.php`, `src/CurrentBookingVerification.php`.

Contract/report: `CUSTOMER-ERP-API-CONTRACT.md` and this report. QA support added: `.qa/listing_foundation_checks.php`, `.qa/listing_foundation_race.php`, `.qa/apply_local_listing_foundation.php`. Existing fixtures/suites updated for complete migrations, explicit historically approved fixtures, the new review rule and classification cases: `.qa/admin_portal_fixtures.php`, `.qa/customer_auth_checks.php`, `.qa/resale_ownership_checks.php`, `.qa/verify_customer_apis.php`, `.qa/market_regression.py`.

**336 of the 359 baseline PHP files remain byte-identical**, including existing Market Intelligence, customer Auth/login OTP/SMS, source/catalog/mapping/directory/authentication/RBAC and admin controller files. Public/Admin design files and routes are unchanged. The two new customer JavaScript changes only retain canonical purpose in the existing wizard request and adjust submit wording.

## Verification

All automated mutation tests use disposable schemas on a separate loopback QA MariaDB instance, fake ERP/SMS providers or copies of the real API code against isolated ERP SQL fixtures. No real SMS was sent. The API suite checks that identity/property requests leave the fixture ERP business tables, including maintenance, unchanged.

| Executed suite | Passed checks |
| --- | ---: |
| `.qa/listing_foundation_checks.php` | 54 |
| `.qa/verify_customer_apis.php` | 221 |
| `.qa/resale_ownership_checks.php` | 95 |
| `.qa/admin_portal_data_checks.php` | 32 |
| `.qa/verify_customer_live.py` | 56 |
| `.qa/verify_phase1_safety.py` | 65 |
| `.qa/verify_agency_portal.py` | 153 |
| `.qa/verify_phase2_lifecycle.py` (existing lifecycle regression only) | 109 |
| `.qa/verify_erp_market.py` | 106 (38 service + 68 API/HTTP/browser) |
| `.qa/customer_auth_checks.php` | 71 |
| **Total** | **962** |

Checks cover backfill and original-column preservation, terminal history, purpose coexistence, actual concurrent insert races, review gates for public/media/contact/agency, immutable legacy purpose, both ERP classification endpoints and mysqli/PDO, absent schema, cross-company/excluded/duplicate/unexpected maintenance, pagination, all sixteen Head/Subhead yes/no/NULL/unexpected combinations, OTP ownership/session/final-proof binding, one-use authorization, preview-only isolation, generic agent/source-specific agency selection, existing secure edit/withdraw/republish, photos, legacy blocks, Admin Unpublish/audit rollback, login and ERP-based market reporting.

PHP syntax passed for 28 changed/new application/API PHP files; both changed JavaScript files passed `node --check`. The real local Apache public home, Customer login and Agency login returned HTTP 200 after migration. No real customer sign-in/SMS was performed for these smoke checks.

Logs: `.qa/listing-foundation-{checks,api,otp,admin,live,safety,agency,lifecycle,market,auth}.log`. The private local upgrade preservation report is `.qa/listing-foundation-local-upgrade.json`.

## Deployment and limitations

Upload package: `deploy/phase1-listing-foundation/`, with a checksummed manifest and exact FTP destinations in its README. It contains 22 portal application files, two additional bridge asset copies and four API files for each ERP. No `.env`, credentials, private backup, vendor or customer data is packaged.

1. Back up the **live portal database and matching application**. Pause listing writes for the migration and upload window; MySQL/MariaDB DDL implicitly commits.
2. Apply the new CI4 migration through the deployment migration runner (`php spark migrate` from the portal root), before serving the new application. **Uploading a migration file through FTP does not execute it.** FTP-only access needs the existing migration/deployment facility or a database operator; do not import the local portal database over live data.
3. Upload the matching portal files together, including both public asset locations used by the live HTML bridge. Retain existing `.env`, sessions, private photos and uploads.
4. Upload the four API files together into each server's existing `smp-api` installation. Zalmi's local folder name is `zalmi-smp-api`; its live API remains at its configured `/smp-api/v1/` URL.
5. Test actual authenticated ownership/classification responses on each live ERP and check a grandfathered public listing and a new private Under Review submission. Outstanding pre-upgrade property OTP drafts may need a fresh code because the sealed draft now includes purpose.

No live upload, database migration or API availability verification was performed. Database behavior was tested on MariaDB 10.4.32; live database version/DDL permissions must be checked by the deployment operator. The migration refuses an unrecorded partial/repeated schema upgrade instead of re-grandfathering new rows. Automatic downgrade is refused because it could collapse Sale/Rent slots or erase review decisions; rollback requires the verified backup and matching application during maintenance.

Phase 2 remains pending. This phase intentionally provides neither a new Rent creation screen nor an admin approval action; the existing Sale wizard now submits new listings for review. Existing approved listings continue through the preserved protected lifecycle.
