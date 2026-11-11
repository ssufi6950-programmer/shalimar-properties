> 11 November 2026 temporary workflow update: the user explicitly requested Admin image review and direct approval without owner-OTP proof or fresh ERP checks. [The manual approval package](deploy/live-admin-manual-approval/README.md) disables those Admin gates by default and hides Check Current Ownership. It preserves permissions, CSRF, photos/version locks and audit records; manual publication receives no ownership-verified badge. The private adminreview.requireOwnershipVerification=true setting restores strict review later. This supersedes the mandatory-ownership passages below while manual mode is active. No live approval was performed by this task.

# Phase 3 â€” Admin listing review and publication

Implemented locally on 11 November 2026. Phase 3 only. No production upload, production SQL, real SMS or live ERP approval test was performed.

## Result

The existing Admin Portal now has **Property Listings**, with Under Review, All, Approved, Rejected and Needs Changes queues. An authorized reviewer opens a customer submission, checks its Sale/Rent details and exact selected representative, manages the final private photo set, and chooses **Approve & Publish**, **Reject Listing**, or **Request Changes**. Approval makes the authoritative row genuinely `live / approved`; it is not a cosmetic badge change or a property sale/rental transaction.

The existing Phase 1 columns, purpose-specific unique key and Phase 2 owner-OTP submission are prerequisites. They were inspected before implementation. The original local database retains 43 listings, 5 saved ownership records, 3 customers, 5 ERP identity links, 9 photo associations and 9 listing events, with matching before/after hashes. No original record was approved or otherwise changed by this work.

## Screens and routes

All paths below are relative to the configured CI4 base URL (`/public/` on the current live deployment). Existing Admin authentication, navigation, permissions, sessions and CSRF filters are reused.

| Route | Method | Existing permission, in addition to `resale_listings.view` |
| --- | --- | --- |
| `admin/listings` | GET | View |
| `admin/listings/{id}/review` | GET | View |
| `admin/listings/{id}/photos/{photo-id}` | GET | View |
| `admin/listings/{id}/verify` | POST | `resale_listings.approve` |
| `admin/listings/{id}/approve` | POST | `resale_listings.approve` |
| `admin/listings/{id}/reject` | POST | `resale_listings.reject` |
| `admin/listings/{id}/changes` | POST | `resale_listings.reject` |
| `admin/listings/{id}/photos` | POST | `resale_listings.edit` |
| `admin/listings/{id}/photo-set` | POST | `resale_listings.edit` |

No new permission key, authentication system or role grant was added. The existing `customers.view` permission additionally gates internal customer account contact on the review screen. An agency/customer session confers no Admin access. All mutation routes retain CSRF; approval/photo-set/decision bodies require explicit confirmation where applicable.

The old `/admin/resale` records screen and modern Unpublish endpoint remain available. Its detail screen links to the new review screen. Unsafe Classic Approve/Reopen and the shared legacy catalog activation bypass remain blocked, including for Super Admin. No state-changing GET route was introduced.

The queue supports review state, purpose, source, search and bounded pagination. It distinguishes constructed House classification from For Sale/For Rent purpose. Twelve columns include authoritative property ID, project, source, type, purpose, price/monthly rent, customer, method, submission time, review state and Review/View Details. All widths retain real HTML tables; horizontal scrolling is contained inside table regions.

Detail shows property/booking IDs, Head/Subhead/project mapping and raw eligibility strings, size/floor/location when recorded, submitting customer and originating identity, selected business/owner public contact, Sale price or monthly rent and optional rental terms, description, feedback, final photos, version and sanitized review history. Missing optional facts say Not recorded. It never substitutes support contact for the seller, or renders the private current-booking OTP mobile/proof.

## Exact approval boundary

`AdminListingReview` performs these checks for every approval:

