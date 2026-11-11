> Authorized Agencies update (5 November 2026): the agencies module is now database-backed with source-specific authorization, manual name suggestions/mapping, sync and persistent alerts. See [AUTHORIZED-AGENCIES-IMPLEMENTATION.md](AUTHORIZED-AGENCIES-IMPLEMENTATION.md). Older preview/module status below is historical.

# Catalog API, Synchronization and Mapping ? Implementation Overview

> Customer module update (5 November 2026): the server-side customer OTP/session and on-demand ownership integration is now implemented. The old sample-only customer status below is historical. ERP identity/ownership endpoints are now implemented in both downloaded API folders and local portal paths are configured; the matching customer API ZIPs must be uploaded to the live hosts; see CUSTOMER-ACTIVATION.md and CUSTOMER-ERP-API-CONTRACT.md for current scope and activation prerequisites.
Prepared: 5 November 2026 (Asia/Karachi).
Purpose: Share this document with ChatGPT or another developer to prepare the next implementation task. This is a description of the current implementation, not an instruction to rebuild it.

## 1. Simple explanation

SGC aur Zalmi ERP se abhi sirf **Head/Subhead catalog** read hota hai. Portal is catalog ki local copy save karta hai. Admin ERP ke Head/Subhead combination ko portal ke local project se map karta hai. Public visibility local database aur original `smp_active` string values se decide hoti hai.

**Catalog sync is not property/ownership sync.** Customers, bookings, individual properties, payments and ownership are not imported by this endpoint.

```text
SGC / Zalmi ERP database
    -> read-only GET /smp-api/v1/catalog.php
    -> portal fetches a complete authenticated snapshot over HTTPS
    -> erp_heads + erp_subheads in the local CI4 database
    -> admin maps an ERP Head/Subhead pair to a local project
    -> public project/listing visibility is checked against local database records
```

## 2. Verified current status

A read-only check of the local portal database on 5 November 2026 confirmed:

| Item | Current state |
| --- | --- |
| SGC source ID | 1 |
| Zalmi source ID | 2 |
| Latest recorded successful SGC sync | 2026-11-05 01:49:15, Asia/Karachi |
| SGC catalog in that successful run | 70 Heads, 468 Subheads |
| Latest recorded successful Zalmi sync | 2026-11-05 01:50:15, Asia/Karachi |
| Zalmi catalog in that successful run | 4 Heads, 4 Subheads |
| Total locally stored Heads | 74 |
| Total locally stored Subheads | 472 |
| Local portal projects | 3 |
| Saved ERP project mappings | 7 |
| Local legacy/example listings | 37 |
| Listings with an assigned mapping | 0 |
| Imported customer-owned properties | 0 |

The current database records successful catalog syncs. Earlier `https_required`, bearer `401`, and generic `500` messages are historical failures, not the latest recorded synchronization status. Recheck the database/logs when starting the next task; these counts are a snapshot, not permanent configuration.

The three local projects are `gulberg-city`, `shalimar-smart-city`, and `zalmi-x`; all currently have `published=1`.

## 3. Existing mappings to preserve

All seven mappings were active and had `head.smp_active = 'yes'` and `subhead.smp_active = 'yes'` at the database check.

| Source | ERP Head ID / name | ERP Subhead ID / name | Local project slug |
| --- | --- | --- | --- |
| SGC (1) | 48 / Gulberg City | 534 / 3.5 Marla Villas | `gulberg-city` |
| SGC (1) | 48 / Gulberg City | 533 / Gulberg Apartments Homes | `gulberg-city` |
| SGC (1) | 48 / Gulberg City | 536 / Gulberg City Plots | `gulberg-city` |
| SGC (1) | 48 / Gulberg City | 549 / Liberty Market | `gulberg-city` |
| SGC (1) | 48 / Gulberg City | 550 / Mini Market | `gulberg-city` |
| SGC (1) | 49 / Shalimar Smart City | 555 / Shalimar Smart City Plots | `shalimar-smart-city` |
| ZALMI (2) | 1 / Zalmi X | 1 / Pine Avenue Lahore | `zalmi-x` |

