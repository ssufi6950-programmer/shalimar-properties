# Phase 2 — listing lifecycle and resale form features

Implemented locally on 6 November 2026. This phase adds customer listing management, secure edits/reactivation, private photo uploads and Negotiable pricing to the existing resale workflow. The existing UI, customer login, ERP identity linking, My Properties, direct publication, agency/agent selection, Admin Unpublish and Phase 1 protections are retained.

No ERP database, ERP business logic, API folder, credential or SMS recipient setting was changed. No live deployment or real SMS/API verification was performed. The additive portal migration has been applied locally.

## Completed behavior

| Feature | Result |
| --- | --- |
| My Resale Listings | Customer-owned records only, with main photo, source, project/property, price, Negotiable, selling method, representative, publication date and status-dependent actions. Individual details include a safe history timeline. |
| Edit Listing | Reuses the existing four-step wizard with saved fields/photos. Price, Negotiable, description, photo selection/order, selling method and eligible representative/public contact can change. New property OTP, preview and final ERP verification are required before any edit is saved. |
| Withdraw | Confirmed, CSRF-protected POST for the owner's active listing. Hides the offer immediately, records the customer/timestamp and preserves ownership snapshots/history. No ERP call or full OTP workflow. |
| Mark as Sold | Confirmed, CSRF-protected POST for the owner's active listing. Sets portal status/deal to Sold and hides the sale offer. It does not transfer ownership, create a booking or change ERP accounting. |
| Republish | Available only for a customer-withdrawn listing whose latest recorded lifecycle action is that customer's withdrawal. Reuses the same listing ID after fresh eligibility, booking-owner OTP, preview and final ownership checks. |
| Property photos | Multiple JPEG/PNG/WebP uploads, private thumbnails, main-photo selection, removal and preservation during editing, buyer preview and public gallery. Uncommitted uploads are never public. |
| Negotiable | Strict boolean saved in the existing listing JSON payload, separate from asking price. Included in OTP-bound data and displayed in preview, public prices, customer listings and modern Admin Resale records. |

No new admin approval queue was introduced. Historical pending records remain historical records. Buyer Inquiries, general notifications and scheduled ownership re-verification remain outside this phase.

## Existing architecture reused

- Actual listing table: `portal_listings`, including existing `status`, `deal`, `payload`, `asking_price`, customer/source/property identifiers and representative links.
- Existing `portal_owned_properties` composite upsert preserves one saved property per ERP source/property rather than inserting a new snapshot for every edit.
- Existing customer session, verified ERP links, CSRF filters and protected customer route group.
- `CustomerPortfolio` for fresh originating-ERP property retrieval and local mapping/catalog eligibility.
- `ResaleOwnershipOtp` and `ResaleSubmission` for property-specific OTP, encrypted exact-draft authorization, preview, final booking verification and atomic direct publication.
- Existing `ShalimarAgents` and `AgencyDirectory` eligibility; cross-ERP SGC agent assignment and source-specific authorized-agency rules remain in force.
- Existing Admin audit log, modern Admin Unpublish, public catalog visibility and Phase 1 `PublicListingContact` resolver.
- Existing four-step wizard and customer page layout. The old standalone poster/demo flow remains disabled.

## Actual states and transitions

The database already supported these statuses. This phase adds no status enum or alternate approval architecture.

| Starting state | Allowed customer action | Saved state | Authorization |
| --- | --- | --- | --- |
| `live`, `approved` or `active`, with `deal=available` or `active` | Edit | `status=live`, `deal=available`, same listing ID | Fresh property OTP, exact proposed data, preview, final current ownership/eligibility check |
| Same active states | Withdraw | `status=cancelled`, existing available/active deal | Confirmed owner session + CSRF + current record version |
| Same active states | Mark as Sold | `status=sold`, `deal=sold` | Confirmed owner session + CSRF + current record version |
| `cancelled`, available/active deal, latest event `customer_withdrawn` for the same owner | Republish | `status=live`, `deal=available`, same listing ID | Fresh property OTP, preview, final current ownership/eligibility check |
| `unlisted` (Admin Unpublish) | None of Edit/Republish | Preserved | Contact support; customer reactivation stays disabled |
| Sold, rejected, pending, legacy cancellation without a confirmed customer-withdrawal event, removed or otherwise inactive | None of Edit/Republish | Preserved | No implicit reopening |

The UI calls the eligible customer cancellation **Withdrawn**. This is distinguished from old ERP/admin cancellation by its persisted lifecycle event, rather than treating every `cancelled` row as eligible.

