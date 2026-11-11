# Authorized Agencies — implementation and deployment

Implemented 5 November 2026 in the existing CI4 portal and both downloaded ERP API folders.

## Result

The existing Authorized Agencies navigation now opens a database-backed central directory. ERP agency records remain separate source-specific links until an administrator confirms a mapping. Matching names provide suggestions only. An SGC/105 link and a Zalmi/72 link can share one portal agency without treating their ERP IDs or authorizations as interchangeable.

The top of the admin workspace, including Dashboard and Authorized Agencies, shows a persistent count of currently authorized unmapped agencies for administrators with `agencies.view`. Present links with exact `authorize='yes'` from enabled sources contribute, matching the actual ERP agencies screen. Repeated syncs cannot duplicate notifications. Mapping, authorization revocation, absence after a successful complete sync or source disabling removes the affected link from the pending count. Historical rows and confirmed mappings remain stored. Separate lock/restriction flags do not hide ERP-authorized agencies from administration.

## Inspected ERP schema

Actual SGC table: `agency`. The owner has confirmed that Zalmi uses the same ERP schema.

- Identity/name: `agency.id`, `agency.name`.
- Authorization: **`agency.authorize`**, a `yes`/`no` enum.
- Operational controls: `agency.locked`, `agency.restricted`, also `yes`/`no` enums.
- Contacts: `cell`, `cell2`, `phone`, `phone2`, `email`, `city`, `address`.
- Evidence: `sgcacc/admin/property/add-agency.php` writes `authorize`; the property agency screens inspect it and maintain restriction state. The unrelated `script_authorize`, commission contracts and project authorization detail records are not guessed to be the source-wide authorization switch.

ERP directory authorization follows byte-exact `authorize='yes'` alone, as used by `sgcacc/admin/property/agencies.php:222`. The `locked` and `restricted` columns are separate controls, not additional conditions for the ERP Authorized badge. Customer resale selection retains its existing stricter checks: `authorize='yes'`, `locked='no'`, `restricted='no'`, confirmed mapping, source enabled and local portal agency active. The existing v1 API `authorization_status` is a legacy combined operational status; admin displays and mapping use the original `authorize` string instead. No API deployment or ERP business-code change is required for this distinction.

No portal agency SQL tables existed before this change. The modern screen was a preview. Existing classic JSON party/owner records and historical listing payload references are preserved; sample agencies are not imported or merged into the new directory.

## Database migration

`app/Database/Migrations/2026-11-05-000003_CreateAuthorizedAgencies.php` — **applied locally**, migration batch 5.

| Table/change | Purpose |
| --- | --- |
| `authorized_agencies` | Central name, binary normalized name, local active flag, contact information and timestamps. |
| `agency_erp_links` | Nullable central agency, ERP source/agency identity, ERP name and normalized name, effective/raw authorization, contact JSON, mapped/unmapped state, presence, discovery/seen/mapping timestamps and mapping actor. Unique `(erp_source_id, erp_agency_id)`. |
| `agency_sync_runs` | Separate per-source agency synchronization result/count/history. |
| `portal_listings.portal_agency_id` | Nullable central historical agency reference. Existing rows remain unchanged. |
| `portal_listings.agency_erp_link_id` | Nullable historical source-link reference. Existing rows remain unchanged. |

Foreign keys restrict deletion of referenced central agencies, ERP sources/links and mapping actors. The mapping-state constraint keeps a null central reference unmapped and a confirmed reference mapped. No existing listing status, ownership record or payload is rewritten.

Other portal deployments apply this migration with `php spark migrate`. No ERP database migration is required.

## ERP APIs and deployment status

Implemented in both local API folders:

```text
GET /smp-api/v1/agencies.php?after_id=0&limit=100
Authorization: Bearer <existing server-side credential>
```

The portal uses each enabled ERP Source's existing base URL and credential reference, approved hosts/public DNS pinning, verified HTTPS and disabled redirects. `Config\ErpConnections::$agencyPaths` supplies `/agencies.php` for SGC and ZALMI; a custom source/path can be configured through private `erp.agencyPaths` JSON. No ERP hostname or raw credential is embedded in browser code.

Response example (illustrative agency identity):

