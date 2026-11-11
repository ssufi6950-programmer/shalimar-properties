# Shalimar Agents synchronization and cross-ERP resale assignment

Implemented 5 November 2026 in the existing CI4 portal and accessible SGC `smp-api` folder.

## Result

Admin → Shalimar Agents now opens a real local agent directory, with Internal Agency configuration, manual sync, source/dealer/ledger search, local enable/disable, contact approval, last successful sync, sync history and assigned SGC/Zalmi listings. The existing premium layouts, authentication, permission registry, ERP Sources, catalog mapping and external agency workflows are retained.

The customer resale form now offers Self Sell, Through Shalimar Agent and Through Authorized Agency. It uses the existing four-step design and saves a real pending-approval listing only after final ownership/eligibility verification. A single SGC-backed portal agent can handle both SGC and Zalmi properties. External agency options remain restricted to the property's own source and exclude the internal Shalimar agency.

## Actual schema and identifiers

- SGC agency table: `agency`; confirmed internal source agency ID: **193**.
- SGC dealer entity: a `ledger` record with `dealer='yes'`; agency membership is `ledger.agency=agency.id`.
- Stable imported identity: **(erp_source_id, erp_ledger_id)**. There is no separate dealer ID proven by this schema. The API declares identity_type=ledger and dealer_id=null; the portal preserves that distinction.
- Operational agent eligibility requires dealer=yes, ledger locked=no and internal agency authorize=yes/locked=no/restricted=no, plus enabled source/current configuration/current membership and local portal enablement.
- General ERP agency-directory authorization continues to follow authorize=yes alone. Its separate lock/restriction controls do not hide agencies from the admin directory.

The actual local SGC copy contains Agency 193, **Shalimar Properties**, and ledger **11762**, **Shalimar agent**. A read-only call through the implemented local HTTP dealer API imported this as **Portal Agent 1**, linked to central **Portal Agency 86** (resolved dynamically). Configured source ID is currently 1, from the existing SGC source; no source or central ID is hardcoded in the implementation.

**11770 mapping result:** 11770 was supplied as a confirmed live example, but is absent from the local ERP copy. It was not created, renumbered or substituted. Isolated service/API/HTTP tests prove 11770→a stable portal agent using actual ledger-backed response contracts. Production sync will import the live record only if the live API proves dealer=yes and Agency 193 membership. Its portal ID need not be 1 when other dealers were discovered earlier.

## Internal agency configuration

`shalimar_agent_settings` is a local singleton configuration. Initial source is resolved by ERP source code SGC when available; initial ERP agency ID is 193. The admin can change the SGC agency ID and select an existing central portal agency when resolving ambiguity.

A changed configuration becomes unavailable for assignments until a successful complete dealer sync verifies it. The configured source must be enabled and have code SGC; a numeric agency ID from Zalmi never satisfies it. Source/config changes during fetching reject the entire snapshot.

The configured, verified ERP agency is linked using the existing `agency_erp_links`. Its central agency is classified agency_type=internal, is_shalimar_agency=1, portal_active=1 at initial explicit setup. Subsequent sync does not undo local disabling. Existing confirmed mappings are never silently remapped. The authorized business setup for this one confirmed internal agency can reuse the unique central Shalimar Properties record; this does not introduce automatic name-based mapping for other agencies. Ambiguous central matches require administrator selection.

## Synchronization

New endpoint: **GET /smp-api/v1/dealers.php?agency_id=193&after_id=0&limit=100**, using the existing SGC bearer credential and source configuration. Missing before implementation; now implemented in the accessible SGC API. No Zalmi dealer endpoint is required or invented.

The portal client reuses existing verified HTTPS, approved public host/address checks, private credential references, timeouts and no-redirect behavior. Production never calls a loopback API. A protected CLI-only helper was used to import the actual local ERP copy through a temporary authenticated read-only HTTP API for local setup.

Snapshots include locked/inactive dealer members, raw status strings, real names/mobile/email and optional updated timestamp. A former dealer or removed member becomes absent only after complete successful pagination. The portal validates identity type, exact agency scope, eligibility proof, ascending unique ledger IDs, stable maximum/total and complete record count, with bounded pages/records.

Each successful snapshot applies in one local transaction with per-source locking, stable source-ledger upserts, internal agency verification, audit record and sync history. Repeated sync preserves portal agent IDs, local disabling and contact approval for unchanged contacts. New ERP contacts clear approval until an administrator approves them. Same names with different ledger IDs remain separate agents. Failed/incomplete/invalid API responses retain previous agent state and assignments.

