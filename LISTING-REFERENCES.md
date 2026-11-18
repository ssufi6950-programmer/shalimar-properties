# Daily listing references ? new listings only

Prepared locally on 18 November 2026. Deployed live; see verification below.

## Approved behavior

New references are SP- + YYMMDD + the daily serial without zero padding: SP-2611181, SP-2611182; the next Pakistan calendar day starts SP-2611191. The serial is shared across new customer/company Sale/Rent listings and ERP sources. Customer references are allocated at submission; company references are allocated at creation/publication. Approval/edit/republication of the same record retains its existing saved reference. Existing SP-64-style references remain unchanged. No historical backfill or record update is included.

Numeric listing IDs, foreign keys, assignment identities, protected media IDs and URLs are unchanged. The saved payload.ref is displayed in Admin review/list/detail/delete, Company Properties status, customer lists/details/success and assignment SMS. Public cards, agency and dealer views already consume saved payload.ref.

## Allocation and consistency

ListingReference acquires a database-scoped MariaDB/MySQL advisory lock before the listing transaction, derives the next serial from the maximum already-saved matching reference for that date, and holds the lock through commit/rollback. Both current creation services use the same lock order. An uncommitted/failed creation does not consume a serial. Every current application deletion is retained, so its serial remains used. No schema changes or separate counter table are required. The host must support GET_LOCK/RELEASE_LOCK; a busy/unavailable lock fails allocation rather than falling back to ID-based references. Manual database writes or alternate code that bypasses these creation services cannot claim this locking guarantee.

## Handoff and verification

Deployment file mapping: deploy/daily-listing-references/README.md. Fourteen Portal PHP source files are included; no ERP or schema change is required. Eleven mocked allocator checks, six existing fixture render checks and twenty company validation/permission checks passed, along with PHP lint. Real database concurrency and authenticated end-to-end submission are unverified. No live upload, backfill, old reference update or notification was performed.


Live deployment completed on 18 November 2026 with user authorization. All 14 Portal files backed up, server PHP linted and SHA-256 verified. Backup: `D:/xampp/htdocs/shalimar-properties/.qa/live-backups/daily-listing-references-20261118-072839/`. Live public SQL, reference allocation SELECT and GET_LOCK/RELEASE_LOCK smoke checks passed. Legacy reference display remains SP-64. No listing/reference rows were written, historical references converted or notifications sent. Actual concurrent listing creation remains unverified.
