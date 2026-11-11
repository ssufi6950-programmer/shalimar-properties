# Phase 4 — public Sale and Rent integration

Implemented locally on 11 November 2026. Phase 4 only. No production FTP, production SQL, ERP database write or real SMS was performed. Final test results and the exact upload inventory are in `deploy/phase4-public-sale-rent/`.

## Implementation and existing architecture

The website's existing standalone PHP entry points remain the public interface. They reuse the same private environment, Composer autoloader and MySQL database as CI4. `PortalCatalogStore`, the shared listing policy, central project mapping and existing private media controller connect the website to Phases 1–3. There is no replacement application, new identity system or duplicate property-details system.

Public URLs retained:

| URL | Result |
| --- | --- |
| `index.php` | Existing homepage; latest approved Sale and Rent listings share its existing featured section |
| `buy.php` | Approved, currently published Sale properties |
| `rent.php` | Approved, currently published Rent properties |
| `listing.php?id={id}` | Existing detail URL, extended for Sale/Rent and safe SEO |
| `projects.php?project={slug}` | Existing projects and eligible Sale/Rent property sections |
| `insights.php` | Existing official completed ERP resale reporting, retained |
| `sitemap.php` | New read-only XML sitemap using the same public eligibility scope |
| CI4 `media/resale/{photo-id}` | Existing media URL; stronger integrity and response-cache checks |

No CI4 route registration was needed. There was no existing public listing JSON API or AJAX listing search endpoint in the active website. Search continues to use GET forms and query strings; JSON-LD is not a private listing API. Future public data endpoints must reuse the public search/scope rather than `read('listings')`, which intentionally remains an internal Classic Admin adapter.

## Prerequisites and actual local database finding

The Phase 1 migration defines binary `sale/rent` purpose and the four review states. Its generated unique property slot includes source, property and purpose. Phase 2 supplies owner-OTP submissions, immutable submission events and private photos; Phase 3 supplies Admin approval, rejection, change requests, photo management, version checks and audit events. These implementations and their tests were inspected and exercised in isolated QA databases.

**The current original XAMPP database on port 3306 does not contain the Phase 1–3 listing/customer schema.** At inspection, `shalimar_resale_portal` contained only the Admin security tables, `erp_sources` and `migrations`. Therefore the normal local public/Admin-listing application cannot be considered ready for a populated preview from that database. Nothing was migrated into it, reset, imported or repaired. A failed initial read-only Phase 3 preservation probe exposed this missing-schema condition; the subsequent original-database snapshot records the tables that actually exist.

The functional tests use independently created schemas on the dedicated loopback QA MariaDB instance at port 33317. They do not demonstrate that the original local database or production has the prerequisites. Run the read-only preflight on the intended live database. If prerequisites are absent, apply the separately reviewed earlier phase setup through the existing controlled process; do not run Phase 4 as a database installer or blindly run `migrate --all`.

## Approved-only public visibility and revision integrity

Normal public records require exact `review_state=approved`, a supported purpose, an exact `live/approved/active` status, and an exact `available/active` deal state. Central source/project/mapping and exact Head/Subhead `yes` eligibility are mandatory. Public source and mapping identities must agree. No ownership query or ERP write is triggered by browsing.

Phase 2 customer submission events identify records governed by Phase 3 review. Those records additionally require a committed `admin_approved_published` event whose saved payload, record version, amount, source/property/project/owner bindings, representative assignments and owner-verification fields match the current row. Changing a price, description, contact assignment, photo set, rental data or version cannot retain public access just by leaving the badge Approved. The scope is shared with customer/agency reads, public contacts and media.

Historically approved records without the new submission events retain Phase 1 grandfathering, including older OTP-backed Sale listings. Merely having an old OTP reference does not make an existing legitimate Sale disappear. Existing Sold, cancelled, withdrawn and admin-unpublished history is not rewritten. There was no eligible public Sold archive in the inspected implementation; none has been invented. Sold details and seller actions remain unavailable publicly.

The established revision behavior is preserved: a material customer edit becomes private Under Review until reapproved. There is no new parallel revision/snapshot system that continues to display an earlier published revision while the same row is pending.

The public read model selects eligible rows in SQL instead of loading every private listing and then hiding them in PHP. Public projections exclude raw owner proofs, OTP values, private mobile and customer identity records. Missing database dependencies return a generic, uncached 503 without server paths or credentials. Inaccessible details return 404 with no indexing metadata containing the private listing.

## Sale/Rent browsing, search and pagination