```json
{
  "api_version":"v1",
  "agencies":[{
    "id":"105", "name":"Al Madina Estate",
    "authorize":"yes", "locked":"no", "restricted":"no",
    "authorization_status":"authorized",
    "cell":"", "cell2":"", "phone":"", "phone2":"",
    "email":"", "city":"", "address":""
  }],
  "total":1,
  "snapshot_max_id":"105",
  "next_after_id":"105",
  "has_more":false
}
```

Subsequent pages send the returned `snapshot_max_id` and `next_after_id` as `after_id`. Limits are 1–200 rows per API request; the portal reads 100 at a time, with a 10,000-record/source safety bound. All authorization states are exported. Every sync scans the complete bounded ID range instead of using a changed-since cursor that could miss deactivations. The API uses the existing prepared mysqli/PDO SELECT-only adapter. Missing actual authorization columns produce a safe 503; no replacement `active` flag is invented.

The portal validates the complete page sequence, stable upper bound/total, unique ascending IDs and raw/effective authorization agreement before applying anything. A failed later page, inconsistent total, invalid response, changed source configuration or safety-limit breach retains the previous complete local data. Each source is independently locked and committed. An unavailable source does not prevent another enabled source from synchronizing.

During verification the live hosts still served build `2026-11-05.floor.6`; SGC `/agencies.php` returned **404**, Zalmi returned **301**. Therefore live agency synchronization is not claimed to have succeeded. The new implementation needs upload first.

Current packages:

- `.qa/erp-api-upload/sgc-smp-api-agencies.zip`
- `.qa/erp-api-upload/zalmi-smp-api-agencies.zip`

Each ZIP contains root `smp-api/`, all previous catalog/customer/ownership/floor fixes, the new agency endpoint and the matching protected private database settings/bearer hash. Keep the archives private. Upload/overwrite the corresponding host's existing `smp-api` files, including hidden `.htaccess` files and `src/catalog.local.php`; avoid `smp-api/smp-api`. Zalmi's destination remains `/home/zalmipk_user/www/html/smp-api` and its ERP config path is preserved.

After upload health should identify build **`2026-11-05.agencies.7`**. Then open Authorized Agencies and click **Sync Enabled ERP Agencies**. No SSH, server restart, credential reset or live ERP business-data update is required for these API packages. `php .qa/check_customer_api_deployment.php` is an optional local read-only deployment probe.

## Matching and admin workflow

1. Sync discovers each ERP agency as an unmapped source link; it does not create central agencies automatically.
2. Names are normalized only by whitespace trimming/collapse and Unicode lowercasing. Binary comparison retains distinctions such as punctuation and accents. ERP IDs never match across sources.
3. An exact normalized-name match shows a suggestion and requires **Confirm Mapping**. Multiple candidates remain separate and require explicit selection; nothing is preselected as a confirmed mapping.
4. Without a match, the administrator can **Create New Portal Agency and Map**, reviewing name/contact information first. If a matching central name appears before save, duplicate creation is refused and the administrator must select an existing record. Concurrent same-name creates are serialized through commit.
5. ERP renames/status/contact updates refresh only the ERP link. Confirmed central IDs, central contact edits and mapping history remain intact. This version deliberately refuses replacing a confirmed link with a different central agency through an unmapped-agency action.
6. Revocation, local deactivation or absence after a successful full sync removes selection eligibility without deleting central/link/listing history. Source outages retain the last complete snapshot.

The unmapped table shows source, ERP agency name/ID, suggested portal agencies, match status and first-discovered timestamp. Per-link details show original authorization controls and contacts. Central details show all confirmed source links and allow authorized contact/name/status administration.

## Routes and existing RBAC

| Method | Route | Required existing permission(s) |
| --- | --- | --- |
| GET | `admin/agencies` | `agencies.view` |
| GET | `admin/agencies?tab=unmapped` | `agencies.view` |
| GET | `admin/workspace/agencies` | Preserved alias; redirects to the directory. |
| GET | `admin/agencies/{id}` | `agencies.view` |
| GET | `admin/agency-links/{id}` | `agencies.view` |
| POST | `admin/agency-links/{id}/map` | `agencies.edit` |
| POST | `admin/agency-links/bulk-review` | `agencies.edit` |
| POST | `admin/agency-links/bulk-map` | `agencies.edit` |
| POST | `admin/agency-links/{id}/create` | `agencies.create` **and** `agencies.edit` |
| POST | `admin/agencies/{id}` | `agencies.edit` |
| POST | `admin/agencies/{id}/status` | `agencies.deactivate` |
| POST | `admin/agencies/sync` | `erp_sync.run` **and** `agencies.edit` |
| POST | `admin/agencies/sync/{sourceId}` | Same sync permissions. |

