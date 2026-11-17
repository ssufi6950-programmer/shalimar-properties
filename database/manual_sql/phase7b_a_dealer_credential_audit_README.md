# Phase 7B-A — Agency-managed credentials and audit schema draft

Prepared 15 November 2026. SQL: `database/manual_sql/phase7b_a_dealer_credential_audit.sql`. **Not executed.** No login UI, credential creation, deployment, ERP or messaging changes.

## Existing-schema evidence

User confirms executing Phase 7A. Its saved SQL has created_by_admin_id / updated_by_admin_id INT UNSIGNED NOT NULL referencing admin_users.id; membership/account/central agency IDs are BIGINT UNSIGNED. Phase 1 membership unique `(id,portal_agency_id)` supports tenant-bound FKs. Supplied `dealer_schema_reference_READ_ONLY.txt` independently confirms Agency Portal account ID BIGINT UNSIGNED and InnoDB. Admin ID INT UNSIGNED is declared in existing Admin migration and already referenced by Phase 1/7A. Phase 7A's actual post-execution SHOW CREATE TABLE was not supplied or queried: this is a conditional manual draft against the confirmed-applied SQL, not a claim of running-schema verification. Manual preflight must confirm exact current types, keys and engines. Stop if different.

## Minimum account change

1. Make existing created_by_admin_id nullable without dropping its FK/index or rewriting any value.
2. Make updated_by_admin_id nullable, preserving existing FK/index/values.
3. Add created_by_agency_account_id BIGINT UNSIGNED nullable + indexed restrictive FK to agency_portal_accounts.id.
4. Creator CHECK requires exactly one Admin or Agency creator. Existing Phase 7A rows already have an Admin creator and pass unchanged; agency rows set Admin creator NULL. Creation fields remain immutable in future code.

The audit table is authoritative for every subsequent action/actor. No duplicated updated-by Agency/Dealer columns: future agency/dealer/system mutations set updated_by_admin_id=NULL, with exact actor in the event; Admin mutation sets the actual Admin ID. Legacy non-NULL values remain until a real update occurs, describing their original Admin attribution. Do not backfill a guessed actor or rewrite prior attribution. Existing records retain known creator/last Admin attribution; older unlogged activity cannot be invented.

No existing account table is recreated. Phase 7A password/revocation/temp-expiry/auth-version checks, unique login/member keys, rows and membership binding are preserved. Dealer self-password change updates password/auth-version/timestamps and clears Admin update attribution while appending a Dealer event. No fake Admin row or ID is needed.

## Dedicated audit design

`agency_dealer_credential_audit` stores timestamp (microseconds), optional target dealer account (NULL permits failed creation), exact target membership/tenant/source, display snapshots for agency/dealer/source/username, action/outcome, typed actor role and exact role-specific account ID. System is explicitly non-account with all actor IDs NULL; never use fabricated ID 0. Dealer actor CHECK additionally requires the target account to be that same dealer account. Agency/Admin actor IDs have respective FK types. All parent deletes/updates are RESTRICT to retain provenance.

Explicit safe before/after columns cover username, enabled, forced-change, auth-version, temporary expiry and revoked time. There is no generic payload/JSON/free-text error field to accidentally capture password/hash/token/OTP/session data. Username/name snapshots must be obtained from verified database identities (except validated requested username when creation fails) and escaped in future UI. Any string field could be misused by buggy code; future serializers must construct an allowlisted event, never copy request/exception objects. Snapshots are display history, never authorization keys.

Actions: LOGIN_CREATED, USERNAME_CHANGED, PASSWORD_RESET, PASSWORD_CHANGED, ACCOUNT_ENABLED, ACCOUNT_DISABLED, ACCOUNT_REVOKED, TEMPORARY_PASSWORD_ISSUED and optional TEMPORARY_PASSWORD_DISCLOSED. Outcomes are success/failure; failure reason is a closed enum with no secret-bearing provider/exception text. Ordinary authentication failures remain solely in agency_dealer_login_attempts. Do not put user login attempts into management audit.

