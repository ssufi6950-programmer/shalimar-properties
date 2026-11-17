# Phase 7A — Dealer Portal account database draft

Prepared 15 November 2026 for manual MariaDB 10.11 review. SQL file: `phase7a_dealer_portal_accounts.sql`. No SQL executed, schema changes applied, accounts/passwords created, UI implemented, ERP contacted or deployment performed.

## Identity decision

Use **one account per agency_dealer_memberships.id**, not per name/mobile or central dealer alone. A membership identifies an exact source-specific dealer, central agency, ERP agency link and membership generation. Those identities are already connected by existing dealer/membership FKs and are validated by AgencyPortalDealers. Do not copy dealer/source/link into the account table: derive them from the bound membership to avoid conflicting identities. Keep portal_agency_id only to enforce tenant binding via the existing membership `(id,portal_agency_id)` unique key.

Account/membership association is immutable in application policy. Never repoint an existing account to another membership, agency or source. Transfer creates a new membership; a separately authorized Admin must provision a new account. Previous username stays reserved because its account is retained. Same source person returning to the same agency with a new membership generation receives a new account, not automatic credential recovery. SGC/Zalmi identities are never automatically combined. One account per membership also prevents duplicate active credentials for the same membership. This design deliberately does not offer a multi-source combined dealer login.

## Schema evidence and limits

- Supplied `C:/Users/dell/Downloads/dealer_schema_reference_READ_ONLY.txt` confirms authorized_agencies.id BIGINT UNSIGNED and InnoDB parent structures; agency account IDs are also BIGINT UNSIGNED but are not reused by dealer login.
- Approved `database/manual_sql/phase1_agency_dealers.sql` defines membership.id/portal_agency_id BIGINT UNSIGNED, InnoDB and unique agency_dealer_membership_agency `(id,portal_agency_id)`. Dealer id/source pairs are constrained on memberships. User previously confirmed applying that schema.
- `app/Database/Migrations/2026-11-04-000002_CreateAdminSecurity.php` declares admin_users.id INT UNSIGNED. Existing Phase 1 dealer overrides already reference it using INT UNSIGNED. No current SHOW CREATE TABLE for admin_users was queried; manual preflight must confirm current type/engine.
- User supplied actual Phase 6A assignment DDL: active assignment unique generated listing key, exact membership/agency composite FK and retained history exist by that evidence. No running local/live database was inspected in this phase.

Draft account FKs: composite membership/agency; central agency; created-by Admin; updated-by Admin. All RESTRICT delete/update. New table types match these evidence sources. Foreign keys cannot enforce current membership status or prohibit account identity updates themselves; current policy validation plus application restrictions are mandatory. No triggers/parent ALTERs proposed.

## Account/security fields

Account: BIGINT primary key, exact membership and agency, unique normalized ASCII login identifier, password_hash (255), enabled (defaults disabled), forced-change flag, temporary expiry, auth_version, last login, password changed time, permanent revocation time/reason, Admin creation/update attribution and timestamps. Two unique keys prevent membership duplication and login collision, including disabled/revoked accounts. Indexes support tenant/account queries and all FKs.

Proposed usernames: 3–100 lowercase ASCII letters/digits plus dot/underscore/hyphen, first character alphanumeric, canonicalized before validation/lookup. Username is a login handle; membership ID is authorization identity. Names/mobile do not establish identity. No phone-login normalization or automatic usernames/passwords. Cross-role login identifiers may coincide because separate endpoints/tables/authentication roles exist; never fall back to Agency Portal authentication on failed dealer login.

Use PHP password_hash (Argon2id where supported; bcrypt fallback) and password_verify; rehash when needed. SQL hash length CHECK is only structural, not proof a string is a valid secure hash: Phase 7B must use password_hash and never accept/store a caller-provided raw/hash credential blindly. Never log/retrieve plaintext passwords or derive them from mobile/ERP ID. Provision cryptographically random temporary password only under approved Admin workflow; force change with a short explicit expiry. Authentication must check expiry against current time (CHECK cannot enforce moving time). CHECK requires expiry when enabled and forced-change is set; clear expiry after permanent-password change.

Reset password, disable, revoke or any security-relevant account change increments auth_version transactionally. Future session validates enabled, revoked_at IS NULL, auth_version match and current membership/mapping on every request. Revoked accounts cannot be enabled by the CHECK. Treat revocation as final; do not clear it through ordinary reset/enable UI. A temporary disable can be re-enabled explicitly by authorized Admin after full membership validation. Updating Admin attribution describes Admin management only; dealer's own password change updates password_changed_at/updated_at and auth_version, retaining the last managing Admin IDs. Detailed changes use the existing Admin audit conventions, not plaintext secret snapshots.

## Why the small second table is proposed