Admin Unpublish still uses `unlisted`, now increments the record version and records an `admin_unpublished` event atomically. A separate fresh submission for the same source/property cannot bypass an existing admin-unpublished customer listing. Classic Admin Approve/Re-open remains rejected, including for super administrators.

## Edit and Republish authorization

1. The server verifies the signed-in customer owns the listing. Foreign IDs return 404; action eligibility is checked server-side.
2. The editor obtains the original source/property and verified owner reference from the saved composite relationship. It freshly retrieves that property from the originating ERP. The customer cannot supply replacement ERP IDs, booking identity, Head/Subhead or mapping.
3. The existing eligibility rules require the published local project, active exact mapping and both stored Head/Subhead `smp_active` strings equal to `yes`. Fresh booking/ownership proof and the selected representative must also remain eligible.
4. The server freezes the listing operation, version and fingerprint. Only this target listing is excluded from its own duplicate-active check; another pending/active listing still blocks publication.
5. Price, Negotiable, description, selling method, representative, Self Sell public number and ordered photo IDs are normalized and sealed in the existing OTP authorization. OTP delivery resolves the **current ERP booking owner's registered mobile**, independently of the Self Sell public contact. The existing SMS test-recipient override, where configured, remains unchanged.
6. The code must be verified before the buyer preview is accessible. A revised submitted draft invalidates prior unconsumed authorization for that listing/property scope. The wizard's change-details action explicitly invalidates the current challenge. Added material fields on final confirmation invalidate the old authorization instead of silently changing the sealed draft.
7. Final confirmation fetches fresh ownership proof and rechecks project/mapping and agent/agency eligibility. A changed owner, booking, proof, representative, listing version or source/property/mapping causes rejection.
8. The existing transaction locks the authorization and listing, writes the exact authorized data, updates the existing saved-property composite, attaches selected private photos, consumes the proof, records history and writes the existing audit log. A failed check leaves the previously public listing unchanged.

The active-property unique constraint remains in use. Row locks, version/fingerprint comparisons and one-use authorization prevent duplicate/stale saves. Legacy catalog saves also compare record versions, skip unchanged rows and retain the Phase 1 policy guard; a stale Classic payload cannot overwrite a newer customer edit or reopen an inactive record.

## Withdraw, Sold and history

Both actions require an authenticated owner, strict confirmation, CSRF and the current integer record version. The transition service locks the row and permits only active-to-withdrawn or active-to-sold. Repeated, stale and terminal-state actions return conflict without duplicating history.

Each successful lifecycle mutation records the event, actor, timestamp, previous/next status and private previous/next listing records in `listing_events`, alongside the existing admin audit event. Public/customer history views expose only action/status/time, not stored proof or private snapshots. Booking proof stores a hash of the registered mobile rather than the raw mobile.

Withdraw stores `withdrawn_at` in the payload. Sold stores `sold_at`. Editing preserves the original creation/publication history and stores `last_edited_at`; Republish records `republished_at` and its new publication time. Property ownership records remain present after all transitions.

## Photos and storage

- Defaults in `Config\CustomerPortal`: **6 selected photos**, **4 MiB per image**, **20 million pixels**, **24-hour private staging lifetime**. The upload endpoint also applies draft quotas and the existing rate limiter.
- The upload path validates upload success, byte size, actual MIME with Fileinfo, dimensions and successful GD decoding. JPEG, PNG and WebP are decoded and re-encoded, stripping uploaded trailing executable content and metadata. Filename extensions alone confer no trust.
- Storage is `writable/resale-photos/{opaque-64-hex-id}.{jpg|png|webp}`. IDs/extensions are generated/validated; no user path is used. Files stay outside the public document directory.
- Uncommitted metadata is bound to the customer, exact submission key, session and expiry. Customer preview requires the authenticated owner; staged photos from another session/customer/draft cannot be selected.
- Ordered photo IDs are part of the sealed OTP draft. The first selected photo is the main image. Existing generated legacy image references can be retained; mixed legacy/new photos keep their saved order.
- Commit attaches selected images to the same listing within the listing transaction. No public file move occurs. Failed OTP, abandoned drafts or failed final verification leave private staged files, never public orphans.
- Public image delivery checks the current local listing status/deal, source, project, exact mapping, Head/Subhead visibility and current payload membership. Removed, withdrawn, sold, admin-unpublished or otherwise hidden images return 404. Responses use `no-store` and `nosniff`.
- Removed committed files remain private for listing history. They are not referenced by the current public listing. Existing static legacy assets retain their original storage compatibility.
- Each upload prunes up to 20 expired unattached files. The bounded CLI cleanup command is `php spark resale:prune-photos` (up to 100 per invocation). It never removes committed listing photos or their history. No scheduled job was installed.