One local project can have several ERP Head/Subhead mappings. Do not assume a one-to-one relationship between project and mapping. ERP IDs can overlap across sources.

## 4. Projects and source locations

### Portal

- Workspace: `D:\xampp\htdocs\shalimar-properties`
- Framework: CodeIgniter 4.7.6, PHP 8.2+, MySQL/MariaDB.
- Local URL: `http://localhost/shalimar-properties/public/`
- Production-style public website remains in `html/`; CI4 admin/customer routes are under `public/`.

### ERP API code

- SGC local API: `D:\xampp\htdocs\sgcacc\smp-api`
- Zalmi local API: `D:\xampp\htdocs\sgcacc\zalmi-smp-api`
- Both are standalone Core PHP APIs, not CI4 applications.
- SGC full ERP source is available under `D:\xampp\htdocs\sgcacc`; only Zalmi's API code is available locally. The owner confirmed the two ERP schemas are identical.

### Live endpoints

- SGC catalog: `https://sgcacc.com/smp-api/v1/catalog.php`
- Zalmi catalog: `https://zalmidevelopments.com/smp-api/v1/catalog.php`
- Each also has a public `/smp-api/v1/health.php` endpoint.

### Zalmi live layout supplied by owner

- API directory: `/home/zalmipk_user/www/html/smp-api`
- ERP configuration: `/home/zalmipk_user/www/html/ERP786-ZALMIDEV/includes/config.php`

The owner has file upload/download access only. Do not require SSH, Apache virtual-host edits, new PHP extensions or server restarts for routine endpoint changes.

## 5. Actual ERP schema used by catalog

| Business term | ERP table | Required fields |
| --- | --- | --- |
| Head | `item_location` | `id`, `name`, `smp_active` |
| Subhead | `item_sublocation` | `id`, `name`, `location_id`, `smp_active` |

Relationship:

```sql
item_sublocation.location_id = item_location.id
```

The endpoint normalizes the Subhead parent field name to `head_id` in JSON. It retains the original ERP IDs and does not invent new ERP identifiers.

`Head` here means the ERP property/location classification. Do not confuse it with an accounting ledger/chart-of-account Head.

## 6. API contract and implementation

`GET /smp-api/v1/catalog.php` requires a valid bearer token and exports both collections in one response:

```json
{
  "api_version": "v1",
  "complete": true,
  "heads": [
    {"id": "48", "name": "Gulberg City", "smp_active": "yes"}
  ],
  "subheads": [
    {"id": "534", "head_id": "48", "name": "3.5 Marla Villas", "smp_active": "yes"}
  ],
  "head_count": 1,
  "subhead_count": 1
}
```

This JSON is an illustrative subset, not the complete live dataset. IDs may be JSON strings or integers; the portal validates and stores them without conflating sources.

- Returns **all** Heads/Subheads, including `no`, NULL and unexpected flag strings.
- No `smp_active` filter is applied in ERP SQL.
- Does not depend on `updated_at`; flags can change without a timestamp update.
- Uses one `SELECT ... UNION ALL ...` statement for both collections.
- Uses mysqli when available, with PDO MySQL fallback. Existing InnoDB and MyISAM table exports have been tested.
- No INSERT, UPDATE, DELETE, migration, business/session bootstrap or ownership changes are performed against ERP data.
- Converts text encoding for valid UTF-8 JSON; it does not trim, lowercase or convert flags to booleans.
- Health is a liveness check only; a successful health check does not import catalog data.

Important API files:

| File | Responsibility |
| --- | --- |
| `index.php` | Bootstrap, JSON failure handler, route dispatch |
| `src/routes.php` | Health public; catalog protected |
| `src/CatalogController.php` | Schema checks, read-only export, safe DB error codes |
| `src/BearerAuthentication.php` | Hash comparison, missing/invalid token rejection, Apache header fallbacks |
| `src/config.php` | Uploaded private token hash takes priority over stale hosting variables |
| `src/ApiEnvironment.php` | Reads private configuration first; hosting variables are fallback |
| `src/catalog.local.php` | Private token hash and this ERP's database settings/config path |
| `src/ErpDatabaseSettings.php` | Reads literal DB constants from a config file as text, without executing it |
| `.htaccess` | Header forwarding and hidden-file protection |
| `src/.htaccess` | Denies direct access to implementation/private files |
| `v1/catalog.php` | Direct PHP endpoint; no rewrite setup needed |

The config-file reader supports literal `DB_SERVER`, `DB_USER`, `DB_PASS`, and `DB_NAME`. It refuses missing/dynamic values or multiple differing DB branches instead of guessing. Explicit production DB values can be set in the private API file when needed.

## 7. Authentication and deployment decisions

- Separate bearer credentials exist for SGC and Zalmi.
- Raw tokens live in the ignored portal `.env`; ERP upload files contain the matching hashes.
- `erp_sources` stores only a credential reference, not the raw secret.
- Do not include passwords, raw tokens or private deployment-file contents in shared prompts/documents.
- API authorization accepts the actual `Authorization` header, Apache redirect variables and `getallheaders()` fallback. Root `.htaccess` preserves the header for CGI/FastCGI.
- **The API-side PHP HTTPS-variable gate was removed intentionally.** It falsely rejected public HTTPS requests when a proxy/backend did not set PHP's HTTPS fields.
- The portal still enforces HTTPS URLs, certificate validation, approved hosts/public destination addresses, disabled redirects and bounded requests. Forwarded headers never replace bearer authentication.
- Do not reintroduce the PHP backend HTTPS gate or require Apache/SSH changes as part of the next task.

Latest package names:

- `.qa/erp-api-upload/sgc-smp-api-catalog-fix.zip`
- `.qa/erp-api-upload/zalmi-smp-api-catalog-fix.zip`

Each archive contains a root `smp-api/` directory. Include hidden files when uploading. Keep archives/private config confidential. The latest locally prepared health build is `2026-11-05.catalog-fix.3`; confirm the live build when diagnosing deployment differences.

## 8. Portal catalog synchronization

`App\Services\ErpConnectionService::catalogSnapshot()` fetches the selected source's `base_url + '/catalog.php'` with its bearer token. It retains the existing approved-host, TLS, DNS/public-IP and public-address-override controls. Current catalog limits are 16 MiB response size and a 30-second request timeout.

`App\Services\ErpCatalogSync::synchronize()` then:

1. Requires an active source and the `erp_sync.run` permission for web requests.
2. Acquires a source-specific database lock **before fetching**, so overlapping jobs cannot apply snapshots out of order.
3. Validates `api_version`, `complete=true`, collection types/counts, identifiers, names and raw flag types.
4. Allows orphaned/unassigned Subheads to remain stored, but they cannot become publicly eligible without a present Head.
5. Begins a local database transaction and rechecks source configuration under a row lock.
6. Marks that source's stored Heads/Subheads `present=0`.
7. Upserts every returned record with its raw flag and `present=1`.
8. Records success and advances `last_sync_at` in the same transaction.
9. Rolls back on failure, records a failure, and retains the previous complete snapshot.
10. Releases the per-source lock.

Missing ERP records are retained locally as absent; they are not hard-deleted. Reparented Subheads preserve the old combination as absent and create a new combination under the new Head.

Synchronization never alters project publication, mapping activation, listing status/deal or ownership.

Run manually through Admin > Synchronization, or:

```text
php spark erp:sync-catalog
php spark erp:sync-catalog 1
php spark erp:sync-catalog 2
```