Buy and Rent now use one shared browsing template with the existing green/gold design system. There are explicit For Sale/For Rent links, purpose-specific prices, project, verified property type, category/sector, subcategory/block, bedroom, budget, custom minimum/maximum price, size/unit, sorting and page-size controls. Filters execute on the server through bound PDO parameters.

Sale budgets are rejected on Rent and rental budgets are rejected on Buy. Contradictory purpose, invalid arrays/types, invalid ranges, unsupported sorts and unknown project/category selections return a clear 400 without broadening the result set. Search text is bounded; SQL wildcards are escaped and injection strings remain literal. Size ranges and area sorting require a unit, preventing comparison of square feet with Marla as though they were equal.

Pages contain 12, 24 or 48 results. Sorts are newest approved, numeric price ascending/descending and area ascending/descending. A listing-ID tie-break makes ordering stable. Pagination retains normalized filters. Existing budget/query links remain supported; browser back/forward remains native GET navigation. There is no AJAX or localStorage simulation.

Cards show purpose, correct price units, property number, classification, size, project/location, the ownership summary when supported by actual evidence, and the actual selected public seller identity. Card contacts are resolved in batches rather than per-card database queries. Existing internal full-catalog writes remain protected by the unchanged legacy publication guard.

## Details, classification and seller card

The same `listing.php` supports approved Sale and Rent. Rent displays monthly rent, optional security deposit, advance rent, furnishing and lease duration only when provided. Zero is preserved as a recorded optional amount; absent values are not invented. Negotiable remains separate from the monthly-rent unit.

Verified Phase 1 property classification is retained: constructed properties normalize to House in the originating ERP workflow. Phase 4 does not reinterpret unknown/non-constructed inventory as Plot or restrict rental eligibility to Houses. No ERP inventory import or new project identifier was added. Mapping remains the existing source/Head/Subhead combination to the portal project's slug.

The white rounded seller card and existing responsive layout are preserved:

- **Direct Owner:** linked seller display name and the listing's explicitly provided public phone/email. Private ERP OTP mobile is never a fallback. Email requires the listing's existing explicit public-email choice.
- **Official Shalimar Agent:** the exact selected eligible SGC Shalimar agent's current Admin-managed business identity/contact, including when handling Zalmi property under the existing rules. SGC Agency 193 and generic dealer ledger 11770 are not interchanged; nothing is sent to a Zalmi transaction workflow.
- **Authorized Agency:** the exact selected central agency and source-specific eligible ERP link, using its Admin-managed business contact.

Unrelated owner names are no longer substituted as the main identity for an Agent or Agency listing. Invalid/ineligible contacts display an unavailable notice and no seller contact actions. Call, WhatsApp and email URLs are normalized/escaped; general support remains visibly separate and is never substituted as the seller. Current Admin-managed eligible directory contacts remain authoritative, following the existing directory policy; customer changes to listing contact assignments require review.

## Photos, caching and SEO

The final saved approved gallery is reused; no public upload directory was added. Pending, unapproved, altered and unassociated private photo IDs return 404. Media verifies customer/listing association, exact approved image status, current gallery membership, safe path containment, MIME, byte count and stored checksum. Both successful and denied public media responses use no-store/nosniff. Removing/reordering photos preserves history and storage as before.

Only safe photo references are rendered. Missing images fall back to a bundled neutral illustration, with a photo-unavailable label; the public image helper no longer substitutes unrelated project stock photos as a property's own gallery. Archived, already public legacy static image URLs are not retroactively revoked or migrated in this phase; new private uploads keep their existing access control.

Public HTML and sitemap are uncached; private review and public media remain isolated. Restoring a page from browser back-forward cache triggers revalidation by reload. There is no persistent listing result cache to invalidate. Existing Market Intelligence caching remains separate.

Canonical URLs use configured `app.publicSiteURL`, or the existing local sibling-site layout derived from `app.baseURL`, never a visitor-controlled Host header. Approved detail pages include escaped public description, Open Graph metadata and JSON-LD with the actual PKR offer and monthly-rent unit where relevant. No private contact or unsupported rating/transaction-price assertion is included. Filter variants use noindex/follow; inaccessible/private details have noindex protection. The new sitemap contains only eligible detail IDs plus the established public entry pages.

## Market Intelligence lineage audit

