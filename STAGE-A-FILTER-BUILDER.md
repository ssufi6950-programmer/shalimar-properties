# Stage A — Admin Property Explorer Filter Builder

Implemented locally after the user confirmed manual schema creation. The 12 required portal files were deployed on 13 November 2026; live ERP metadata fetching remains unverified. No further schema changes or migrations were applied to the real portal/ERP databases.

## Existing system and inspected schema

The actual local `SHOW CREATE TABLE portal_explorer_filters` was inspected before implementation. It contains the approved generated identity columns, source-specific uniqueness, project/Head foreign keys and parent self-reference scoped by project, source and Head. This implementation inserts only `parent_filter_id=NULL`, omits generated columns, and uses the existing `record_version` column. No additional table/column is required.

Existing Admin routes/controller/view were extended rather than creating another module. Admin layout, source configuration, authentication, permissions, CSRF and audit conventions are reused. Legacy public Explorer files and `portal_projects.payload._property_explorer` are preserved.

Observed local mappings (IDs are not application constants):

| Project | ERP source | Current local Head | Current mapped Sub Locations |
| --- | --- | --- | --- |
| Gulberg City | SGC | 48 | 533, 534, 536, 549, 550 |
| Shalimar Smart City | SGC | 49 | 555 (mapping resolves Head only for Sector discovery) |
| Zalmi X | ZALMI | 1 | 1 |

SGC's actual Property Booking form obtains **Sub Location** option IDs from `item_sublocation.id`, parent `location_id`, and uses them as booking `sub_head`. Both downloaded APIs use the same catalog relationship. Local SGC schema and inventory metadata were read directly. Full Zalmi Core PHP/database is not downloaded; its API source was inspected and its API handler tested with isolated metadata tables, not its live database.

## Admin flow

URL: `/public/admin/property-explorer` (retain your configured portal base path). Navigation: **Property Explorer → Filter Builder**.

1. Select an existing portal project. The screen resolves its enabled ERP source/Head from current mappings and displays the verified field.
2. **Fetch Options from ERP** makes one authenticated server-side request. It creates an expiring 15-minute server-session draft only; it does not write filters, import properties or publish anything.
3. New fetched values have unchecked checkboxes and default Hide. Select values, configure display name, allowlisted icon, Show/Hide and numeric order, then **Save Selected Filters**.
4. Saved root filters remain editable without another fetch while the current builder draft is valid. Refresh loads current metadata without overwriting saved customizations. Saved values missing from a refresh remain retained and marked unconfirmed.
5. Unchecking an existing saved value does not delete it. To change visibility, select it, set Hide/Show and save. No delete action exists here.

Browser submits only selected configuration JSON plus CSRF/project/draft tokens. Source IDs, Head IDs, exact source values, parent references and field names are resolved from trusted session/database state, never accepted as authoritative form fields.

## Source scoping and exact values

- **Smart City:** `SELECT DISTINCT` exact binary `pro_inventory.sector` under the dynamically resolved Head, across **ALL Subheads**. No Subhead 555, company, booking, status, `smp_active` or listing restriction narrows this metadata discovery.
- **Gulberg/Zalmi:** fetch only the current project's mapped Sub Location IDs, constrained again by their parent Head in the API query.
- NULL and whitespace-only values are excluded. Actual `0` is preserved. Raw source strings are never trimmed/lowercased/merged; `Business District` and `Business District ` remain distinct until the user corrects the source and explicitly refreshes.
- Saved values are never automatically removed or hidden when ERP data changes/disappears. The original value is immutable; a genuinely changed value is a new option requiring admin selection.
- Missing mappings, disabled sources, multiple source/Head mappings and a Head shared with another active project fail clearly for review. No project names/Head IDs are guessed.
- Source field names are a server-controlled allowlist: `item_sublocation.id` or `pro_inventory.sector`. API identifiers come only from existing private server configuration, are syntax-validated and checked against actual columns.

## New API endpoint

Both local API folders implement **GET `/smp-api/v1/filter-options.php`** through the existing authenticated router. It requires the existing bearer credential and returns only source code, Head, source-field identity and option value/label pairs.

Request parameters are bounded `head`, `kind=sector|sublocation`, and mapped `subheads` IDs for Sub Location mode. Only SGC permits Sector mode. The existing SELECT-only `CustomerDatabase` executes prepared metadata queries; no ERP business bootstrap or write is used.

Bounds: 4096-byte query string, at most 100 mapped Sub Location IDs, at most 1000 distinct returned options (overflow fails rather than serving a partial set). Portal transport uses approved hosts, public-address checks and DNS pinning, bearer references, HTTPS certificate validation, no redirects/proxy, 3-second connect/10-second total timeout, and a 2 MiB response cap. HTTP 404 explicitly says the API upload is missing. Other safe failures distinguish HTTP status/transport problems without revealing response bodies or credentials.

No actual live request to either ERP endpoint was performed. Existing live APIs will not support this endpoint until the matching local API files are uploaded later.

## Persistence and security

Saving re-resolves the selected project/source/Head mappings and rejects stale mapping drafts. A project-row lock serializes Builder saves. New roots save original identity, original label, custom configuration and successful-fetch timestamp. Existing rows use `record_version` checks and conditional updates; the unique generated key prevents duplicate roots. Selected rows and their audits commit in one transaction; an invalid/stale item rolls the entire batch back. Unselected rows, child records, project JSON, public mappings and listing records are not updated.