No automatic live cron/scheduler was installed. The command exists; do not assume periodic synchronization is already running.

## 9. Local database model

The portal database is `shalimar_resale_portal`; connection secrets are in private configuration. Catalog migration:

`app/Database/Migrations/2026-11-05-000001_CreateErpCatalog.php`

| Table | Main fields / purpose |
| --- | --- |
| `erp_sources` | Existing ERP registry; ID, code, base URL, auth type/reference, active flag, health and sync timestamps |
| `erp_heads` | PK `(erp_source_id, erp_head_id)`; name, raw nullable string `smp_active`, `present` |
| `erp_subheads` | PK `(erp_source_id, erp_head_id, erp_subhead_id)`; name, raw nullable string flag, `present` |
| `portal_projects` | PK `slug`; name, local `published` flag, legacy-compatible JSON payload |
| `erp_project_mappings` | ID, source/Head/Subhead key, local `project_slug`, local `is_active` |
| `portal_listings` | ID, local project slug, nullable `mapping_id`, status, deal, legacy-compatible JSON payload |
| `portal_owned_properties` | Source/property ID, Head/Subhead IDs, owner reference and JSON payload; schema exists, real ownership import is not implemented |
| `erp_sync_runs` | Source, result, completion timestamp and safe status message |

The mapping key is unique:

```text
erp_source_id + erp_head_id + erp_subhead_id
```

Use `erp_source_id` as the relational source identifier. Do not replace it with a source name/code or assume IDs from different ERPs are globally unique. Existing foreign keys restrict deletion of referenced records.

The project/listing tables are deliberately minimal and preserve legacy JSON payloads. They are not evidence that all modern CRUD screens or full property/owner models have already been implemented.

## 10. Mapping screen behavior

URL: `http://localhost/shalimar-properties/public/admin/workspace/mapping`

### Create mapping

Select an eligible ERP/source/Head/Subhead combination, choose an existing local project, then create the mapping.

- Dropdown options come from synchronized local database rows.
- Source must be active; Head and Subhead must be present.
- Both original flags must be exactly `yes`.
- Server rechecks eligibility inside a transaction; forged/stale selections cannot bypass the rule.
- Existing mappings stay stored/displayed if a flag later becomes `no`.
- Local mapping activation can be toggled independently; it cannot override ERP visibility flags.

### ?Assign a listing to its exact ERP mapping?

This is a manual bridge for existing local legacy/example listings. It sets `portal_listings.mapping_id`.

- Listing and mapping must belong to the same local project.
- It does not import an ERP property or prove ownership.
- It does not approve/reopen the listing or change status/deal.
- A Shalimar Smart City listing cannot be assigned to a Gulberg City mapping.
- No listing currently has this assignment in the checked database.

For a future real ERP-property resale flow, mapping should normally be resolved automatically from the **verified property's exact source/Head/Subhead IDs**. This automatic flow does not exist yet. Never infer the mapping from project names alone, and do not assign every listing to the first mapping of its project.

## 11. Final visibility rule ? preserve exactly

`smp_active` contains **strings**:

```php
$head['smp_active'] === 'yes' && $subhead['smp_active'] === 'yes'
```

Never use `smp_active = 1` / `0` in ERP SQL. `no`, NULL, `YES`, `yes `, empty strings, `1`, and unexpected values are not publicly eligible. The portal uses byte-exact SQL comparisons as well as strict PHP comparisons.

A public project requires:

- Local project published.
- At least one active mapping with an active ERP source.
- The mapped Head/Subhead both present and both exactly `yes`.

A public listing additionally requires:

- Its own exact `mapping_id` belongs to the same local project.
- Local status is `live`, `approved` or `active` (`live` is the classic public/approved status).
- Local deal is `available` or `active`.

Going to `no` hides associated content after the next successful sync. Returning to `yes` restores eligibility only if all local conditions still hold. Never reopen sold, cancelled, rejected, pending or removed listings automatically.

