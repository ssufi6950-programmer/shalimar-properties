# In-process bookings and resale

Implemented locally on 6 November 2026, following the request to allow resale of properties whose ERP documents are still in process.

The portal now accepts the exact strings `booking_in_process='yes'` and `'no'` for an otherwise eligible current booking. `yes` still displays **Pending ERP approval**; the portal does not approve ERP documents or change that flag. NULL, unexpected, numeric and incorrectly cased processing values remain blocked. Compatibility with older owned-properties endpoints that omitted the field after filtering to `no` is preserved.

The existing List for Resale button uses this eligibility on both My Properties and property details. The same wizard, current-booking mobile OTP, buyer preview and fresh final ownership verification are required before publication. Known processing changes between `yes` and `no` do not invalidate ownership by themselves.

All other gates remain: verified customer/source identity, unique current booking and current owner, active customer, valid registered mobile, active booking/inventory status, both Head/Subhead `smp_active` strings exactly `yes`, published local project, active exact mapping, representative eligibility, duplicate-listing protection and admin restrictions. Cancelled, returned, transferred-out and ambiguous bookings do not become eligible. Sold and admin-unpublished listings are not reopened.

## Application and API files

Portal:

- `app/Services/CustomerPortfolio.php`: recognizes both known processing strings without treating pending documents as a resale veto.
- `app/Services/ResaleOwnershipOtp.php`: permits the same known flags at OTP issuance.
- `app/Services/ResaleSubmission.php`: permits them at final persistence while retaining all authorization and transaction checks.

Both ERP API folders under `D:/xampp/htdocs/sgcacc/`:

- `smp-api/src/CurrentBookingVerification.php`
- `zalmi-smp-api/src/CurrentBookingVerification.php`
- Each API's `src/HealthController.php` and `CURRENT-BOOKING-API.md`

Existing endpoint: authenticated `POST /smp-api/v1/verify-current-booking.php`. No new route or response field is required. Build: `2026-11-06.booking.10`. Both implementations accept `in_process='yes'` and `'no'`, retaining the existing strict owner, booking, visibility and registered-mobile rules. Both continue to use SELECT statements only.

## Upload

For each existing live API installation, extract and merge its corresponding patch into the directory containing `smp-api`:

- [SGC API patch](deploy/in-process-resale/sgc-api-patch.zip)
- [Zalmi API patch](deploy/in-process-resale/zalmi-api-patch.zip)

Each patch contains only `smp-api/src/CurrentBookingVerification.php`, `smp-api/src/HealthController.php` and `smp-api/CURRENT-BOOKING-API.md`. Keep the existing API folder and private `src/catalog.local.php`. No SQL, token reset, configuration replacement or server setting change is required. The three portal service files must also be uploaded when deploying the portal itself.

The existing full API packages in `.qa/erp-api-upload/` were regenerated as well, with private configuration excluded. Live uploads were not performed. An older live verification API can still reject an in-process booking until its patch is uploaded.

## Verification

Tests use disposable databases, isolated customer sessions and fake SMS/ERP providers; the actual API HTTP suite runs copies of both updated API folders against a disposable ERP schema. No real SMS, live deployment or ERP business-data write was performed.

The API tests cover in-process success, wrong owner and cancellation rejection, unknown/NULL processing values, all 16 Head/Subhead flag combinations, current/co-owner/transfer rules, mobile privacy and unchanged database snapshots. The OTP service tests include in-process publication for both ERP sources and rejection of malformed processing flags. The HTTP publication test performs the complete SGC and native browser Zalmi in-process OTP/preview/final-publication flows. Existing lifecycle tests retain edit, withdrawal, secure republish, Sold restrictions and concurrency coverage.

Logs: `.qa/in-process-erp-api.log`, `.qa/in-process-auth.log`, `.qa/in-process-otp.log`, `.qa/in-process-live.log`, `.qa/in-process-publication.log`, `.qa/in-process-lifecycle.log` and `.qa/in-process-packages.log`.

All **514 checks passed**: 211 ERP API checks, 44 customer authentication checks, 46 resale OTP service checks, 50 customer live HTTP/browser checks, 70 cross-ERP publication checks and 93 lifecycle checks. All seven changed application/API PHP files passed syntax checks. Patch ZIP integrity, exact file lists and private-configuration exclusion were verified.

No database migration, public design change or new publication bypass was introduced. Earlier documentation describing rejection solely because `in_process='yes'` is superseded by this request.
