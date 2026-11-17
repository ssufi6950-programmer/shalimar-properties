# Stage B — public Property Explorer integration

Implemented and deployed with explicit user approval on 13 November 2026. No real portal/ERP records, schema, mappings or credentials were changed. No migration or ERP API change is required.

## Inspection and metadata checkpoint

The existing Layer 1 links are in `html/includes/home/hero-b.php`: `project.php?project=<portal slug>`. They remain byte-identical. The existing project-specific entry is **`html/project.php`**, a standalone PHP page using the shared portal services and layout; it has no separate CI4 project controller/view route. It resolves the slug through the existing published/visible `projects()` catalog. No additional project page or route was created.

Inspected source files include `PropertyExplorer`, `ExplorerFilterBuilder`, `ExplorerErpClient`, `PublicListingSearch`, `ListingPolicy`, `ErpVisibility`, `PortalCatalogStore`, `CustomerPortfolio`, `ResaleSubmission`, public bootstrap/header/footer and SGC's existing `CustomerController` API serializer. The local portal schema, enabled mappings, actual public-eligible listing metadata, ownership snapshots and saved filter rows were queried read-only. No ERP inventory was imported or fetched for a public request.

### Actual local identities

| Project | Reliable local listing identity | Saved filter identity |
| --- | --- | --- |
| Gulberg City | `portal_listings.project_slug` + `mapping_id` → matching `erp_project_mappings.erp_source_id`, `erp_head_id`, `erp_subhead_id` | `project_slug`, source, Head, `source_field='item_sublocation.id'`, exact Sub Location ID string |
| Shalimar Smart City | Matching source/Head mapping, source-bound `l.erp_source_id` + `l.erp_property_id`, and JSON string/integer `l.payload.sector` | `project_slug`, source, Head, `source_field='pro_inventory.sector'`, exact original Sector value |
| Zalmi X | Same verified mapping relationship as Gulberg; source is ZALMI | Source-specific `item_sublocation.id` identity; IDs are never shared across sources |

`portal_listings` already has `project_slug`, `mapping_id`, `erp_source_id`, `erp_property_id`, `payload`, `listing_purpose`, `review_state`, `status`, `deal`, version and approval-related fields. Head/Subhead are resolved from the existing mapping rather than invented JSON keys. `portal_owned_properties` additionally stores source/property/Head/Subhead plus its JSON snapshot.

The four current **local public-eligible records** are three Smart City Sale listings (SP-43/44/45), each with Sector string `1`, and one Gulberg Rent listing (SP-46), with mapped Sub Location 536. All three Smart City sectors agree with the source/property-matched ownership snapshots. Gulberg's blank Sector is irrelevant to its Sub Location filter. There is no current local public-eligible Zalmi listing; its matching and Sale/Rent behavior were exercised with disposable data.

The customer API reads inventory `sector` directly; its serializer and `CustomerPortfolio` preserve its text rather than converting a display label. `ResaleSubmission` saves that verified sector into listing JSON. No missing Sector was found among the current local eligible Smart City listings. This checkpoint supports local implementation without a capture change or backfill.

Missing/NULL/whitespace metadata is never guessed or inferred from a label. Such records remain eligible for **All**, but cannot be matched to an individual Sector. The implementation does not use a possibly newer mutable ownership snapshot to silently rewrite an approved listing. Actual integer/string `0` remains valid; floating-point/boolean/array metadata is not treated as a Sector identity. Binary comparison preserves case and trailing spaces.

The real local `portal_explorer_filters` table is currently empty. No filter rows were inserted into it. The populated filter rows used for tests belong only to disposable schemas. Live Stage A table/API readiness and saved selections were confirmed by the user. An additional live read-only inspection could not connect: saved hostname DNS lookup failed, and the previously supplied internal address timed out. **Current live listing metadata and live rendering remain unverified**; local observations above are not claims about live rows.

## Resulting behavior