1. Recheck the active Admin's existing view/approve permissions. Require an exact current version and a fingerprint covering the listing, actual photo bytes/metadata and current selected public business contact.
2. Require an available Under Review pending record. Previously approved, Sold, rejected, cancelled/withdrawn or admin-unpublished records cannot be reopened here.
3. Validate the original consumed, owner-verified Phase 2 OTP challenge and its matching immutable customer submission event. Compare authoritative IDs, purpose, amount, method, contact assignments and material payload with that verified revision. Only explicitly permitted Admin photo/review/publication metadata can differ. A legacy writer cannot edit the description or contact and then reuse old customer OTP evidence.
4. Read the customer's currently verified originating ERP identity, re-fetch the currently owned property through the existing portfolio provider, and revalidate the existing current-booking API using the submitted authoritative property and booking IDs. A saved portal ownership row is only an identity pointer, never approval evidence.
5. Require the same current owner, source, booking, property, Head/Subhead and portal project. Both raw ERP `smp_active` strings must be exactly `yes`. Current property type/number must still agree with the submitted listing. API verification timestamp must pass the existing freshness contract.
6. Revalidate the exact representative and explicit public contact. The existing generic Shalimar Agent selection rule is preserved: SGC Agency **193** and configured generic dealer ledger **11770** are separate identities. The agent may handle SGC or Zalmi listings through existing rules; no ledger is written to either ERP. External agencies must match the listing's exact originating source, confirmed central mapping and existing eligibility policy.
7. Lock the listing and relevant local customer/link/catalog/representative records in an InnoDB transaction. Recheck version/fingerprint after the ERP call and after local locks. Recheck source/project/mapping eligibility, same-purpose uniqueness, current representative contact and final photo integrity. A delayed proof older than the local 15-second commit window requires a fresh attempt.
8. Atomically save `review_state=approved`, `status=live`, approved photo status, version increment, approval timestamp, publication timestamp where absent, reviewer/event metadata and existing Admin audit record. Preserve `deal`, source/ownership identifiers and earlier publication history.

**Check Current Ownership** is a separately audited fresh check. It never authorizes a later approval: Approve & Publish always repeats the authoritative check. No ERP record is modified, no SMS is sent by an Admin review action, and no automatic Sold/Rented state is created.

An unavailable/malformed ERP response, owner mismatch, inactive eligibility, changed contact/photo/content or stale review fails closed. The listing stays private and unchanged. Admin receives a clear error; safe failure codes are stored in Admin audit and displayed in review history. No API credentials, raw ERP response or OTP is logged.

The existing source-configured APIs are reused; these files were inspected in both downloaded API folders:

| Source base URL | Existing property lookup | Existing final booking proof |
| --- | --- | --- |
| `https://sgcacc.com/smp-api/v1` | `/customer-owned-properties.php` | `/verify-current-booking.php` |
| `https://zalmidevelopments.com/smp-api/v1` | `/customer-owned-properties.php` | `/verify-current-booking.php` |

The configured per-source paths and authenticated transport remain authoritative. No new ERP endpoint, authorization column or ERP write was introduced. Live connectivity was not exercised by these tests.

Listing locks, version/fingerprint checks and the existing purpose-aware unique key prevent conflicting decisions. Retrying the same committed decision with its original ticket is idempotent while the saved row is unchanged. A later different decision or edited revision returns a conflict. Audit/event storage failure rolls back publication. Publication does not depend on a persistent listing cache: existing catalog/contact/agency reads query the local database on each request; their request-local cache cannot carry a previous request's pending state.

## Photos

`AdminListingPhotos` adapts the existing `ResalePhotos` private storage. Admin can preview, upload, remove, reorder and make the first image the cover. Replacement uses remove/save followed by upload. There is no second upload directory or public temporary upload endpoint.

- Existing JPEG/PNG/WebP content, dimensions, file size, maximum six images, random private filenames, decode/re-encode and metadata validation are reused. The shared uploader now also rejects extensions outside JPG/JPEG/PNG/WebP, even when the bytes are an image. Allowed image files still have trailing executable content removed by re-encoding.
- Preview URLs require Admin view permission and bind the exact listing/photo association. Stored SHA-256, MIME, bytes and real paths are checked. Another listing's photo cannot be attached through an ID substitution.
- Saving order/removal or adding a photo increments the version and records an audited before/after event. Unsaved photo changes disable decision/upload controls until saved or reloaded.
- Missing/altered photos block publication until removed/replaced. The final saved photo set is checked again at approval. Public media requires Approved + Live + existing mapping/source/Head/Subhead eligibility, approved images status and membership in that exact listing's current photo set.
- Removal retains files, metadata and historical references. A failed concurrent upload remains unattached/private and expires through the existing staged-photo pruner.
- Existing legacy filename references can be privately reviewed and retained/reordered; this phase does not migrate old publicly hosted legacy static files. New uploads use private storage only. Removing an old static file from a listing does not erase its historical URL/file.

## Reject and Request Changes

Reject requires a reason and atomically saves `rejected / rejected`, a new version, reviewer identity/time, reason and event/audit history. The original submission/photos stay stored. It is not Sold and cannot be approved through the review endpoint afterward.

Request Changes requires a reason and saves `pending / needs_changes`, remaining private. The owner sees Needs Changes, escaped feedback and **Make Changes & Verify**. Existing secure editing is minimally extended for that state: only the owning customer may correct it, authoritative source/property/purpose bindings remain fixed, and material correction requires fresh originating-ERP owner OTP, sealed preview and final verification. The same listing returns to `pending / under_review` with `customer_changes_resubmitted` history. The previous Admin ticket is invalid. A customer cannot retain an earlier approval while changing material content.

