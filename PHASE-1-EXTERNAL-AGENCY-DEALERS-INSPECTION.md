# Phase 1 — External Agency Dealers: Inspection and Architecture

Prepared 14 November 2026. Inspection only; no code, database, ERP or live changes were made for this report.

## Executive summary

The current agency portal authenticates one central portal agency and shows its agency-wide eligible listings. It has no external dealer directory, dealer accounts, dealer assignments or dealer-specific portal session. The current SGC and Zalmi `dealers.php` handlers are for Shalimar staff discovery, not external agency dealers.

Both local API controllers currently show the same schema assumptions: external dealer candidates appear to be rows in `ledger`, with the ledger primary key as identity and `ledger.agency` as the agency reference. That relationship is strongly indicated by the API query and existing ERP ledger conventions, but the actual ERP database schema and live row behavior were not independently queried in this turn. Consequently, `ledger.dealer='yes'` as the external-dealer eligibility rule, status semantics, and transfer/removal behavior remain **UNVERIFIED**. Do not implement an endpoint or SQL until those are checked against each actual database and ERP screens/business rules.

## Verified existing implementation

### ERP API behavior, separately inspected

Local API folders:

- SGC: `D:/xampp/htdocs/sgcacc/smp-api/`
- Zalmi: `D:/xampp/htdocs/sgcacc/zalmi-smp-api/`

Both `src/DealerController.php` implementations:

- Expose authenticated GET `/v1/dealers.php`, routed by `src/routes.php`.
- Require `agency_id`, but accept only the configured Shalimar business agency (`193` in SGC, `2` in Zalmi); any other ID returns `invalid_agency`.
- Query `ledger` using `BINARY sp_Agent = BINARY 'yes'`; this is the Shalimar staff selector.
- Use `ledger.id` as returned `ledger_id`, but set `dealer_id` to null.
- Return ledger `agency`, `dealer`, `locked`, name and optional `mobile`, `email`, `image`, `updated_at` fields.
- Return paginated snapshots (`snapshot_max_id`, `after_id`, limit at most 200).
- Mark staff active when `sp_Agent='yes'` and `locked='no'`; overall eligibility additionally requires the configured Shalimar agency to have `authorize='yes'`, `locked='no'`, `restricted='no'`.
- Use a prepared SELECT-only database wrapper (`src/CustomerDatabase.php`); the handler does not write ERP records.

This endpoint cannot fetch external dealers by the requesting agency because its query neither filters ledger rows by `ledger.agency` nor accepts arbitrary agency IDs. Its hard check explicitly rejects an external ERP agency ID.

### Current portal agency authentication and portfolio

- Agency login: `app/Controllers/Agency/Auth.php`, `app/Services/AgencyPortalSession.php`.
- Routes: `app/Config/Routes.php`, group `agency`, guarded by `AgencyPortalAccess`.
- Login uses a normalized username, `password_verify`, Argon2id/Bcrypt hash generation for provisioning, IP/account throttling, regenerated session IDs, auth-version checks, idle/absolute expiry, and eligibility revalidation on each loaded session.
- Agency account table: `agency_portal_accounts`, unique account per `portal_agency_id`, foreign-keyed to `authorized_agencies`. It stores a hash, not a recoverable password.
- Current portal account is central-agency scoped; it is not a dealer login.
- Agency ERP sources resolve through `agency_erp_links` joined to `erp_sources`; eligible external agencies require portal-active external status and at least one present, mapped, active-source, authorized/unlocked link.
- Portfolio: `app/Controllers/Agency/Listings.php` and `app/Services/AgencyPortalListings.php`.
- Listings are local `portal_listings` joined through the saved project mapping and exact agency ERP link. Public listing policy and ERP visibility rules are applied. External-agency results require `portal_agency_id` to match the signed-in central agency and the exact saved agency link/source.
- `decorate()` returns a whitelist of public listing fields and approved public images; it removes the full payload. No customer CNIC, OTP or ownership proof is included.
- Existing agency portal has read-only portfolio pages today; there are no dealer-management or assignment routes.

### Relevant local portal tables confirmed from owning code/migrations

The code and migrations establish these existing tables and purposes:

| Table | Existing purpose |
|---|---|
| `authorized_agencies` | Central agency record/type/active state |
| `agency_erp_links` | Unique source-specific ERP agency identity, mapping and presence |
| `agency_portal_accounts` | One username/password-hash account for a central agency |
| `agency_login_attempts` | Agency login rate limiting |
| `portal_listings` | Locally submitted/public resale or rental listing records and exact agency/agent references |
| `erp_sources` | SGC/Zalmi connection identities and enablement |
| `admin_audit_logs` | Existing Admin audit trail |

The complete current live `SHOW CREATE TABLE` output for all these tables was not collected in this continuation. Before implementation, inspect the target database's actual DDL, indexes, engines and foreign keys; do not treat the migration files alone as proof of live schema.

## SGC external dealer findings

### Verified from local SGC API source