All mutations retain existing CSRF/admin authentication enforcement. No new role grant, public bypass, admin account or permission-reset seeder is needed. Mapping/create/update/status/successful-sync actions use the existing audit service. Mapping records include actor, link, old/new central IDs and ERP source/agency IDs; private ERP credentials and raw contact payloads are not logged.

Optional CLI: `php spark erp:sync-agencies` or `php spark erp:sync-agencies SGC`. It uses enabled Sources and the same synchronization service; no scheduler was silently installed.

## Customer resale selection

The existing verified property review now contains the real local agency selector. Only mapped, present, portal-active agencies authorized for the **property's own source** appear. Unmapped links and agencies authorized only in the other ERP do not appear.

- `GET customer/api/agencies?property={opaque-property-reference}` returns source-specific eligible choices after checking current ownership/eligibility.
- `POST customer/resale/agency` accepts `reference` and `agency_link_id`; it re-fetches ownership, derives the source server-side, rechecks authorization/local active state and validates the prepared review expiry.
- The confirmed choice is stored only in the authenticated prepared-review session. No portfolio import, booking update, pretend listing submission or bulk save occurs.

The previously unavailable full listing submission workflow is unchanged. A future actual listing writer must recheck `AgencyDirectory::requireEligible()` inside its save workflow and persist both historical IDs. Existing classic listings and their legacy party references remain intact.

## Files

New portal implementation:

- `app/Database/Migrations/2026-11-05-000003_CreateAuthorizedAgencies.php`
- `app/Services/AgencyDirectory.php`, `AgencySync.php`, `ErpAgencyApi.php`
- `app/Controllers/Admin/Agencies.php`
- `app/Commands/ErpSyncAgencies.php`
- `app/Views/admin/agencies/alert.php`, `index.php`, `link.php`, `show.php`

Updated portal integration:

- `app/Config/Routes.php`, `Services.php`, `ErpConnections.php`
- `app/Filters/AdminPermission.php`
- `app/Controllers/Admin/Workspace.php`, `app/Helpers/admin_helper.php`, `app/Views/admin/layouts/main.php`
- `app/Services/AdminAuditService.php`
- `app/Controllers/Customer/Resale.php`, `app/Views/customer/resale/ownership_review.php`, `public/assets/customer/js/customer-live.js`
- `app/Models/ErpSourceModel.php`: restrict reference metadata discovery to the current portal schema. This retains source-reference protection while avoiding scans of unrelated legacy databases. The original broader query triggered a local MariaDB crash on corrupt pre-existing test tables during regression. MySQL was restarted normally, and source CRUD/reference checks then passed; no ERP/portal table was repaired/replaced or business row deleted.

In each ERP API folder:

- New: `src/AgencyController.php`, `v1/agencies.php`, `AGENCIES-API.md`.
- Updated: `index.php`, `src/routes.php`, `src/HealthController.php`, `README.md`.
- Existing private configuration/credentials and business bootstraps are preserved.

Tests/verification:

- `.qa/agency_service_checks.php`
- `.qa/agency_test_router.php`, `.qa/agency_http_fixture.php`, `.qa/verify_agency_http.py`
- Updated `.qa/verify_customer_apis.php`, `.qa/check_customer_api_deployment.php`.
- Scoped RBAC test sessions to disposable directories in `.qa/visibility_test_env.php`, `.qa/rbac_fixture.php`, `.qa/run_visibility_http.py`, so expiry tests do not touch real admin sessions.
- Updated `CUSTOMER-ERP-API-CONTRACT.md`, `CUSTOMER-ACTIVATION.md`, `CUSTOMER-PORTAL.md`, `CATALOG-IMPLEMENTATION-OVERVIEW.md` and upload notes with the combined package/version.