`html/insights.php` **is in this repository**. Its active implementation calls `market_snapshot()` in `html/includes/market.php`, then `PortalCatalogStore::marketSnapshot()`, then `ErpMarketIntelligenceService` and authenticated `ErpMarketApi` transport. Reporting uses each source's existing `market-intelligence.php` endpoint. Source-specific API SQL evaluates completed, approved, non-reversed ERP resale booking evidence. Public listing counts, asks, rents, submission events and Admin publication decisions are not reporting inputs.

The service supplies Pakistan-local Today, current month, previous full month, and a zero-filled 30-calendar-day series. It checks source identity, scope, coverage and freshness. Outages remain explicitly unavailable or use an explicitly marked previous complete report under the existing cache policy; partial data is not silently presented as complete. Actual consideration and immutable comparable historical area/price snapshots are not verified, so the existing price-vs-last-year metric correctly remains N/A. This is a data dependency, not a price metric to fabricate.

A separately scoped price-reporting follow-up would first verify, in each ERP, the actual completed resale consideration, immutable area/unit, completion time and reversal evidence; then extend only the authenticated read-only reporting adapter using those proven fields. Comparable project/property-type/size cohorts and normalized units would be tested against underlying transaction observations, with current month-to-date compared to the equivalent prior-year period and no median-of-medians calculation. Missing cohorts must remain N/A. No such field/schema assumption or API expansion is included in Phase 4.

The active homepage Market Intelligence and the active `/insights.php` charts/CSV already use that official service and are preserved. Two misleading residual public uses were found and addressed: property detail's legacy `market_tx()` recent trades/average-rate panel was replaced with a clearly separate official reporting link; project-page deal counts now come from the official snapshot with unavailable counts shown as N/A. Existing illustrative homepage stories were labeled as illustrative, including their viewer metadata, and the unsupported “100% verified deals”/automatic-rental claim was removed.

Legacy Classic Admin transaction helpers and archived `html/samples/**` design copies still exist. Some archived insights pages label included sample transactions and use file-backed demo data; they are not the active `html/insights.php`, are not linked as official reporting, and are not in this deployment package. The duplicate `html/includes/home/insights.php` is a legacy implementation under the protected includes tree. Do not deploy/archive-route those samples as the official Market Insights application. No sample transaction data was promoted into the official service.

## Compatibility, tests and verification limits

See the generated `TEST-RESULTS.md` for final suite counts and log paths. New tests drive real customer OTP submission with injected ERP/SMS providers, actual Phase 3 approval, public discovery/details/contacts/media, query validation, stable pagination, stale material revisions, sitemap exclusions, checksum failures and SQL read-only behavior. Existing Phase 2/3, Agency, customer, classification, legacy-bypass and official-market regressions are run against isolated schemas.

Actual headless Edge tests cover 360, 390, 768, 1024 and 1440px, rendered public filters/cards/detail/contacts, navigation, real gallery switching and history/pagination, empty states and JavaScript errors. Screenshots are QA evidence with explicitly synthetic fixture identities, not real production customers. Final screenshots were visually inspected.

QA adaptations fix an old second-photo test that depended on random primary-key ordering, replace a dummy non-image foundation fixture with a genuine PNG, account for the current F: checkout/API html paths, and allocate an unused Agency browser debugging port instead of colliding with an occupied port. Application protections were not weakened to make old fixtures pass.

The older lifecycle suite's explicit fixture-approval helper now records a matching fixture approval snapshot; its six customer lifecycle events are counted separately from those fixture approvals. Actual authorization, fresh ownership and audit behavior are exercised by the independent 413-check Phase 3 suite. Node was unavailable on this machine, so the exact deployment JavaScript was also compiled by Edge's `Runtime.compileScript`, in addition to its real rendered-page tests.

## Database and deployment

No Phase 4 migration, modifying SQL, new index, seed/backfill or `.env` change is required. Read-only preflight SQL is supplied for phpMyAdmin; it is not an installer. Existing Phase 1–3 tables, indexes, valid data and transactional engines are required. Live schema/migration history, hosting PHP, private-media permissions, intended public-site URL, ERP availability and real SMS still need production UAT.

The deployment package contains only the changed application classes, website entry points/templates and public assets. It excludes `.env`, credentials, vendor, writable, customer photos, SQL dumps, QA schemas, archived packages and both ERP API folders. README provides the exact local-to-live mapping and rollback instructions. No SGC/Zalmi API upload is needed for Phase 4. Stop after Phase 4; no Phase 5/6 redesign or production-wide certification is implied.

<!-- PHASE4_INVENTORY_START -->
## Exact created/modified application files