`D:/xampp/htdocs/sgcacc/smp-api/src/DealerController.php` requires `ledger.id`, `name`, `agency`, `dealer`, `locked`, and `sp_Agent`; optional `mobile`, `email`, `image`, `updated_at` are returned if present. It queries the ERP `agency` table for the Shalimar business agency's authorization state. The current query selects all `sp_Agent='yes'` ledgers and does not filter on `ledger.agency`.

### Strong candidate external-dealer model, not yet verified

- Candidate table: `ledger`.
- Candidate stable identity: `ledger.id`.
- Candidate agency relationship: `ledger.agency` references `agency.id`.
- Candidate external dealer flag: `ledger.dealer='yes'`.
- Candidate lock flag: `ledger.locked`; exact accepted string and its business meaning must be verified.
- Candidate business fields: `ledger.name`, `mobile`, `email`, `image`.

The API source does not prove external dealer eligibility. It only proves the staff selector and exposes the candidate fields. SGC Core PHP screens/model and the live database must confirm the relationship, flag semantics, active rules, company scoping and image safety before these candidates become an endpoint contract.

## Zalmi external dealer findings

### Verified from local Zalmi API source

`D:/xampp/htdocs/sgcacc/zalmi-smp-api/src/DealerController.php` has the same source-level requirements and staff query as SGC, while its configured Shalimar business agency ID is `2` rather than `193`. It requires candidate `ledger.agency`, `dealer`, `locked` and `sp_Agent` fields and optionally selects `mobile`, `email`, `image`, `updated_at`.

### Unverified Zalmi details

Although the API implementation resembles SGC, this does not establish that the Zalmi ERP live schema or business rules are identical. Its own Core PHP screens/schema must independently verify whether `ledger.agency` is a direct single agency membership, whether dealer rows may have another relationship, the `dealer` flag values, company boundaries, locking/authorization rules, and how reassignment/removal is represented.

## Required evidence before Phase 2 endpoint work

For each ERP independently, inspect read-only:

1. `SHOW CREATE TABLE ledger` and `SHOW CREATE TABLE agency` (plus any dealer-specific tables discovered by schema search).
2. `INFORMATION_SCHEMA` indexes and foreign keys for `ledger.agency` and dealer-related columns.
3. Core PHP dealer listing/edit screens and save handlers; identify their exact predicates and whether company ID is part of identity/membership.
4. A few redacted examples for dealer=yes/no, locked, active/disabled, agency membership and any transfer/deletion marker. Never include names, phones, credentials or private data in this report.
5. Whether ledger membership changes update `ledger.agency` in place or create a new ledger identity; whether deleted rows remain available to a complete API snapshot.

Without this evidence, external dealer eligibility and revocation cannot safely be encoded. If these ERP databases cannot be queried from the local environment, the precise blocker is access to each ERP's read-only schema/data; provide the above DDL and relevant application predicates for both systems.

## Proposed design (not implemented)

### Read-only ERP APIs

Add a separate endpoint in each ERP API package, such as `GET /v1/agency-dealers.php`, leaving `/dealers.php` unchanged. The API should:

- Require existing bearer authentication and GET only.
- Accept a validated ERP agency ID and bounded snapshot cursor/page size.
- Verify that the agency exists and apply the ERP-verified agency and external-dealer eligibility predicate.
- Return only source identity, agency identity, safe business name/contact/photo filename, original eligibility flags needed for reconciliation, stable update marker and pagination metadata.
- Use fixed allowlisted SQL/table/column names and prepared values; return no customer, financial, password or ownership fields.
- Produce a complete snapshot marker so the portal reconciles missing membership only after every page succeeds.

Do not just remove the `BUSINESS_AGENCY_ID` guard from the staff endpoint: that would mix internal staff with external dealers and alter established semantics.

### Minimal portal data model (draft concept only)

The current portal has no external-dealer or dealer-assignment tables. Keep external dealers separate from `shalimar_agents` and `agent_erp_links`.

Proposed tables:

1. `external_dealers`: central local profile (`id`, display name, portal active, timestamps). Do not auto-merge same names.
2. `external_dealer_erp_links`: one source identity (`erp_source_id`, `erp_ledger_id`, optional ERP dealer key only if the actual schema proves one), current `erp_agency_id`, present/eligible flags, safe profile fields, last seen/sync and unique `(erp_source_id, erp_ledger_id)`. A changed agency membership updates/revokes the prior membership state while preserving history.
3. `agency_dealer_accounts`: one dealer login (`external_dealer_id`, unique normalized username, password hash, enabled, must-change/reset expiry, auth version, timestamps). No ERP password is stored. If one person is source-specific and not confirmed across sources, keep separate local identities/accounts unless Admin explicitly links them.
4. `agency_listing_dealer_assignments`: listing ID, central agency ID, exact `agency_erp_link_id`, listing ERP source, exact dealer ERP link, active/revoked state, actor and timestamps; enforce one active assignment per listing with a safe unique strategy supported by MariaDB version. Keep immutable assignment events in a separate history table or append-only audit record.

Foreign keys must preserve agency/listing history (`RESTRICT` rather than cascading deletion). Index tenant, source, dealer and active assignment lookup paths. Final DDL requires actual live schema review and should be delivered as idempotent manual SQL before dependent implementation.