## Verification

Passed:

```text
php .qa/agency_service_checks.php
php .qa/verify_customer_apis.php
python -u .qa/verify_agency_http.py
python -u .qa/run_visibility_http.py
python -u .qa/verify_customer_live.py
```

- All ten requested scenarios: new SGC/no match; cross-ERP same-name suggestion; differing ERP IDs; multiple candidates/explicit selection; repeated sync without duplicate alerts; ERP rename; single-source revocation; admin confirmation; unmapped exclusion; confirmed mapping preservation.
- Additional checks: all 64 raw authorization/lock/restriction combinations, pagination/missing-column/mysqli/PDO/API bearer/write refusal, failed later page/rollback, portal-disable and absent-record behavior, retained listing history, audit fields, guest and forged-role denial, separate create/edit/sync permissions, CSRF, escaping, source-derived customer validation and revocation after a page loads.
- Real admin/customer HTTP and Edge desktop/mobile rendering, plus the existing full admin/RBAC/ERP Sources/classic/customer regression suites, including project-mapping permissions and CSRF checks.
- PHP syntax, JavaScript syntax and upload ZIP contents/integrity.

Tests use disposable schemas and test-only transport/SMS fixtures. No production OTP bypass, fake agency directory import, real SMS or ERP agency write was used. The migration is applied locally; live agency data becomes available only after the agency API packages are uploaded and synchronization succeeds.


## Bulk review and confirmation (5 November 2026)

The Unmapped Agencies tab now supports 25/50/100 rows per page, per-row selection, a Select Suggested Matches shortcut, Clear Selection and a final review page. Unique matches can be proposed in the dropdown but remain unchecked until the administrator selects them. Multiple candidates begin with an empty choice and need an explicit dropdown choice plus selection. No-match records can now be explicitly selected as Create New Portal Agency when the administrator has agencies.create permission. They are created only after the final reviewed confirmation; sync and preview never create them automatically.

Workflow: select rows, click Review Selected, inspect source/ERP agency/selected central agency/authorization, then Confirm Mappings. Nothing is written during review. Review sessions are bound to the current admin account/session, expire after ten minutes, and use single-use random tokens plus the existing CSRF checks. Any new review attempt invalidates the previous pending review.

The server revalidates exact current normalized-name matches and a fingerprint of relevant ERP/central name, authorization, presence and local active details before applying a batch. Changed details or conflicting confirmations abort the batch. Unchanged periodic sync timestamps do not invalidate a review. At most 100 links are locked in stable order and mapped in one local database transaction with per-link audit records. Any stale/invalid later row rolls back earlier mapping and audit writes. Already-confirmed identical mappings are idempotent; different confirmed mappings are never overwritten. Unselected links are untouched.

New files: app/Views/admin/agencies/bulk_review.php and public/assets/admin/js/agency-bulk.js. Updated files: AgencyDirectory.php, Admin/Agencies.php, admin/agencies/index.php, Config/Routes.php, Filters/AdminPermission.php and the agency service/HTTP tests. No new migration, ERP endpoint or API ZIP is needed; this feature only reads/writes the local portal database after explicit admin confirmation.

Passed service and actual HTTP/browser tests: review without writes, 100-row limit, empty/duplicate/forged selections, explicit ambiguous choice, selected-only mapping, idempotency, stale-review rollback including audits, cross-admin-session token rejection, replay rejection, RBAC/CSRF on both stages, page-size retention, unique-match selection leaving no-match rows untouched, real browser review/confirmation, persistent-alert clearing, desktop/mobile layout and preservation of the existing agency/customer workflow. Screenshots remain .qa/agency-admin-desktop.png and .qa/agency-admin-mobile.png.


## No-match checkbox correction (5 November 2026)

The reported disabled checkboxes were explained by the real directory state: 180 pending ERP links and zero existing central-name matches. The earlier suggested-match-only control disabled all those rows.

No-match rows now expose an explicit Create New Portal Agency choice and a usable checkbox for administrators with both agency edit/create permissions. Select All Ready Agencies selects matching rows and one representative per new normalized name on the current page. Select Suggested Matches remains available for existing matches only. The review page labels each action as either mapping an existing central ID or creating a new central agency; no database write happens before confirmation.