- The existing project page immediately shows eligible listings using its existing paginated query, with All selected. Buy/Rent links and arrival purpose remain separate.
- Navigation reads only `portal_explorer_filters`, with `is_visible=1` and `parent_filter_id IS NULL`. Stage A display names, safe icons and display order are applied. Legacy JSON remains stored and unchanged, and is not a competing public configuration source.
- Current enabled source/Head scope is resolved through mappings. Sub Location choices must also belong to a mapped Subhead. Smart City filters are scoped to its mapped Head, across eligible mapped listings from any Subhead; no Subhead 555 restriction was added to Sector filtering.
- A hidden icon does not remove its listings from All. Saved visible options without listings remain available and produce a clear empty state. Wrong-project, hidden, obsolete or malformed selection keys return HTTP 400 with no unrestricted results.
- The server uses a stable hash of project/source/Head/field/original value in navigation. Each filtered count/results query also rechecks the saved row ID, identity, visibility and root status. Renamed labels do not enter matching SQL.
- Every results/count query retains `PublicListingSearch::fromSql()`, the existing source/project/Head/Subhead visibility gates and `ListingPolicy::publicSql()` approval/version/status/deal/deletion restrictions. Existing cards, contact resolver, images, prices, URLs and pagination are reused.
- Only one Layer 2 icon row is shown, with 36px badges, breadcrumb and inline All/Back. It wraps at narrow widths. Back uses existing `index.php#hero`. Result transitions use 200–220ms and respect reduced motion.
- The page handler now claims its own clicks in the capture phase so the shared site's page-transition listener cannot trigger an additional full reload. The shared script itself is unchanged. Browser history and ordinary links remain available.

The new metadata lookup uses two project-scoped local queries; filtered predicates use a saved-filter primary-key lookup inside the existing count/results queries. No per-property ERP request, extra inventory query or N+1 enrichment was added.

## Exact application files

| File | Change |
| --- | --- |
| `app/Services/PublicExplorerFilters.php` | New read-only Stage B saved-filter resolver and identity/visibility SQL predicate |
| `app/Libraries/PortalCatalogStore.php` | Existing Explorer options adapter uses the new saved-filter resolver |
| `app/Services/PublicListingSearch.php` | Existing shared query accepts the validated Stage B predicate; existing legacy service contract preserved |
| `html/project.php` | Existing project page uses saved configuration through its adapter; inline Back, breadcrumb, compact mobile badges and click-transition compatibility |

Package: `deploy/stage-b-property-explorer/portal/`. No ERP upload is needed for Stage B. No manual phpMyAdmin SQL is required because the existing approved Stage A table is reused. No production SQL or migrations were executed.

## Focused QA

**109 automated checks passed**, plus PHP and inline JavaScript syntax checks:

| Suite | Result |
| --- | --- |
| `.qa/stage_b_checks.php` | 34 service/database checks: exact identity, source/Head isolation, Gulberg/Zalmi Sub Locations, Sector 0, trailing spaces, blank/missing All behavior, hidden and renamed filters, configured order/icon, empty results, stale visibility recheck, invalid field/ID, source binding, terminal state/Head visibility, existing uniqueness, inactive mappings, root-only filters and bounded nonduplicating pagination |
| `.qa/verify_stage_b.py` | 40 browser/HTTP checks: existing page, immediate All, Admin labels/icons/order, inline Back, selection/breadcrumb/URL, Buy/Rent and arrival context, exact values, empty state, hidden-sector listings retained, pending/Sold exclusion, no raw proof output, no full reload, 360/390px no overflow, six compact badges, reduced motion, browser Back, both Sub Location projects, invalid/wrong-project selection rejection, no browser ERP requests and no uncaught JavaScript errors |
| `.qa/verify_builder.py` | 35 existing Stage A HTTP/browser checks passed unchanged: fetch/save/edit/refresh preservation, new unchecked options, Sector 0, errors/concurrency, both ERP sources, CSRF, Admin roles, mobile layout and guest authentication; fake ERP only |