## Public and Agency protections

The existing shared `ListingPolicy::publicSql()` remains the authority for Approved + Live/Active + available deal, combined with existing `ErpVisibility` local source/project/mapping/Head/Subhead gates. Homepage/search/detail/contact and Agency Active Listings reuse those services. There is no new public API, seller-contact bypass, sitemap writer or response cache in this phase. Direct pending/rejected/needs-changes detail URLs and private media remain unavailable.

Agency Active Listings remains read-only and source-specific, bound to the exact central agency and ERP link. Only its own eligible Approved/Live Sale and Rent assignments appear. Two small presentation updates label the purpose and monthly rent correctly; no Agency Portal redesign or new write permission was introduced. Private customer contact, owner proof and OTP mobile remain outside its whitelist.

Existing approved legacy Sales remain visible subject to their original visibility gates. Sold/withdrawn/history rows retain their states. Market Intelligence services and both ERP API folders are byte-identical to the pre-change baseline: official completed ERP resales remain the source of market statistics.

## Database and deployment

**No Phase 3 migration or modifying SQL is required.** Existing `listing_events`, `admin_audit_logs`, `record_version`, `resale_photos` and JSON payload fields store the new review metadata. Existing permissions are reused. Phase 1's Sale/Rent columns and generated unique index must already be correct, and Phase 2's challenge/photo/event tables must exist. Imported live migration history is not assumed to be synchronized.

The FTP package is `deploy/phase3-admin-listing-review`. Its README lists every absolute local â†’ live path. `LIVE-PREFLIGHT.sql` is read-only, for inspecting existing live columns/indexes/engines/permissions. It contains no INSERT, UPDATE, DELETE or ALTER. Review its output before replacing files; required transactional tables must use InnoDB. Back up the database, changed code and private photo storage first. Do not run `spark migrate --all`, reset/truncate tables, copy a local database or replace the live `.env`.

Both Admin assets are supplied in `public/assets/admin/...` and `html/public/assets/admin/...` for the existing FTP bridge/document root. Upload the package's matching folders into `/home/propertys_user11/www/` by merge/replace, keeping the existing root structure. Neither SGC `smp-api` nor `zalmi-smp-api` needs a Phase 3 upload.

Deploy during a quiet window; upload all PHP and both asset copies together, then refresh the browser. If rollback is needed, restore the backed-up files as a group. Review decisions already saved must remain intact; do not restore an old database dump or reopen rejected/Sold/history records to roll code back. A rollback to the pre-Phase 3 code removes the new review UI and correction action; pause new reviews until the complete code set is restored.

## Application file inventory

Created:

- `app/Controllers/Admin/ListingReview.php`
- `app/Services/AdminListingPhotos.php`
- `app/Services/AdminListingReview.php`
- `app/Services/AdminListingReviewReadModel.php`
- `app/Views/admin/listing_review/index.php`
- `app/Views/admin/listing_review/detail.php`
- `public/assets/admin/css/listing-review.css`
- `public/assets/admin/js/listing-review.js`
- `html/public/assets/admin/css/listing-review.css` (bridge mirror)
- `html/public/assets/admin/js/listing-review.js` (bridge mirror)

Modified:

- `app/Config/Routes.php`
- `app/Controllers/Customer/Resale.php`
- `app/Filters/AdminPermission.php`
- `app/Helpers/admin_helper.php`
- `app/Services/CustomerListingReadModel.php`
- `app/Services/LegacyListingPolicy.php`
- `app/Services/ListingLifecycle.php`
- `app/Services/ResalePhotos.php`
- `app/Services/ResaleSubmission.php`
- `app/Views/admin/records/details.php`
- `app/Views/agency/listings/index.php`
- `app/Views/agency/listings/show.php`
- `app/Views/customer/components/listing_actions.php`
- `app/Views/customer/resale/live_create.php`
- `app/Views/customer/resale/live_details.php`
- `app/Views/customer/resale/live_index.php`
- `html/includes/admin/navigation.php`

New QA support: `.qa/admin_review_fixture.php`, `.qa/admin_review_race.php`, `.qa/admin_review_extended.py`, `.qa/verify_admin_review.py`, `.qa/run_phase3_units.py`, `.qa/run_phase3_regressions.py`, `.qa/phase3_local_preservation.php`, `.qa/build_phase3_package.py`. Existing test dependencies add opt-in raw Head flag failures; the lifecycle polyglot-image regression now separately verifies rejected executable extensions and re-encoding of an allowed PNG. QA files, fake credentials/dependencies, screenshots and fixture databases are not deployment files.

## Verification

