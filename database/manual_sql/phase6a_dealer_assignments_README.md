# Phase 6A — Listing/dealer assignment schema draft

Prepared on 15 November 2026. **Draft for manual approval; no SQL executed.**

SQL: `database/manual_sql/phase6a_dealer_assignments.sql`. Exactly one new InnoDB table; no parent schema changes, migrations, assignment UI or deployment.

## Evidence inspected

- `C:/Users/dell/Downloads/dealer_schema_reference_READ_ONLY.txt`: supplied MariaDB 10.11.14 SHOW CREATE TABLE reference. Portal listing, agency, agency-link and account primary keys are BIGINT UNSIGNED; ERP source key is INT UNSIGNED. Parent tables are InnoDB.
- `database/manual_sql/phase1_agency_dealers.sql`: membership primary key and agency ID are BIGINT UNSIGNED; unique `(id, portal_agency_id)` supports the composite FK; `(agency_dealer_id, erp_source_id)` references the source-specific dealer identity. User previously confirmed manual creation. Current running schema has not been queried.
- `app/Services/AgencyPortalListings.php`: external portfolio requires an eligible central agency, exact listing agency/source/link, authorized mapped/present ERP link, enabled source, public project/head/subhead mapping, and ListingPolicy public approval. Internal Shalimar staff uses a separate membership branch; this proposed external dealer table does not replace that branch.
- `app/Services/ListingPolicy.php`, `ErpVisibility.php`, `AdminListingDeletion.php`: effective listing eligibility is broader than a status column. Approval must match the committed listing event when required; lifecycle/deal/purpose, deletion marker and project mapping visibility also apply.
- `app/Services/ResaleSubmission.php`, `AgencyDirectory.php`: submission resolves agency from an eligible source-specific link. Agency mapping changes must never rewrite assignment snapshots.
- `app/Services/AgencyPortalDealers.php` and `app/Controllers/Agency/Dealers.php`: Phase 5B actually exists; session supplies agency ID, and current memberships require exact agency/source/link/ERP agency equality. Locked/staff flags are preserved without assignment policy.

## Proposed structure and integrity

The table stores listing and membership IDs, immutable central agency/source/link snapshots, active/revoked state, assignment actor/time, revocation actor/time/reason, record version and audit timestamps. Agency actor IDs reference Agency Portal accounts, not Admin IDs. `revocation_actor='system'` permits a future reconciliation action with an explicit reason and no invented user. Admin-initiated revocation is not implemented or represented as an agency account; a separately approved actor extension would be required if needed.

`active_portal_listing_id` is a STORED generated BIGINT UNSIGNED: listing ID while active, NULL while revoked. Unique `listing_dealer_one_active` prevents two active assignments to the same listing globally, including concurrent transactions from different agencies. Multiple revoked rows for the same listing are permitted. There is no unique dealer constraint, so one dealer can receive many listings. Same-dealer assign requests must be idempotent in application code: if the current assignment already references that membership, return it without creating history noise.

FKs use RESTRICT for both update and delete. The composite membership/agency FK enforces the membership's tenant. Independent FKs enforce listing/link/source/account existence and preserve history when parent hard deletion is attempted. Existing Admin listing deletion is a retained-record operation; future account/dealer deletion must disable/revoke rather than hard-delete referenced records.

**Database limitation:** existing listing, agency link and account tables do not have all matching composite unique keys. Without altering them, FKs cannot enforce account's owning agency or every listing/link/source equality. No such ALTER is proposed. Those checks, current membership, mapping eligibility, approval and business flags must be enforced inside the assignment transaction AND on every future dealer-property read. CHECK constraints cannot enforce these cross-table policies. A row being active never grants access by itself.

## Future transactional assignment protocol

1. Resolve agency/account from authenticated session; reject request-supplied tenant identity.
2. Lock listing row first (`FOR UPDATE`), then current assignment and membership/link/account rows in a consistent documented order. Revalidate exact listing agency, source and agency ERP link, current membership with matching source and ERP agency, dealer source, enabled agency/account/source and current link authorization. Reuse the full AgencyPortalListings / ListingPolicy policy; do not create a status-only check. Verify listing approval under the lock.
3. If assigning the same current membership, return existing assignment unchanged. For reassignment/unassignment, transition active row to revoked with actor/reason/time, increment record_version, preserve original assigned identity/time.
4. Reassignment inserts a new active row after revocation in the SAME transaction. Unassignment inserts nothing. A failed insertion rolls the transaction back, retaining the previous assignment. Unique generated key is the final concurrency guard. Deadlocks/duplicate-key races receive bounded retries or a refresh response.
5. Retain revoked rows permanently and never edit their history. This is an application/database-permission responsibility; the draft deliberately adds no triggers or grants.

If listing ownership changes, approval is withdrawn, mapping changes, or membership is revoked/transferred, deny effective dealer access immediately by joining/revalidating current identities and policies even before assignment rows are reconciled. Do not rewrite old snapshots. Revoke the obsolete row before a new valid assignment can claim that listing. Listing locks/version checks protect concurrent listing edits; read-side revalidation remains mandatory.

## Eligibility policy awaiting approval

Proposed conservative external-dealer assignment policy: `present=1`, `erp_dealer_flag='yes'`, `erp_locked_flag='no'`, `erp_staff_agent_flag='no'`, current membership, currently authorized/unlocked mapped ERP agency and enabled portal agency/account/source. Do not exclude ERP `restricted='yes'` solely because of that flag. This is a proposal only: no eligibility code, flags or memberships changed. Phase 6B must receive the user's locked/staff policy decision before assigning those records.

## Static review / limits

- Parent types compared with the supplied reference and approved Phase 1 DDL; all FK types match those sources.
- Generated-key truth table reviewed: active listing L -> L (duplicates blocked); revoked listing L -> NULL (multiple history rows allowed); same membership on listings L1/L2 -> distinct keys (permitted).
- Explicit indexes cover listing history, tenant/current directory, membership/current access, link reconciliation, source and actors; FK membership/agency has the required matching parent unique key in Phase 1.
- Status/revocation CHECK enforces actor/reason/time shape; active rows cannot carry revocation metadata.
- CREATE TABLE IF NOT EXISTS is rerunnable only for creation; it does not repair or validate an existing differently shaped table. Preflight SHOW CREATE TABLE queries are comments for manual use, not commands executed by Codex.
- No SQL parsing by a MariaDB server, actual index/FK creation, transaction race tests, or live database verification performed. Manual preflight must confirm current DDL/engine/indexes before execution. Database design is ready for manual review subject to that check; business policy remains pending.

Stop after this draft. User must approve and manually execute SQL before Phase 6B implementation.