QA cloned table structures into explicitly guarded disposable schemas; no real users, credentials, ownership rows or listings were copied. No real SMS or ERP requests were sent. Temporary schemas and servers were cleaned up after checks. A preserved SHA-256 baseline verifies **13 existing homepage, route, Admin Builder, customer workflow and privacy/policy files unchanged**.

Support files: `.qa/stage_b_metadata_inspect.php`, `.qa/stage_b_fixture.php`, `.qa/stage_b_router.php`, `.qa/stage_b_checks.php`, `.qa/verify_stage_b.py`. Evidence includes `.qa/stage-b-service.log`, `.qa/stage-b-builder-regression.log`, `.qa/stage-b-local-metadata-inspection.json`, `.qa/stage-b-preserved-baseline.json`, and `.qa/stage-b-smart-city-viewport-360.png` / `390.png`. Screenshots contain disposable sample records.

Public URL retained: `http://localhost/shalimar-properties/html/project.php?project=shalimar-smart-city` (also `gulberg-city`, `zalmi-x`, and optional `purpose=rent`). Browser QA used the same page through an isolated localhost router on port 8120.

**Deployment status: deployed with explicit user approval on 13 November 2026.** No homepage Layer 1, existing project route, Admin Builder behavior, customer submission/approval, ERP mapping or legacy JSON changes were made. The earlier connectivity failure was resolved in the deployment turn; live results follow below.


## Authorized live deployment ? 13 November 2026

The four portal application files listed above were compared with live and backed up before upload. Live differences were confined to the required Explorer integration; no unrelated live changes were overwritten. The new service was absent on live and was added. Server PHP syntax and final SHA-256 checks passed for all four files. A separate live baseline confirms 13 homepage, routing, Admin Builder, customer workflow and privacy/policy files remain unchanged.

Read-only live metadata inspection succeeded before deployment. It found 10 saved root filters: five visible Gulberg Sub Locations, four visible Smart City sectors and one hidden Zalmi Sub Location. All current eligible Smart City records have source-bound saved Sector data matching their ownership snapshots, including the exact `Business District ` trailing-space value on SP-51. Existing live public-eligible records: Smart City four Sales; Gulberg one Sale and two Rentals; Zalmi one Sale. No missing Sector blocker was found in these live eligible Smart City rows. No ERP API calls, data writes, SQL schema changes, migrations or SMS were performed during deployment.

**23 live HTTP checks passed:** homepage, projects, Buy, Rent, customer login, protected Admin redirect, all three project pages with both purposes, visible Smart City/Gulberg filters, empty selections, wrong-project rejection and direct hidden-filter rejection. **15 live browser checks passed:** immediate All, exact Business District match, compact 36px badges and no overflow at 360/390px, smooth filtering without a full reload, Rent empty state, Gulberg Sub Location filtering, hidden Zalmi icon absent while its listing remains in All, and no uncaught JavaScript errors.

Zalmi currently shows only All/Back because its saved `Pine Avenue Lahore` filter has `is_visible=0`. The deployment preserves that Admin decision; it does not hide its otherwise eligible listing from All.

Backup: `.qa/live-backups/stage-b-property-explorer-20261113-221728`. Manifest/hashes: `.qa/stage-b-property-explorer-live-deployment.json`. Verification: `.qa/stage-b-live-metadata-inspection.json`, `.qa/stage-b-live-http-smoke.json`, `.qa/stage-b-live-browser-smoke.json`, `.qa/stage-b-live-protected-baseline.json`. Screenshot: `.qa/stage-b-live-smart-city-390.png`.

Live page: `https://shalimarproperties.com/project.php?project=shalimar-smart-city`; existing Gulberg/Zalmi links and `purpose=rent` remain available. No SGC/Zalmi API folder upload was needed or performed for Stage B.