### Sync on agency login

Recommended: agency login succeeds first and never waits on ERP. Queue a bounded sync job if the project already has a reliable worker. No existing job/queue worker was verified in the inspected routes/services, so the minimal initial option is a throttled, bounded post-login sync request with short connect/total timeouts and a freshness lock; redirect immediately and report sync status on the dealer page. Do not run the network call inside every page request. If no queue can run reliably on the host, use a deferred portal request with a per-source lock and freshness window, preserving prior rows on failure. Reconcile absent dealers only after a complete successful paginated source snapshot.

For a central agency linked to both sources, sync each `agency_erp_links` row independently using that row's `erp_source_id` and `erp_agency_id`. Never reuse agency IDs across sources.

### Agency dealer management and property assignment

Agency dealer UI should list only source links belonging to the signed-in central agency's currently eligible `agency_erp_links`. Credentials are separate local dealer credentials, not ERP credentials. Agency users can create/reset/disable their own dealer accounts and assign only eligible listings in their current portfolio.

Assignment transaction must verify all relationships server-side in one transaction:

- Listing passes the existing public/active/approved eligibility query.
- Listing's central agency and exact `agency_erp_link_id` belong to the authenticated agency.
- Listing ERP source equals the dealer link's ERP source.
- Dealer link's current ERP agency membership equals the exact source-specific ERP agency link for that same central agency.
- Dealer remains present and eligible, and the dealer account is enabled.
- Lock the listing/assignment row to prevent competing active assignments; revoke old assignment and insert new history atomically.

Do not change `portal_listings` public representative fields. Internal dealer assignment is a separate portal relationship and must not alter public agency/agent contact.

### Dealer login and view-only access

Use a distinct session namespace/filter, not the existing agency account session. Passwords use one-way hashes; reset uses short-lived random temporary credentials with mandatory change, version invalidation and audit. Dealer access routes expose only “My Assigned Properties.” Every list/detail query must join the current active assignment to the authenticated dealer ID and reapply current listing public eligibility. A guessed listing ID returns 404. Never include agency-wide dealer controls, other dealers, customer CNIC/mobile, OTP, proof documents or non-public contacts.

Revoke access immediately when assignment is reassigned/revoked, dealer membership is no longer eligible, dealer account is disabled, or agency account/eligibility is disabled. Public listing unpublication removes it from dealer-visible results while keeping assignment history.

## Proposed phases 2–5

1. **Phase 2 — Verified read APIs and local sync:** first complete ERP schema/business-rule verification; then implement separate read-only APIs and local source-link sync, with failure-safe snapshot reconciliation.
2. **Phase 3 — Agency dealer management:** list synced source dealers; provision/reset/disable dealer accounts; scope each action to the signed-in agency; add audit and tests.
3. **Phase 4 — Dealer assignment:** agency-owned eligible listings, exact source matching, transactional reassignment/history, no changes to public representative.
4. **Phase 5 — Dealer view-only portal:** isolated authentication/session, assigned-listing reads, server-side detail authorization, revocation behavior, accessibility/mobile QA.

## Likely files to change later (not changed now)

Portal:

- `app/Config/Routes.php`, `app/Config/Services.php` or service config, `app/Filters/AgencyPortalAccess.php` plus a new dealer filter.
- `app/Controllers/Agency/Auth.php`, `app/Controllers/Agency/*` for sync trigger and management.
- New `app/Controllers/Dealer/*`, views and session/access services.
- `app/Services/ErpAgencyApi.php` or a new external dealer API client; do not repurpose `ErpDealerApi` staff semantics.
- `app/Services/AgencyPortalAccounts.php`, `AgencyPortalListings.php` (extend with reviewed separate services where appropriate).
- New external dealer sync, account, assignment, audit/read-model services.
- New MariaDB manual SQL file after schema review; no migration execution under the current workflow.
- Focused test fixtures and suites for source isolation, snapshots, cross-tenant URLs, session revocation and assignment concurrency.

SGC and Zalmi API packages, only after verified schema predicates:

- `src/DealerController.php` (prefer a separate controller).
- `src/routes.php`, new `v1/agency-dealers.php`, and shared read-only SQL support as required.

## Remaining unverified items / blocker

- Actual SGC and Zalmi ERP DDL and live dealer rows were not queried in this inspection. Local API code exposes candidate `ledger` fields but is not proof of the Core ERP's complete schema/business rules.
- SGC and Zalmi Core PHP dealer screens/save handlers were not successfully isolated from the broad ERP source search; exact external eligibility, company scoping, and status meanings need direct targeted review.
- The local live portal database's current `SHOW CREATE TABLE` for agency/listing tables was not collected here; migration/source code identifies the expected model only.
- Whether an operational queue/worker exists is unverified; no queue path was found in the inspected agency login/routes.
- Therefore no final executable SQL is supplied. The correct next evidence is separate read-only DDL and the relevant dealer list/save query from each ERP, plus portal `SHOW CREATE TABLE` for the named tables.

No Phase 2 implementation was started.