Indexes support global chronological history, agency, membership, dealer account, ERP source, action/outcome and exact Agency/Admin/Dealer actor. Parent composite membership/tenant FK and all integer FK types match reference definitions. Account-to-target membership equality, actor Agency ownership and source equality cannot be fully enforced by existing parent keys without more parent ALTERs; application must validate them under lock. This draft deliberately avoids extra parent indexes/schema changes.

## Future atomic audit implementation

- Derive Agency actor/account/tenant exclusively from authenticated session; require CSRF and current membership + exact valid source/link/company mapping as already represented locally. Locked=yes/sp_Agent=yes are informational and do not deny dealer login/management; revocation and invalid mapping always deny effective access. No name/mobile identity merging.
- Agency may create/read username/reset/change username/enable/disable only within its current membership tenant. Admin actions require explicit permission. Dealer may change only its own password after authenticated verification. No automatic credentials/self-registration.
- Lock target membership/account and validate actor authorization. Create/reset uses password_hash on cryptographically random temporary password; display it only once through no-store/no-referrer response and consume one-use delivery state. The persistent account stores hash only. Existing password is never recoverable.
- A create transaction inserts account plus LOGIN_CREATED + TEMPORARY_PASSWORD_ISSUED; reset records PASSWORD_RESET + TEMPORARY_PASSWORD_ISSUED. These event pairs commit with the mutation or all roll back. Username/enable/disable/revoke/self-password actions append exact corresponding events in that same transaction. Reset, username change, disable, revoke and password change increment auth_version; re-enable should invalidate any previous session as well. Preserve original creator IDs.
- SQL cannot enforce mandatory event insertion or append-only workflow without triggers/privilege changes. Future central mutation service must write account + event atomically, fail closed on audit failure, and expose INSERT/read only for audit (no update/delete routes). Prefer deployment DB privileges denying UPDATE/DELETE on audit if infrastructure permits; no GRANT/TRIGGER is executed/proposed here.
- After a mutation rolls back, optionally append a failure event separately with known authorized target and safe reason. Never expose or log another tenant's target merely because an attacker supplied an ID; resolve attribution only from authorized server-side context. Unknown/unresolvable targets stay in a generic security log, not a fabricated membership row. If database is unavailable, persistence of failure audit cannot be guaranteed.
- TEMPORARY_PASSWORD_DISCLOSED can only mean server authorized a one-time response. It cannot prove human receipt: database commit/network/browser display are not atomic. Do not claim guaranteed disclosure auditing. Do not store plaintext password to retry disclosure; reset/reissue on a lost response. A secure one-time response mechanism is later implementation, not introduced by this SQL.

## Manual execution and rerun safety

1. Back up SMP database. Run the commented preflight queries manually in phpMyAdmin. Confirm original Phase 7A account shape, correct parent engines/types, existing FKs and membership unique key. No missing Admin attribution should exist under original NOT NULL schema.
2. Review/approve SQL. Execute the **one-time ALTER** first, then CREATE for the dedicated audit table. ALTER is not rerunnable as written: it adds a column/index/constraints; do not rerun after successful completion. If interrupted, inspect SHOW CREATE TABLE and resume only missing statements with a reviewed repair, never drop/recreate accounts.
3. MariaDB DDL auto-commits; an audit-table CREATE failure does not roll back the preceding ALTER. Do not enable credential management until both structures are confirmed. Table CREATE IF NOT EXISTS will not verify/repair a conflicting existing table. No foreign_key_checks bypass.
4. Run postflight SHOW CREATE TABLE manually and retain output. No old rows are modified and no old events are fabricated/backfilled. This migration does not itself enable accounts or give an agency permissions.

## Static review / remaining risks

Review confirms one necessary account ALTER + one new audit table; existing attribution untouched; Admin/Agency/Dealer integer types; role-specific actor checks; NULL system actor; safe closed before/after fields; eight required action types; secret-free structural payload; restrictive FKs/indexes; transaction requirements and existing account constraints preserved.

No MariaDB parser/FK/DDL execution or real transaction/permission tests performed. Current DDL verification, manual SQL approval/execution and later centralized atomic credential service/Admin audit UI remain required. Existing AdminAuditService assumes Admin actors, so it is not reused to impersonate Agency/Dealer actors. No existing authentication, assignment or staff code is changed in this phase.
