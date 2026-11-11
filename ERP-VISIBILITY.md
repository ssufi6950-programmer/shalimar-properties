# SGC and Zalmi visibility integration

> Latest catalog package (build `2026-11-05.catalog-fix.3`): file-based configuration takes priority; catalog export uses mysqli/PDO and a single UNION ALL SELECT supporting InnoDB or MyISAM. Explicit transactions and an InnoDB-only requirement described in earlier notes are superseded. Safe error codes now identify config/database/schema failures.

> Current deployment: use the upload-only packages described in `.qa/erp-api-upload/UPLOAD-NOTES.md`. The PHP backend HTTPS-detection guard has been removed; the portal still enforces verified HTTPS and APIs retain bearer authentication. Earlier proxy/server-configuration notes below are historical.

Implemented 2026-11-05. SGC Core PHP source and SQL schema identify Heads as `item_location` and Subheads as `item_sublocation`, joined by `item_sublocation.location_id = item_location.id`. The owner confirmed that Zalmi is an identical copy. ERP business code and tables have not been changed.

The available local SGC dump/database predates `smp_active`; it does not contain that column. Live deployments must already have the string column on both tables, as described by the owner. The API validates schema/type and returns a generic server error if it is missing; it does not add columns or reinterpret numeric flags.

## ERP upload

Updated source folders:

- `D:\xampp\htdocs\sgcacc\smp-api`
- `D:\xampp\htdocs\sgcacc\zalmi-smp-api`

Ready-to-upload ZIPs are under `.qa/erp-api-upload/`. Each ZIP contains a root folder named `smp-api`; upload SGC's ZIP to SGC and Zalmi's ZIP to Zalmi. Include hidden `.htaccess` files. Read `DEPLOY-CATALOG.md` in each folder.

Both expose `GET /smp-api/v1/catalog.php`. One authenticated response contains all Heads and Subheads, raw `smp_active` strings/NULL, row counts and `complete: true`. Combining both collections in one consistent read-only InnoDB transaction avoids mismatched snapshots. No `smp_active` WHERE filter and no `updated_at` cursor are used: toggles that do not update timestamps are still captured. Public `GET /smp-api/v1/health.php` remains unchanged.

One-time server configuration is required; uploading PHP files cannot create hosting credentials:

1. Configure `SMP_API_DB_HOST`, `SMP_API_DB_PORT`, `SMP_API_DB_NAME`, `SMP_API_DB_USER`, `SMP_API_DB_PASSWORD` on each ERP host. Use a dedicated SELECT-only account for its two catalog tables. No ERP session/bootstrap is loaded. Defaults for table names are `item_location`, `item_sublocation`, and parent column `location_id`.
2. Generate separate strong API tokens for SGC and Zalmi. Set only each token's lowercase SHA-256 hash as `SMP_API_BEARER_TOKEN_SHA256` on its ERP server. Configure the server to forward Authorization to PHP and to confirm HTTPS. The catalog requires bearer authentication; health stays public. `REDIRECT_` environment variants are supported.
3. In the portal's private environment configure bearer references using the existing mechanism, for example:

```dotenv
erp.credentialRefs = '{"sgc_catalog":{"type":"bearer","tokenEnv":"SGC_CATALOG_TOKEN"},"zalmi_catalog":{"type":"bearer","tokenEnv":"ZALMI_CATALOG_TOKEN"}}'
SGC_CATALOG_TOKEN = 'the SGC raw token, kept private'
ZALMI_CATALOG_TOKEN = 'the Zalmi raw token, kept private'
```

Use ERP Sources to select bearer auth and the matching credential reference. Keep each source base URL ending `/smp-api/v1`. Existing approved hosts, public DNS overrides and health paths are reused and unchanged. Catalog adds `/catalog.php` to the base URL. Only approved public HTTPS hosts are permitted; no localhost/private address exception, redirect or TLS bypass was added. Responses are capped at 16 MiB with a 30-second timeout; exceeding either fails without applying a partial snapshot.

## Portal installation and operation

From the portal root, with PHP 8.2+, intl, mbstring, mysqli, PDO MySQL and curl:

```text
php spark migrate
php spark erp:import-legacy-catalog
php spark erp:sync-catalog
```

On this machine intl is disabled in CLI php.ini, so use `D:\xampp\php\php.exe -d extension=intl` in place of `php`. The migration and legacy import have already run locally. Three projects and 37 listings were copied. Repeating the import preserves database records; legacy JSON files remain as an archive. Once migrated, the database is authoritative for project/listing reads and classic/public writes. Other classic collections remain unchanged.

Run synchronization manually under Admin > Synchronization, or use `php spark erp:sync-catalog SOURCE_ID`. For production, configure hosting cron every five minutes (adapt paths/PHP binary):

```cron
*/5 * * * * cd /absolute/path/shalimar-properties && /usr/bin/php spark erp:sync-catalog >> /private/log/path/erp-catalog.log 2>&1
```