Final suite results are recorded in `deploy/phase3-admin-listing-review/TEST-RESULTS.md`. New Admin integration checks use actual CI4 HTTP, disposable SQL, isolated customer/Admin/Agency sessions, existing real OTP/preview handlers and injected ERP/SMS providers. Concurrent decisions use distinct Admin actors and separate simultaneous PHP/database connections. Browser checks use real Edge/Bootstrap handlers at 360, 390, 768, 1024 and 1440px, with private images, contained table scrolling, touch targets, confirmation cancellation and actual approval.

**1,797 automated checks passed across 14 completed suites.** This includes 413 new Admin integration checks; Sale/Rent browser 388; lifecycle/photo security 114; legacy/contact safety 65; Agency Portal 153; customer live/authenticated properties 56; ERP Market Intelligence 106; foundation 54; resale ownership OTP 76; customer authentication 71; Admin read models 32; Sale/Rent validation 40; request-local configuration 8; both actual ERP API copies against isolated SQL 221. Abandoned development runs are not counted. PHP syntax passed for 23 changed/new application PHP files; JavaScript syntax passed for both deployed copies. Screenshots under `.qa/phase3-review-{queue,detail}-{390,1440}.png` and `.qa/phase3-review-approve-confirm.png` contain disposable fixture data.

Final local read-only smoke checks returned HTTP 200 for the public homepage, customer sign-in and agency sign-in. The 27-file FTP manifest matches its source files; 73 protected ERP API/market/migration/auth/config files checked against the baseline remain unchanged.

Before/after evidence: `.qa/phase3-review-baseline.json`, `.qa/phase3-review-local-before.json`, `.qa/phase3-review-local-after.json`. New workflow log: `.qa/phase3-review-http.log`. Existing regressions: `.qa/phase3-regression-*.log`; service/API tests: `.qa/phase3-*.log`.

## Manual UAT after FTP upload

Use controlled test identities and eligible source-specific business contacts. Repeat Sale and Rent for both SGC and Zalmi; use Self Sell, Shalimar Agent and Authorized Agency across those cases.

1. Sign in as a customer with a currently owned eligible property. Submit through Phase 2 and verify the current owner OTP. Confirm Under Review in My Listings, public URL unavailable and no Agency Active Listing yet.
2. Open Admin â†’ Property Listings. Filter Under Review, Sale/Rent, SGC/Zalmi; search and paginate. Confirm authoritative property/booking IDs, House classification for an actual constructed property, correct project and Head/Subhead.
3. Open details with an authorized reviewer. Compare price/monthly rent, rental terms, description, selected representative and explicit public contact. Confirm private OTP mobile is absent.
4. Preview/upload/reorder/set cover/remove photos; reload and confirm the saved final set. Reject an unsafe/oversized image. Check new pending media URLs are unavailable outside authorized customer/Admin sessions.
5. Use Check Current Ownership. Then approve using explicit checkbox and confirmation. Confirm Live/Approved, exact content/photo set, correct public seller Call/WhatsApp/email and monthly-rent label. A repeated click must not create a second approval event.
6. For an Agency listing, sign in as that central agency and verify only its approved eligible assignments appear. Another agency, a customer and a read-only Admin must be unable to approve or upload review photos.
7. On separate test submissions, Reject with a reason and Request Changes with feedback. Confirm private status and owner feedback. Correct Needs Changes with fresh OTP; verify the same ID returns Under Review and the old review tab cannot approve it.
8. With a controlled API outage or test ownership/eligibility change, attempt approval. Confirm a clear failure and no publication, rejection or invented Sold/Rented status. Restore the test dependency and perform a fresh review.
9. Compare old approved Sales, Sold/withdrawn records, mappings, source CRUD and Market Intelligence. Verify desktop/mobile queue/details and confirmation dialogs on real devices.

## Boundaries and unverified live checks

- Live deployment, live ownership checks, production permissions/schema/engines, network/SMS delivery and live photo filesystem permissions still require the UAT above. No live approval was attempted.
- Local source/contact readiness is preserved. Previously unselectable/unapproved generic agent contacts and unmapped Zalmi agencies are not auto-enabled or created by this implementation. They must satisfy the existing directory/business rules; test fixtures supply valid examples without changing real records.
- The local C: drive was full during testing. Only the dedicated QA database's temporary storage was redirected to D:. No user files were deleted and the main XAMPP instance/configuration was left intact.
- Fresh ERP verification and a local SQL commit cannot form a distributed transaction with either external ERP. The implementation rechecks immediately, uses the existing short proof freshness contract and local locking; future periodic ownership monitoring remains separate work.
- No Phase 4 public redesign/search, Phase 5 terminology overhaul, ERP resale write system, automatic Sold/Rented detector or unrelated notification workflow was implemented.