Ownership storage and public visibility are separate. Never delete customer-owned properties because an ERP visibility flag changes.

## 12. Public website integration

Public pages read the local project/listing database through:

- `app/Libraries/PortalCatalogStore.php`
- `app/Services/ErpVisibility.php`
- `html/includes/bootstrap.php`
- `html/listing.php`

The adapter preserves the existing public/classic payload format while using `portal_projects` and `portal_listings` as the catalog source of truth. Legacy project/listing JSON files were copied into the DB and kept as an archive. Public lists and direct listing detail URLs both apply visibility checks.

Public requests do not call ERP APIs and do not fall back to archived sample JSON when no mapping is eligible. They use `Cache-Control: no-store` so a prior response does not mask a visibility change.

## 13. What is dynamic and what is unfinished

| Module | Current implementation |
| --- | --- |
| Admin authentication/users/roles/permissions/audit | Database-backed, existing security modules |
| ERP Sources | Database-backed CRUD and health testing |
| Head/Subhead catalog | Database-backed sync/display |
| Project Mapping | Database-backed create/activation and manual listing assignment |
| Synchronization | Real complete catalog sync, results and CLI command |
| Local public project/listing reads | Database-backed with ERP visibility conditions |
| Modern Projects screen at `admin/workspace/projects` | **Still sample-data preview**; modern create/edit/publish actions are not DB-backed |
| Classic Admin Projects | Existing workflow now reads/writes the local project DB through the adapter |
| Customer portal login/OTP/property/resale flow | Existing sample-only frontend; not real owner authentication or ERP property data |
| ERP individual properties/customers/ownership sync | **Not implemented** |
| Automatic mapping of a newly created real resale listing | **Not implemented** |
| Automatic recurring sync job | **Not installed** |

Do not mistake prototype labels/counters for real database values. Do not treat the 37 legacy/example listings as synchronized, ownership-verified ERP inventory.

## 14. Code map and protected routes

| File | Responsibility |
| --- | --- |
| `app/Services/ErpCatalogSync.php` | Validation, locking, transactional local import, missing records, logging |
| `app/Services/ErpConnectionService.php` | Secure ERP HTTP transport, bearer headers, response bounds and error handling |
| `app/Services/ErpMappingService.php` | Eligible pairs, mapping creation/activation, same-project listing assignment |
| `app/Services/ErpVisibility.php` | Shared exact-flag/public eligibility SQL |
| `app/Libraries/PortalCatalogStore.php` | DB adapter for existing public/classic project/listing formats |
| `app/Controllers/Admin/ErpCatalog.php` | Catalog admin pages and writes |
| `app/Controllers/Admin/Workspace.php` | Dispatches mapping/heads/sync to real module; other preview tabs remain |
| `app/Views/admin/erp/catalog.php` | Existing styled mapping/Heads/sync UI |
| `app/Exceptions/ErpCatalogRequestException.php` | Fixed safe API error descriptions for admin |
| `app/Commands/ErpSyncCatalog.php` | `erp:sync-catalog` command |
| `app/Commands/ImportLegacyCatalog.php` | One-time/idempotent legacy project/listing import |
| `app/Config/Routes.php` | Explicit routes; auto-routing remains disabled |
| `app/Filters/AdminPermission.php` | Per-endpoint permissions |

Relevant routes:

```text
GET  admin/workspace/mapping
GET  admin/workspace/heads
GET  admin/workspace/synchronization
POST admin/erp-mappings
POST admin/erp-mappings/{id}/status
POST admin/erp-mappings/assign
POST admin/erp-catalog/{sourceId}/sync
```

Existing permission keys include `erp_mapping.view/create/edit`, `erp_sync.view/run/view_logs`, and `resale_listings.edit` for manual listing assignment. Preserve server-side authentication, granular authorization, session CSRF and audit behavior.