| File | Change |
| --- | --- |
| `app/Controllers/ResaleMedia.php` | modified |
| `app/Libraries/PortalCatalogStore.php` | modified |
| `app/Libraries/PublicWebsiteUrl.php` | created |
| `app/Services/ListingPolicy.php` | modified |
| `app/Services/PublicListingContact.php` | modified |
| `app/Services/PublicListingSearch.php` | created |
| `app/Services/ResalePhotos.php` | modified |
| `html/buy.php` | modified |
| `html/rent.php` | modified |
| `html/listing.php` | modified |
| `html/projects.php` | modified |
| `html/sitemap.php` | created |
| `html/includes/bootstrap.php` | modified |
| `html/includes/header.php` | modified |
| `html/includes/public-browse.php` | created |
| `html/includes/home/common.php` | modified |
| `html/includes/home/sections/featured.php` | modified |
| `html/includes/home/sections/stories.php` | modified |
| `html/assets/css/public-listings.css` | created |
| `html/assets/js/public-listings.js` | created |
| `html/assets/images/property-placeholder.svg` | created |

QA-only files created: `.qa/verify_phase4_public.py`, `.qa/phase4_fixture.php`, `.qa/phase4_test_router.php`, `.qa/phase4_original_snapshot.php`, `.qa/run_phase4_suite.py`, `.qa/run_phase4_units.py`, `.qa/run_phase4_regressions.py`, `.qa/phase4_js_syntax.py`, `.qa/build_phase4_package.py`.

QA-only files adapted: `.qa/verify_admin_review.py`, `.qa/listing_foundation_checks.php`, `.qa/verify_erp_market.py`, `.qa/agency_portal_browser.py`, `.qa/phase2_fixture.php`, `.qa/verify_phase2_lifecycle.py`.

Reports/evidence: this implementation report, `deploy/phase4-public-sale-rent/README.md`, `LIVE-PREFLIGHT.sql`, `UAT-CHECKLIST.md`, `TEST-RESULTS.md`, `IMPLEMENTATION.md`, `manifest.json`, `PRESERVATION.json`, `VALIDATION.json`, the corresponding ZIP, and `.qa/phase4-*` logs/screenshots/baseline and preservation snapshots. QA files and runtime evidence are excluded from the upload application set.


## Follow-up: unavailable screenshot

After the screenshot report, the original local database was initially observed without listing tables; a later read-only check found those tables available and the full local homepage returning HTTP 200 with approved IDs 45/44/43. The patch did not run a migration/import or modify the original database. The earlier preservation snapshot describes the earlier Phase 4 baseline, not this later schema state.

The canonical helper's cold-start environment dependency and partial-error HTTP 200 behavior were reproduced and corrected in `app/Libraries/PublicWebsiteUrl.php` and `html/includes/bootstrap.php`. The 276 public regressions passed again; 11 dedicated error/cold-start checks also passed. The two-file package is `deploy/phase4-unavailable-fix`. It includes exact live paths and safe log/header instructions.

Live homepage/Projects were observed failing while Buy/Rent worked. Their exact backend exception is not yet confirmed from server logs. The patch has not been uploaded by this agent and must not be described as a verified live homepage repair. It fixes confirmed bootstrap/error-response issues and provides non-sensitive diagnostic categories for any remaining failure. Production GET checks do not prove ERP/SMS or Admin approval UAT.


## Matching market dependencies ? live deployment mismatch follow-up

The live Insights response was observed showing the old sample-transaction implementation while new homepage/Projects responses returned PUBLIC_COMPONENT_ERROR. Sitemap and approved detail/Buy/Rent routes worked. The archived old market helper lacks market_snapshot(), and a fresh-process call reproduces an Error consistent with this observation. Exact production exception location was not available without log access; live repair is not claimed before upload/testing.

The full package now explicitly includes 9 additional existing, unchanged market dependencies (30 application/dependency files total). The original 21 code-change inventory still describes 7 created and 14 modified files; these 9 additional files are deployment dependencies, not a new market redesign. The targeted ten-file set, which also includes the current Phase 4 home/common.php, is deploy/phase4-market-component-fix. Do not overwrite the current common.php or other Phase 4 code with an older market deployment bundle.

The repair copies matching services/helpers/templates from current source, preserves official ERP-only reporting and displays N/A when external reporting is unavailable. It includes no ERP API, SQL, .env or credential changes. 106 reporting regressions and 9 dependency-contract checks passed on isolated QA. Both the targeted and full ZIP require a post-upload live check.