No live cron job, ERP credentials or live deployment is created by this change. CLI exits nonzero if any source fails, for scheduler monitoring. The admin view records successes/failures; `last_sync_at` advances only on a successful complete import. A per-source database lock is acquired before fetching, preventing overlapping jobs from applying stale snapshots. Source configuration is checked again under a row lock before commit. Full snapshot updates, missing-row marking, success logging and sync timestamp are one transaction. Failure retains the last complete local snapshot.

Admin > Project Mapping now uses the database. The new mapping dropdown includes only present, active-source combinations whose original values are exactly `yes` and `yes`. The server rechecks eligibility inside a transaction; forged/stale selections cannot bypass it. The unique relational key is `(erp_source_id, erp_head_id, erp_subhead_id)`. Source foreign keys prevent deletion/code changes where already referenced. Existing mappings remain visible/editable when flags change; deactivation preserves records.

Assign each existing listing to its exact mapping using the form on this page. No mapping is inferred from names, listing titles or project membership. Legacy listings without a mapping stay hidden. Local project publication still uses the existing classic Projects workflow. Mapping activation and listing assignment use existing granular permissions; new writes require session CSRF and produce audit events. ERP Sources CRUD, login, users, roles and existing navigation remain intact.

## Visibility and preservation

Public project cards require a published local project with at least one eligible mapping. Public listing lists, cards and direct detail access require that listing's exact mapping and project to be eligible. The predicate requires active source/mapping, present Head/Subhead, and byte-exact `head.smp_active === 'yes' && subhead.smp_active === 'yes'`. Binary SQL comparisons reject `YES`, trailing spaces, NULL, `1`, empty and unexpected strings.

A listing additionally needs an approved/active status (`live` is the existing classic approved/public status) and available/active deal. Sold, cancelled, rejected, pending and removed listings are never reopened by sync. No visibility synchronization writes project publication, mapping activation, listing status/deal or ownership. Going from yes to no hides content after the next successful snapshot; going back to yes restores eligibility only while all local conditions still hold.

Missing records are retained with `present=0`. Reparented Subheads preserve their old combination as absent and create the new combination separately. Orphaned/unassigned Subheads are retained but never eligible without a present Head. This is necessary because the inspected SGC local database already has an orphaned Subhead. The Head screen also shows Heads without Subheads.

`portal_owned_properties` is independent of public visibility; synchronization never deletes or updates it. The customer portal remains the existing sample-only frontend; importing real ownership/authentication is a separate integration, not invented by this change.

Public project/listing requests query only the local portal database. They make no ERP HTTP requests and do not fall back to archived JSON when no eligible mapping exists. Archived design samples under `html/samples` are prototypes, not the production catalog.

## Automated verification

```text
php .qa/verify_erp_visibility.php
php .qa/verify_catalog_apis.php
php .qa/erp_mysql_checks.php
python -u .qa/run_visibility_http.py
python -u .qa/run_visibility_http.py catalog
```

These create randomly named isolated schemas and remove only their own test schemas. Visibility tests cover 64 combinations of yes/no/NULL/unexpected/case/whitespace/numeric-looking/empty strings across strict PHP, mapping options and public SQL; terminal statuses, publication, mapping/source state, raw preservation, complete-empty/missing snapshots, orphaning/reparenting, failed fetches, invalid/incomplete payloads, rollback after an injected DB failure, overlapping jobs and cross-ERP ID isolation. API tests execute both actual API entry points against fixture schemas, verifying raw string/NULL export, no active-only filter, bearer/HTTPS/method enforcement, missing config/schema failures and unchanged health.

The isolated HTTP suite reruns existing administrator/security/ERP CRUD tests and adds catalog RBAC/CSRF checks. The catalog HTTP/browser suite creates and assigns an actual mapping, tests direct public URLs through yes/no/yes transitions and sold-state preservation, then checks desktop/mobile rendering in Edge. Its temporary server, identities, browser profile and database are removed afterward. Screenshots are saved as `.qa/erp-mapping-desktop.png` and `.qa/erp-mapping-mobile.png`.

## Latest connection setup

Separate portal bearer credentials/references have now been configured locally. Updated private upload packages and current connection blockers are recorded in `.qa/erp-api-upload/UPLOAD-NOTES.md`. Both live catalog endpoints currently reject requests with JSON `https_required` (403), while health endpoints return 200. This is not a successful catalog synchronization. The upload now supports additional server-owned TLS indicators and a protected `src/catalog.local.php` deployment file. Zalmi database configuration is still required.

Zalmi deployment path is now configured: `/home/zalmipk_user/www/html/ERP786-ZALMIDEV/includes/config.php`, with the API at `/home/zalmipk_user/www/html/smp-api`. The protected configuration reader extracts literal database constants without executing ERP code; parser and actual API fixture tests pass. Upload is still required, and the previously observed live HTTPS/proxy rejection has not been claimed resolved.