Parent-child capacity remains in the approved schema, but no child filters, child metadata fetching or maximum-depth assumption is implemented. This is Stage A only; the new table is not wired into public pages. Existing public Layer 2 behavior from earlier work remains unchanged.

GET requires `projects.view`. Fetch and Save require `projects.edit` plus the controller's `projects.view` check. Existing `AdminAuth`, `AdminPermission` and real CI4 CSRF middleware cover every route. Audit entries record original source identity and saved configuration. Icons are an existing predefined allowlist and all rendered source/display text is escaped. New audit field names were explicitly allowlisted.

## Changed application files

New portal files:

- `app/Services/ExplorerFilterBuilder.php`
- `app/Services/ExplorerErpClient.php`
- `app/Exceptions/ExplorerMetadataException.php`

Updated portal files:

- `app/Controllers/Admin/PropertyExplorer.php`
- `app/Views/admin/projects/explorer.php`
- `app/Config/Routes.php` (one POST fetch route; current GET/save routes retained)
- `app/Filters/AdminPermission.php`
- `app/Services/AdminAuditService.php`
- `html/includes/admin/navigation.php`

In **each** `D:/xampp/htdocs/sgcacc/smp-api` and `D:/xampp/htdocs/sgcacc/zalmi-smp-api`:

- New `src/FilterOptionsController.php`
- New `v1/filter-options.php`
- Updated `index.php` (load controller)
- Updated `src/routes.php` (authenticated GET route)

No token, `.env`, private `catalog.local.php` or database credentials are included in the package.

## Focused QA

**92 focused checks passed**, using guarded disposable portal/metadata tables and fake HTTP transport:

| Check | Result |
| --- | --- |
| `.qa/verify_builder.py` | 35 HTTP/browser checks: current Admin module, fetch/selection, Sector 0, save, display name/icon/order, refresh/new unchecked values, hide/re-enable, stale edits, failed fetch preservation, both mapped Sub Location sources, real CSRF Save/Fetch rejection, viewer/no-view/guest authorization, 360/390px layouts and no JavaScript errors. |
| `.qa/builder_service_checks.php` | 29 service/database checks: exact values and trailing spaces, zero/NULL/blank, root-only persistence, JSON preservation, version/duplicate prevention, transactional rollback, project isolation, foreign response rejection, refresh preservation, unsafe input rejection, audit fields, disabled/missing/shared mappings, dynamically changed Head and stale mapping rejection. |
| `.qa/builder_api_checks.php smp-api` | 11 API SELECT/query checks against disposable metadata tables: all-Head sectors include an unmapped Subhead, correct Sub Locations, exact values, zero/blanks, parent-Head isolation and invalid scopes. |
| `.qa/builder_api_checks.php zalmi-smp-api` | 7 API checks: mapped Sub Locations, foreign Head isolation, invalid inputs, disallowed Sector mode. |
| `.qa/builder_transport_checks.php` | 10 fake cURL checks: verified TLS, DNS pinning, redirect/proxy rejection, timeouts/body limit, HTTP 404/401/503 and connection failure handling. |

All 17 affected portal/API PHP files pass syntax checks; Admin inline JavaScript passes Node syntax checks. A seven-file checksum baseline confirms the homepage, project page, public listing search/store, legacy `PropertyExplorer` service and resale submission workflow remained byte-identical.

Additional **read-only local SGC** handler smoke check (`.qa/builder_local_erp_readonly.php smp-api`) dynamically resolved portal mappings and read actual local inventory/catalog using prepared SELECTs: Smart City returned 7 distinct exact sector values including `0`; Gulberg returned its 5 mapped Sub Locations. This exercised the new handler against local schema, not a live HTTP endpoint.

Test setup cloned empty structures, including the manually created filter table, into a guarded disposable database; it did not copy real users, customers, API credentials or business records. No CI4 migrations ran. The test database and temporary test server are cleaned up after validation. QA router/client files are not deployment files.

## Deployment and remaining dependencies

Portal deployment completed on 13 November 2026: 12 files were backed up, server-linted, uploaded and SHA-256 verified. Deployment also required the existing `PropertyExplorer.php` helper service, `AdminSecurity.php` navigation permission and `admin_helper.php` URL mapping, which were absent/outdated on live. The live sidebar label Resale Listings was preserved. ERP API folders were not uploaded.

Read-only live preflight found `portal_explorer_filters` missing (SQLSTATE 42S02). The previously confirmed schema exists locally; apply the approved `database/sql/property-explorer-stage-a.sql` manually to the live SMP database before Fetch/Save. No live SQL, migrations, ERP writes or SMS were executed.

Live smoke checks passed: homepage, projects and customer login HTTP 200, protected Builder redirects guests to Admin login. Authenticated Fetch/Save is blocked by the missing table and was not tested live. Matching ERP API endpoints must also be present; their live availability/token validity remain unverified. Backup: `.qa/live-backups/stage-a-filter-builder-20261113-214051`; manifest: `.qa/stage-a-filter-builder-live-deployment.json`; smoke results: `.qa/stage-a-live-smoke.json`.

Public Stage B integration, filter children and ERP inventory imports are outside this implementation. New selections do not alter public pages until a separately authorized Stage B change.