`agency_dealer_login_attempts` stores only identifier/IP HMAC hashes and failure timestamps with indexes for identifier+time, IP+time and cleanup. Unknown usernames have no account row, so account-only counters cannot throttle those attempts. Existing AgencyPortalSession already uses a separate agency_login_attempts table and independent per-IP locking; reusing that table would mix roles and possibly clear another portal's counters on successful login. A dedicated security table is the minimum relational equivalent that preserves the Agency Portal unchanged. This is a proposed addition for approval, not an executed change.

Future rate limiter: use keyed HMAC-SHA256 (dedicated private server key/purpose), canonicalize identifier/IP consistently, acquire bounded locks for both identifier and IP in consistent order, then check and record failures atomically. Proposed starting limits matching current Agency Portal: 5 rejected attempts/identifier and 10/IP in 10 minutes. Throttle unknown identifiers too; generic responses, dummy password verification, no enumeration, bounded request field sizes. Commit failure tracking before returning failure. A successful login must not erase the IP-wide history; prune expired rows through approved CLI maintenance. Failure to store/check throttle state must fail closed. These are implementation requirements for later phases; the schema alone enforces no rate limit. No attempt account FK intentionally, because unknown usernames must be trackable.

## Existing authentication inspected

`AgencyPortalSession.php` uses Argon2id/bcrypt, generic credential errors/dummy verification, transaction + GET_LOCK per IP, separate failed attempts, enabled/eligibility checks, temporary expiry, regenerated session ID, 30-minute idle and eight-hour maximum lifetime, auth_version revalidation and password hash removal from session user projection. `AgencyPortalAccounts.php` invalidates sessions on reset/enable changes. `AgencyPortalIdentity.php` supports agency phone/custom handles; dealer accounts intentionally propose only a unique custom handle. Existing file session driver and agency auth logic remain unchanged.

Future dealer authentication must use dedicated session keys and a dedicated guard/controller, isolated from customer/agency/Admin/internal staff roles. Never interpret a dealer ID as an agency or internal staff account ID. Regenerate session on authentication/privilege changes; use secure HttpOnly SameSite cookies and CSRF on login/mutations. Reuse the existing proven session lifecycle pattern, but never reuse Agency Portal credentials, agency session identity or internal staff dashboard permissions. Suggested expiry defaults can match existing Agency Portal and remain a later implementation decision. Separate browser cookie/session storage, if required for simultaneous-role isolation, must be explicitly designed then; this SQL adds no session-storage table.

## Mandatory future effective property access

Every property list/count/detail/media request must start from authenticated dealer account ID, require enabled/unrevoked/current auth_version, resolve its bound membership, and enforce:

1. Membership current; dealer identity/source exact; central agency enabled external; agency ERP link mapped/present/authorized/unlocked and source enabled. Membership ERP agency/source/central agency equals that current link.
2. Active assignment references that exact membership, tenant, source and agency ERP link. Do not trust the assignment active flag alone.
3. Listing's CURRENT agency/source/link matches both membership and assignment snapshots.
4. Complete existing AgencyPortalListings external-portfolio eligibility, including ListingPolicy committed approval, purpose/lifecycle/deal/deletion policy and ERP public project/head/subhead mapping visibility. Existing account-eligibility policy must also be respected; do not create a new status-only shortcut.
5. Dealer erp_locked_flag=yes and erp_staff_agent_flag=yes are informational and DO NOT independently deny assignment visibility. ERP restricted=yes on an external agency likewise is not by itself an exclusion. Internal Shalimar staff authentication/dashboard remains separate even if the ledger has the overlap flag.
6. Return a whitelist of public-safe assigned property fields only; no owner/customer private contacts, CNIC, OTP, financial records, credentials or unrestricted agency portfolios. Enforce same rules on guessed direct URLs and images.

Membership revocation/transfer, listing transfer/approval withdrawal/deletion or link remapping denies access immediately on the next request even if account or assignment rows have not been reconciled yet. Disable/revoke old account + bump auth_version when reconciling; preserve old account/history. A new membership never inherits old credentials or assignments. No global dealer-present flag alone can override revoked membership.

## Static review outcome

Reviewed unsigned FK types, composite parent unique index, one account/member and unique canonical login identity, revocation checks, enable/temp-expiry constraints, session auth-version fields, independent failure/IP tracking, and password-hash-only storage. No existing-table ALTER/data mutations. MariaDB 10.11 supports these InnoDB FKs, unique indexes and CHECK expressions. Rerunning CREATE TABLE IF NOT EXISTS does not validate/upgrade an incompatible existing table; manual preflight remains necessary.

No real database creation/parser/FK/concurrency/auth tests performed. Remaining prerequisites: manual current parent DDL validation (especially admin_users and membership index), approval of the small attempts table, reviewed SQL execution, later provisioning/credential/session implementation. This deliverable is ready for manual SQL review; no login implementation is included.