## 15. Verification already available

- `.qa/verify_catalog_apis.php`: both actual API codebases tested in isolated runtime copies/databases; complete export, raw flags, bearer rules, Apache-forwarded headers, stale environment hash, DB config-file reading, mysqli/PDO, MyISAM, and safe failure codes.
- `.qa/verify_erp_config_reader.php`: literal credential extraction without executing config/business code; escaped strings, comments, ambiguous/dynamic/missing definitions and unreadable file paths.
- `.qa/verify_erp_visibility.php`: 64 combinations of raw flag values, public SQL and mapping-option agreement, terminal states, local publication/mapping/source controls, missing/reparented/orphaned records, rollback, overlap locking and ERP ID isolation.
- `.qa/erp_mysql_checks.php`: existing ERP source/model/network-policy regression.
- `.qa/run_visibility_http.py`: isolated existing admin/RBAC/CSRF/ERP CRUD/workspace regression.
- `.qa/run_visibility_http.py catalog`: real mapping/assignment forms, direct public URL yes/no/yes behavior, sold-state preservation, desktop/mobile browser checks.

Tests create scoped temporary schemas/fixtures and clean up their own data. API/visibility tests have passed locally. Do not run destructive migrations or recreate existing live data to start the next task.

## 16. Constraints for the next task

1. Inspect current files/schema before editing; preserve the three projects, seven mappings and real synchronized catalog.
2. Keep SGC and Zalmi source identity separate everywhere.
3. Preserve exact string `smp_active` semantics and full sync of inactive records.
4. Do not change ERP business logic, existing flags or ownership to make the UI appear populated.
5. Do not disable bearer authentication or portal TLS verification.
6. Keep deployment compatible with file upload/download only.
7. Do not reintroduce backend HTTPS-variable rejection, PDO-only or InnoDB-only ERP assumptions.
8. Preserve the existing admin UI, ERP Sources CRUD, authentication, RBAC, CSRF and audit modules.
9. Scope the next task explicitly: modern Projects CRUD and real property/ownership integration are separate unfinished modules.
10. If adding property/customer APIs, inspect their actual ERP tables and ownership relationships first; this overview only verifies the Head/Subhead schema.
11. Automatic resale mapping must use a verified ERP property's exact source/Head/Subhead identity; names or sample owner data are not authorization.
12. Do not request, paste or expose production passwords/tokens in a shared prompt.

## 17. Copy-paste context for ChatGPT

```text
I have an existing Shalimar Properties portal built with CodeIgniter 4 and two Core PHP ERP APIs (SGC and Zalmi). Read the attached CATALOG-IMPLEMENTATION-OVERVIEW.md before writing my next development command.

The catalog integration already works: both ERP sources have successful recorded full Head/Subhead syncs. The local DB currently has 74 Heads, 472 Subheads, 3 projects and 7 active project mappings. Preserve them. The 37 legacy/example listings are not real imported ERP property records; all currently have mapping_id NULL. Real ownership import is not built.

Head=item_location; Subhead=item_sublocation; parent=item_sublocation.location_id. smp_active is a STRING. Public eligibility requires exact 'yes' on BOTH records plus active source/mapping, local published project and an approved/active unsold listing. Sync all records including no/NULL/unexpected values; never delete ownership or reopen terminal listings because flags change.

Deployment is file upload/download only; no SSH/server configuration changes. Bearer authentication remains required; the portal enforces verified HTTPS. Do not rebuild or break the working catalog sync, mapping, ERP Sources, RBAC or admin UI.

My NEXT TASK is: [WRITE THE NEW FEATURE HERE].

Write a focused implementation command for my coding agent. Separate already implemented behavior from new work. Require inspection of actual relevant tables/code, preserve existing data/security/UI, and include meaningful acceptance tests. Do not assume modern Projects CRUD, real customer authentication, individual-property sync or automatic resale mapping already exists.
```