Mixed create/map batches remain atomic, limited to 100, actor/session/CSRF protected and audited per ERP link. Creation permissions are checked at preview and confirmation. New central contact information is proposed from the source contact JSON; invalid emails remain available in the source link but are not copied as valid central emails. Existing central matches appearing after review cause a fresh-review error, never silent merging or duplicate creation. Name locks are held through commit. Same-name new records are left unchecked by Select All Ready; after one central record is explicitly created, the remaining links can be reviewed as existing-name matches.

Passed the updated service suite and real HTTP/browser suite: no-match selection, explicit create preview without writes, mixed create/map confirmation, permission enforcement, duplicate-new-name rejection, rollback of central/link/audit writes, a central name appearing after review, existing mapping preservation, desktop/mobile layout and unchanged customer source-specific authorization. This is a local portal change; no ERP API upload or schema migration is required.

## Authorized-only display correction (5 November 2026)

Per the owner's clarification, the main central directory and Unmapped Agencies queue display only currently authorized agencies from enabled ERP sources. Central records require at least one present, confirmed ERP link with `authorize='yes'`; independently authorized links in another ERP keep the central agency visible. Local-disabled central agencies remain manageable in this authorized directory but cannot be selected by customers. The initial additional lock/restriction filter was corrected in the reconciliation below.

Dashboard alerts, pending tab counts, pagination totals, authorized-link counts and sync completion summaries use the same current authorization scope. The sync success summary reports authorized agencies and those requiring mapping; the sync run record_count still records all source records checked. Background synchronization continues reading all statuses to detect revocations reliably. No ERP endpoint, upload ZIP or migration is changed.

Individual creation/mapping and bulk review/confirmation reject unauthorized, unknown, absent or source-disabled ERP records, including authorization revoked after preview. Existing historical detail links remain readable for audit; unauthorized unmapped details offer no mapping forms. Revocation never deletes agencies, mappings, customer ownership or listing history. Name suggestions may reuse an existing historical central agency when explicitly linking a new authorized ERP record, avoiding duplicate central agencies.

Updated portal files: app/Services/AgencyDirectory.php, app/Services/AgencySync.php, app/Controllers/Admin/Agencies.php, app/Commands/ErpSyncAgencies.php, app/Views/admin/agencies/index.php and app/Views/admin/agencies/link.php. Verification covers all 64 yes/no/NULL/unexpected flag combinations, filtered counts, forged mapping/create actions, review-time revocation, source disable, cross-ERP authorization, preserved mapping restoration, and actual HTTP/admin/browser workflows using isolated fixtures.

## ERP authorization count reconciliation: 51 versus 15 (5 November 2026)

The actual ERP `admin/property/agencies.php` selects `agency WHERE authorize='yes'`; it does not filter `locked` or `restricted`. Read-only aggregation of the synchronized local SGC data found 51 authorize=yes agencies: 15 restricted=no and 36 restricted=yes. The additional portal restriction condition caused the incorrect 15 display.

Admin directory membership, unmapped counts/alerts, mapping eligibility, sync summaries, review authorization labels and historical detail badges now follow the raw ERP authorize flag alone. The existing deployed API already exports this raw flag, so no API upload, live SQL update, token change or migration is needed. Its combined authorization_status field remains validated for compatibility and continues serving the existing customer operational checks; it no longer determines the ERP badge or admin mapping eligibility.

Current read-only reconciliation after the fix: 51 authorized ERP links, 28 confirmed central agencies and 23 authorized links requiring mapping. A dynamic summary above the tabs shows total authorized ERP records, mapped links and pending mappings; central directory pagination still counts central records rather than silently including unconfirmed agencies. No real pending links were auto-confirmed and no historical mappings were rewritten.

Updated files for this correction: app/Services/AgencyDirectory.php, app/Controllers/Admin/Agencies.php, app/Views/admin/agencies/index.php, app/Views/admin/agencies/link.php, app/Views/admin/agencies/show.php, plus service/HTTP tests and the CLI-only read-only diagnostic .qa/agency_counts.php. Tests distinguish ERP authorization across all 64 flag combinations from the existing customer resale restrictions, and exercise explicitly mapping ERP-authorized restricted/locked agencies without bypassing customer checks.
