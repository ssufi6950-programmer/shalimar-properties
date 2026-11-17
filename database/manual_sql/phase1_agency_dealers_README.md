# Phase 1 — Agency dealer database draft

**Status: DRAFT — NOT EXECUTED.** This package contains only the manual SQL draft. It does not implement APIs, sync, Admin pages, dealer accounts, assignments, notifications, or ERP changes.

## SQL file

`database/manual_sql/phase1_agency_dealers.sql`

## Tables proposed

- `agency_dealers`: one source-specific identity per `(erp_source_id, erp_ledger_id)`, with original ERP profile and status values. SGC and Zalmi identities are never merged.
- `agency_dealer_memberships`: agency/link/source snapshots and current or revoked membership generations. A new generation preserves a later return/transfer as a distinct membership; a generated unique key allows only one current membership per source-specific dealer identity.
- `agency_dealer_profile_overrides`: Admin-edited local name/mobile/email and encrypted CNIC, attached to one exact membership. New membership generations do not inherit these overrides. ERP sync must update only the original ERP fields in `agency_dealers`.
- `agency_dealer_sync_state`: lock, pagination, status, freshness and count independently for each agency ERP link.

## Existing tables changed

**None.** No ERP table, Shalimar Agent table, `agency_portal_accounts`, or `portal_listings` is altered. All foreign keys refer to stable SMP primary IDs. Exact agency/source/link consistency is to be checked by the later application services, as requested.

## CNIC handling

`encrypted_cnic` is binary storage for application-layer authenticated ciphertext (including its nonce/tag envelope); `cnic_key_version` identifies the encryption-key version. The encryption key must come from secure application configuration, never this SQL or the database. Do not import ERP CNIC. Do not log or return plaintext/decrypted CNIC to public or agency-facing code. The schema stores no CNIC search hash.

## Manual phpMyAdmin review/execution

1. Back up the SMP Portal database.
2. Select the SMP Portal database, not SGC or Zalmi.
3. Inspect for existing tables with these four names. `CREATE TABLE IF NOT EXISTS` will not validate or fix an existing table definition.
4. Review the full SQL file. Execute it manually only after approval.
5. If execution succeeds, inspect each new table with the four `SHOW CREATE TABLE` statements at the bottom of the SQL file.

The script creates tables in foreign-key dependency order. MariaDB DDL can commit statement-by-statement; this script is not an atomic transaction. If a statement errors partway through, stop and inspect which tables were created before rerunning.

## Validation performed

Static comparison against `dealer_schema_reference_READ_ONLY.txt`: source ID, portal agency/link, admin actor and ERP source reference types; parent engines; generated nullable unique membership key; source-specific identity unique key; membership-generation uniqueness; FK delete restrictions; no existing-table alteration; and CNIC ciphertext/key-version pairing. SQL was **not** executed, and no live database connection or schema mutation was performed.