## Public visibility and contacts

Public grids/search/project results, detail pages and contact resolution continue to use the shared local catalog eligibility gate. Active status alone is insufficient: source, published project, active mapping and both `smp_active='yes'` values are required. No ERP API is called for public media/catalog requests.

Withdrawn, Sold and admin-unpublished offers leave active results immediately. The existing Phase 1 resolver still supplies Self Sell's explicit public number, the approved eligible Shalimar agent business contact or the exact property's source-eligible mapped agency business contact. It never substitutes the private ERP registered mobile. Revoked/unapproved contacts stay unavailable; Support remains separately labeled.

## Routes

All customer routes are below the existing configured `/public/customer` base and use its existing authentication/CSRF setup.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/customer/resale` | Own listing cards |
| GET | `/customer/resale/{id}` | Own details/history |
| GET | `/customer/resale/{id}/edit` | Existing wizard with saved data |
| GET | `/customer/resale/{id}/republish` | Eligible withdrawn listing wizard |
| POST | `/customer/resale/{id}/withdraw` | Confirmed owner withdrawal |
| POST | `/customer/resale/{id}/sold` | Confirmed portal-only Sold transition |
| POST | `/customer/resale/photos` | Private draft uploads |
| GET | `/customer/resale/photos/{photo-id}` | Authenticated owner preview |
| POST | `/customer/resale/otp/invalidate` | Invalidate the bound draft authorization |
| GET | `/media/resale/{photo-id}` | Public image only while current listing is eligible |

Existing `/customer/resale/submit`, OTP verify/resend, preview and publish routes handle new publication, edits and Republish. No duplicate authentication or publication API was created. Existing admin routes remain in place.

## Application files

New:

- `app/Database/Migrations/2026-11-06-000001_CreateListingLifecycle.php`
- `app/Services/ListingLifecycle.php`
- `app/Services/ResalePhotos.php`
- `app/Commands/ResalePrunePhotos.php`
- `app/Controllers/ResaleMedia.php`
- `public/assets/customer/js/listing-lifecycle.js`

Updated controllers/services/configuration:

- `app/Controllers/Customer/Resale.php`
- `app/Services/CustomerPortfolio.php`
- `app/Services/ResaleOwnershipOtp.php`
- `app/Services/ResaleSubmission.php`
- `app/Services/AdminPortalData.php`
- `app/Config/Routes.php`
- `app/Config/CustomerPortal.php`
- `app/Libraries/PortalCatalogStore.php`
- `app/Libraries/PortalLinks.php`
- `html/includes/bootstrap.php`

Updated views/JavaScript:

- `app/Views/customer/layouts/main.php`
- `app/Views/customer/resale/live_create.php`
- `app/Views/customer/resale/live_index.php`
- `app/Views/customer/resale/public_preview.php`
- `app/Views/admin/records/table.php`
- `app/Views/admin/records/details.php`
- `public/assets/customer/js/resale-live.js`

New tests: `.qa/phase2_fixture.php`, `.qa/verify_phase2_lifecycle.py`. The existing `.qa/phase1_test_router.php` now propagates static-file handling correctly and permits the actual read-only public grid pages in the isolated HTTP test server. `.qa/verify_shalimar_agents_http.py` uses the exact confirmation contract: Phase 2 rejects changed material fields rather than ignoring them; explicit tampering/invalidation coverage is in the lifecycle suite. This report is `PHASE-2-LISTING-LIFECYCLE.md`.

## Migration and rollout

Migration: `2026-11-06-000001_CreateListingLifecycle` (local batch 9).

Additive portal changes:

- `portal_listings.record_version` for concurrent update protection; old rows start at 1.
- `resale_otp_challenges.authorization_scope` and customer/scope index for superseding old authorizations.
- `listing_events` for revision/status history, with foreign keys and unique authorization IDs.
- `resale_photos` for private staging/committed metadata, expiry/draft indexes and foreign keys.

Negotiable uses the existing listing `payload.negotiable` boolean. No price/accounting column, existing status values, customer rows, mappings or ERP tables were replaced or truncated.

For deployment, back up the portal database and writable directory, deploy the listed portal files and run `php spark migrate` on the portal database before reopening traffic. Confirm PHP GD (including WebP), Fileinfo and writable private storage are available. Ensure PHP `upload_max_filesize`, `post_max_size` and `max_file_uploads` accommodate the chosen limits, including multipart overhead. Keep `writable` outside the served document tree as in the existing deployment. No SGC/Zalmi API upload is needed for Phase 2.

Rollback: put the portal into maintenance first and back up database/files together. The migration's `down()` refuses rollback while either new history/photo table is nonempty, rather than discarding customer revisions. If no Phase 2 history/photos exist, roll back only the latest migration batch with the existing CI migration tooling and restore the matching previous application files together. Once Phase 2 is used, prefer a forward fix; an intentional rollback requires an explicit archival/restore plan for database and private photo files. Do not truncate the new tables to bypass the guard. MySQL DDL is not claimed to be transactionally reversible.

## Tests and preservation

Automated mutations run only in explicitly guarded disposable portal databases with fake ERP/SMS transports and isolated sessions. No real SMS was sent, no live ERP was queried or changed, and existing local portal rows were not used as test fixtures.

| Executed suite | Result |
| --- | --- |
| `.qa/verify_phase2_lifecycle.py` | **92 passed**: own/foreign access, edit price/description/Negotiable/photos, JPEG/PNG/WebP and invalid uploads, private staging, MIME/content sanitization, mixed photo order, booking-mobile OTP, wrong/superseded/changed-data authorization, fresh final ownership, same-ID save, public gallery/grid, agent and source-specific agency changes, CSRF, browser upload/main/removal, Withdraw, fresh-OTP Republish, duplicate/stale protection including legacy writes, admin restriction/replacement bypass, Sold, cleanup and exact history preservation. |
| `.qa/verify_phase1_safety.py` | **65 passed**: Phase 1 legacy publication guards, public entry routing and all contact/visibility protections. |
| `.qa/resale_ownership_checks.php` | **37 passed**: sealed draft/ownership proof, expiry, attempts, resend recipient resolution, one-use authorization and SMS failure. |
| `.qa/admin_portal_data_checks.php` | **32 passed**: actual read models, composite relationships, filtering, audit rollback, idempotent Unpublish and terminal-state preservation. |
| `.qa/verify_admin_portal_http.py` | **51 passed**: Admin Customers/Properties/Resale/Dashboard, permissions, CSRF, filters/details/pagination, Unpublish, browser layout and no admin ERP/SMS calls. |
| `.qa/verify_agency_http.py` | **55 passed**: sync/mapping, source-specific eligibility, bulk confirmation, alerts, RBAC/CSRF and history preservation. |
| `.qa/verify_shalimar_agents_http.py` | **69 passed**: existing login and ERP linking, property-specific OTP, preview/direct new publication, SGC and Zalmi agent assignment and admin records. |
| `.qa/run_visibility_http.py catalog` | **14 passed**: Head/Subhead/mapping public hide/restore, Sold remains hidden and mapping screen layout. |
| `.qa/run_visibility_http.py` | **109 passed**: existing admin authentication/RBAC, Classic/read modules, ERP Sources, audit, timeout, logout and throttle. |
| PHP/JavaScript syntax | Passed for all 21 affected application PHP files, the two PHP test helpers and both JavaScript files. |

Browser checks use desktop and mobile widths; screenshots are `.qa/phase2-edit-desktop.png` and `.qa/phase2-edit-mobile.png`. Actual file chooser upload, saved field/photo prefill, main-photo selection/removal and absence of uncaught JavaScript errors are checked.

Logs: `.qa/phase2-lifecycle-checks.log`, `.qa/phase2-phase1-regression.log`, `.qa/phase2-final-otp-checks.log`, `.qa/phase2-admin-service.log`, `.qa/phase2-admin-http-regression.log`, `.qa/phase2-agency-regression.log`, `.qa/phase2-publication-regression.log`, `.qa/phase2-visibility-regression.log`, `.qa/phase2-rbac-regression.log`.

A read-only check after migration/testing still shows **1 customer, 2 ERP links, 2 saved properties, 39 listings, 2 customer workflow listings and 1 active customer listing** in the actual local portal. Its new history/photo tables remain empty. These tests did not publish, overwrite, delete or otherwise change those records.

## Environment and remaining verification

- No requested Phase 2 feature remains intentionally unimplemented. Sold/admin-unpublished/rejected reactivation stays disabled by the requested business restrictions.
- Existing local SGC crashed-ledger/missing `smp_active` issues were not repaired or modified. They are not evidence of live ERP state; valid isolated API fixtures were used for automated coverage.
- Deployment and end-to-end live OTP/API verification are still required in the intended environment, using the existing test SMS override while customer delivery is being tested. Tests establish local application behavior, not live server availability or SMS delivery.
- Existing unapproved/blank contacts remain unavailable under Phase 1 rules. This phase does not invent, approve or substitute contact numbers.
- Buyer Inquiries, general notifications and periodic ERP re-verification remain future work as requested.