## Assignment and persistence

Customer selection uses `portal_agent_id`. Server-side assignment validates a currently eligible configured SGC agent for either an enabled SGC or Zalmi property. It never asks for or creates a Zalmi dealer account.

Final customer POST verifies:

1. Existing authenticated customer, CSRF and prepared property reference/submission key bound to that account/session.
2. Fresh ERP ownership/booking approval through the unchanged customer identity and portfolio services.
3. Exact current source/head/subhead project mapping, published project, active mapping/source and exact yes/yes visibility flags under local database locks.
4. No existing active/pending listing for the same source/property, with the existing unique generated-key constraint.
5. A valid price, bounded remarks, explicit final confirmation and current selected agent/external agency eligibility under locks.

It then atomically inserts one pending listing and upserts only its selected property in `portal_owned_properties`. Preparing a form, listing agents, logging in and synchronizing never bulk-import properties. Replays of the same server-issued submission key return the owned result without duplicate writes. Failed submissions save neither a property nor a listing.

Agent listing fields include selling_method=shalimar_agent, portal_agent_id, agent_erp_link_id, agent_source_id and immutable agent_snapshot (source=SGC, real ledger ID, nullable independent dealer ID, original agent name and approved contact details). Original booking agent/agency remains a separate property/payload field. A Zalmi property's erp_source_id remains Zalmi while agent_source_id remains SGC.

**Official ERP posting is not implemented or performed.** No SGC ledger ID is ever submitted as a Zalmi ledger ID. A future ERP transaction adapter must resolve its own verified destination ledger separately. Listings remain pending until the existing moderation flow approves them; this feature does not publish/reopen sold, rejected or cancelled records.

## Inactive agents and review

Source/agency membership, agent/agency operational status, local agency enablement and agent enablement are rechecked for new selections/submissions. Successful agent/agency sync or local agent/internal-agency disabling flags affected active/pending assignments for administrator review. Historical links, name/contact snapshots, property ownership and listing statuses remain intact. Terminal sold/cancelled/rejected records are not reopened.

The dashboard and Agents page show a persistent review count. Restoring eligibility does not silently clear review flags; an administrator can confirm the now-eligible historical assignment without changing its listing status. API failure alone does not deactivate a valid agent.

## Migration

`app/Database/Migrations/2026-11-05-000004_CreateShalimarAgents.php` — applied locally.

- Adds agency_type and is_shalimar_agency to authorized_agencies; existing rows default external.
- Creates shalimar_agent_settings, shalimar_agents, agent_erp_links and agent_sync_runs with source/agent/agency foreign keys and unique (source, ledger) identity.
- Extends portal_listings with selling method, historical agent references/snapshot, review flags/reason, asking price, submission key and timestamps; enables safe AUTO_INCREMENT IDs while preserving old IDs.
- Preserves existing customer/source/project tables, ownership records and existing listing data.

Actual local setup after import: **1 agent**, **0 customer-owned properties imported**, existing **37 listings unchanged**. No ERP schema or agency/dealer business record was modified by this implementation.

## Admin routes and permissions

| Route | Existing permissions |
| --- | --- |
| GET admin/agents, admin/agents/{id} | agents.view |
| POST admin/agents/sync | erp_sync.run + agents.edit |
| POST admin/agents/configuration | erp_sync.manage_settings + agents.edit + agencies.edit |
| POST admin/agents/{id}/status | agents.deactivate |
| POST admin/agents/{id}/contacts | agents.edit |
| POST admin/agents/listings/{id}/review | resale_listings.edit + agents.view |

Assigned listing details are shown only with resale_listings.view. Sync history is shown only with erp_sync.view_logs. All admin mutations require existing CSRF protection and record audit logs. No role resets or new permission seeding is required.

Customer routes: GET customer/api/agents?property={opaque-reference}; POST customer/resale/submit; existing resale create/prepare/agency/details/success routes are reused. Agent options expose local agent ID/name/approved contact only, not internal ERP ledger/customer identity. Customer routes retain existing access and CSRF filters.

Optional CLI synchronization: `php spark erp:sync-agents`. No scheduler was installed.

## Files

Created:

- app/Database/Migrations/2026-11-05-000004_CreateShalimarAgents.php
- app/Services/ErpDealerApi.php, ShalimarAgents.php, ShalimarAgentSync.php, ResaleSubmission.php
- app/Controllers/Admin/Agents.php; app/Commands/ErpSyncAgents.php
- app/Views/admin/agents/index.php and alert.php
- app/Views/customer/resale/live_create.php and submitted.php
- public/assets/customer/js/resale-live.js
- SGC smp-api/src/DealerController.php, v1/dealers.php, DEALERS-API.md
- Protected .qa service/API/HTTP/browser fixtures, read-only inspection/local bootstrap and package helpers; upload notes below.

Modified:

- app/Config/ErpConnections.php, Services.php, Routes.php
- app/Services/ErpAgencyApi.php (shared protected directory GET transport), AgencyDirectory.php, AgencySync.php, AdminAuditService.php
- app/Controllers/Customer/Resale.php; app/Controllers/Admin/Workspace.php
- app/Filters/AdminPermission.php; app/Helpers/admin_helper.php
- app/Views/admin/layouts/main.php; app/Views/admin/agencies/show.php
- app/Views/customer/resale/live_index.php; public/assets/customer/js/customer-live.js
- SGC smp-api/index.php, src/routes.php, src/HealthController.php (build agents.8)
- Existing relevant .qa fixtures/checks updated for the new migration and real listing form.

## Upload

SGC package: [.qa/erp-api-upload/sgc-smp-api-agents.zip](.qa/erp-api-upload/sgc-smp-api-agents.zip).

Instructions: [.qa/erp-api-upload/UPLOAD-SHALIMAR-AGENTS.md](.qa/erp-api-upload/UPLOAD-SHALIMAR-AGENTS.md).

Merge the package into the existing live SGC smp-api folder, retaining its private src/catalog.local.php (excluded from this ZIP). Include hidden .htaccess files. New health build is 2026-11-05.agents.8. Then use Admin → Shalimar Agents → Sync Shalimar Agents. No SSH/server configuration/token reset, Zalmi API upload, or ERP database migration is required.

## Verification

Passed service checks cover the requested 17 scenarios and additional cases: unique ledger identities/same-name dealers, cross-source selection and persisted SGC provenance, internal agency exclusion, reconfiguration verification, missing/removed/locked/ineligible agent rejection, repeated sync, new agents, API outage/partial snapshot preservation, local enablement, contact reapproval/immutable snapshots, pending-only listings, duplicate prevention/idempotency, source-specific external submission and explicit review without terminal-state changes.

The actual HTTP/RBAC/CSRF/Edge suite passes: existing admin/customer authentication, forged permission and token denials, source-verified configuration, persistent external agency alerts, admin search, approved contact exposure, fresh ownership/approval checks on final submission, SGC JSON submission and native four-step Zalmi browser submission sharing the same portal agent and SGC ledger, persistent review alerts and historical assignments. Desktop/mobile layouts fit and no uncaught browser errors were found.

The authenticated API suite exercises the real SGC dealer PHP endpoint using the real SQL adapter, agency scoping, locked/former dealers, raw flags, pagination, bearer/write/bounds checks and existing SGC/Zalmi customer/agency/floor/ownership regressions. ERP rows remain untouched. All automated OTP delivery uses injected fixtures; no actual SMS or ERP accounting write is sent.

Commands: php .qa/shalimar_agent_checks.php; php .qa/verify_customer_apis.php; python -u .qa/verify_shalimar_agents_http.py; php .qa/agency_service_checks.php. Existing full agency, customer and admin/RBAC/visibility regression results are recorded below after completion.

Completed regression results: **PASS** `python -u .qa/verify_agency_http.py`, **PASS** `python -u .qa/verify_customer_live.py`, **PASS** `python -u .qa/run_visibility_http.py`. These cover existing agency bulk creation/mapping and alerts, customer OTP/session/ownership/floor/source-retry/browser flows, existing ERP Sources CRUD and catalog mapping, admin RBAC/CSRF/user/role/session controls, classic modules, and all registered workspace pages. Disposable test schemas/session directories were removed; real users, ownership, ERP data and audit history were retained.

PHP/JavaScript syntax checks and the SGC upload archive integrity/private-config exclusion passed. The package contains 31 files rooted at smp-api/. Browser screenshots: .qa/shalimar-agents-admin-desktop.png, .qa/shalimar-agents-admin-mobile.png, .qa/shalimar-resale-desktop.png and .qa/shalimar-resale-mobile.png.
