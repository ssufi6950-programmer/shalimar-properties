# Current booking verification — SGC, Zalmi and resale authorization

Implemented 5 November 2026. Both downloaded API folders are updated:

- `D:/xampp/htdocs/sgcacc/smp-api` — SGC, including the existing dealer/agent endpoints.
- `D:/xampp/htdocs/sgcacc/zalmi-smp-api` — Zalmi, preserving its separate configuration.

The full SGC Core PHP source and local database were inspected. The owner previously confirmed Zalmi is an identical-schema copy; its downloaded API implementation was inspected and tested with that schema. These results do not claim access to or verification against the two live databases. Uploading the supplied API packages is still required on live.

## Authoritative tables and current-booking rules

| Concern | Actual relationship |
| --- | --- |
| Stable property reference | `pro_inventory.id`, the existing My Properties `property_id`; not plot/file display text. |
| Booking history | `pro_payment_booking.property_id = pro_inventory.id`, with equal `company_id`. |
| Current booking | Exactly one booking with `locked='no'` for that property/company. All unlocked rows are resolved before checking ownership or eligibility. |
| Primary owner | `pro_payment_booking.customer_ledger = ledger.id`. |
| Current co-owners | Strict JSON/CSV ledger IDs in `pro_payment_booking.co_owners`, using the existing ownership API parser. |
| Registered mobile | `ledger.mobile` of the verified primary/co-owner linked to that current booking. No primary-owner, dealer, agency, previous-owner or browser-mobile fallback. |
| Head | `item_location.id = pro_payment_booking.head`. |
| Subhead | `item_sublocation.id = pro_payment_booking.sub_head` AND `item_sublocation.location_id = pro_payment_booking.head`. |

Evidence: the ERP resale code locks previous bookings and unlocks the new booking (`admin/property/property-resale.php` around lines 520–521); the booking screen filters unlocked, uncancelled bookings and joins the booking customer ledger. The existing customer API already uses this rule, counts unlocked bookings independently of the requested customer, and parses current co-owners strictly. Its status predicate is now shared through `CurrentBookingRules` without changing the predicate.

Accepted current booking/inventory statuses remain `booked`, `resale`, `ownership`, `complete`, with booking `sale_type` in `sale`/`resale`, `cancel='no'`, and processing/approval `in_process='no'` for this new verification endpoint. The expected owner's ledger must be unlocked and an active customer. In-process properties remain privately visible through the existing My Properties API; this endpoint rejects them for resale authorization.

No MAX booking ID, creation timestamp, inventory.customer_id, CNIC match or historical booking is used as ownership proof. Multiple unlocked rows fail safely, including when one looks cancelled or belongs to another customer. Cancellation/return/hold and unknown statuses never resurrect an older owner. Returns that restore an unlocked predecessor follow the ERP's resulting authoritative state.

Co-owners receive verification through their own current-booking-linked ledger mobile. `owner_role` distinguishes `primary` and `co_owner`; unrelated owners' mobile/profile details are not returned. Malformed co-owner records fail as ambiguous.

## Endpoint and authentication

Recommended direct endpoint, compatible with the existing file-based API deployment:

```text
POST https://sgcacc.com/smp-api/v1/verify-current-booking.php
POST https://zalmidevelopments.com/smp-api/v1/verify-current-booking.php
Authorization: Bearer <existing private portal API credential>
Content-Type: application/json
```

The router also registers `/v1/resale/verify-current-booking` when the site's existing rewriting supports it. No new Apache configuration is needed for the direct PHP endpoint.

The existing bearer middleware executes before database access; the new route normalizes its errors. No separate API scope system exists in these ERP APIs, so this reuses their existing private service token rather than claiming unsupported permissions. Tokens are never placed in customer JavaScript or ZIPs.

The portal uses its existing verified HTTPS transport, approved-host/public-address checks, DNS pinning, private credential references, timeouts and disabled redirects. The API retains the existing proxy-compatible authentication model: it does not infer external TLS from untrusted forwarded headers or reintroduce the previously broken PHP HTTPS detector. Live traffic uses the site's existing HTTPS endpoints.

Responses have `Cache-Control: no-store`. Only authenticated server-to-server success responses contain the registered mobile. API access URLs contain no customer/property/mobile query parameters. Controlled application errors omit SQL, exception messages, credentials and personal data; logs record only generic failure types.

Verification has a process-independent, filesystem/flock rate limiter: default 120 authenticated requests per minute per direct client IP/API deployment. Rate files contain only window/counter values and use opaque keyed filenames under the PHP temporary directory, outside the webroot. Rate protection storage failure fails closed. No ERP database writes or schema changes are needed.

Optional existing private configuration/environment keys:

- `SMP_API_VERIFY_RATE_LIMIT`: 1–1000 requests/minute; default 120.
- `SMP_API_VERIFY_ALLOWED_IPS`: comma-separated direct `REMOTE_ADDR` allowlist; empty preserves existing connectivity. Forwarded client-IP headers are not trusted.

## Request

```json
{
  "property_ref": "1250",
  "expected_customer_id": "550",
  "expected_booking_ref": "9876"
}
```

`expected_booking_ref` is optional and detects a stale booking; it never selects the booking. IDs use the real numeric primary-key format in this schema. JSON lists, oversized bodies, unknown keys/mobile overrides, malformed IDs and injection strings are rejected. Queries use the existing prepared SELECT-only database adapter.

## Success response

Illustrative values, same contract/types for both APIs:

```json
{
  "success": true,
  "data": {
    "property_ref": "1250",
    "current_booking_ref": "9876",
    "booking_status": "active",
    "current_booking": true,
    "current_owner_customer_id": "550",
    "owner_role": "primary",
    "ownership_match": true,
    "registered_mobile": "923001234567",
    "head_id": "10",
    "subhead_id": "25",
    "head_smp_active": "yes",
    "subhead_smp_active": "yes",
    "resale_eligible": true,
    "verified_at": "2026-11-05T15:00:00+05:00"
  }
}
```

Mobile normalization is the existing canonical Pakistani `923XXXXXXXXX` format. Equivalent duplicate representations of one number are acceptable; multiple distinct registered numbers have no documented primary priority and therefore return `REGISTERED_MOBILE_AMBIGUOUS`. Invalid mixed phone text is rejected rather than guessed. There is no request-mobile fallback.

Raw Head/Subhead flags remain strings or NULL. Exact `'yes'` AND `'yes'` alone yields `resale_eligible=true`; `no`, NULL and unexpected values are returned accurately with eligibility false. Missing linked catalog rows yield unavailable flags and false eligibility; missing/non-string visibility schema fails safely. The portal additionally requires its own published project, active exact source/head/subhead mapping and current local catalog visibility.

## Controlled errors

```json
{
  "success": false,
  "error": {
    "code": "CURRENT_OWNER_MISMATCH",
    "message": "The requested customer is not a current booking owner."
  }
}
```

| HTTP | Codes |
| --- | --- |
| 400/413/415 | `INVALID_PROPERTY_REFERENCE`, `INVALID_CUSTOMER_REFERENCE`, `INVALID_BOOKING_REFERENCE`, `INVALID_REQUEST` |
| 401/403 | `UNAUTHORIZED_API_REQUEST`, `CURRENT_OWNER_MISMATCH` |
| 404 | `PROPERTY_NOT_FOUND` |
| 405 | `METHOD_NOT_ALLOWED` |
| 409 | `CURRENT_BOOKING_NOT_FOUND`, `CURRENT_BOOKING_AMBIGUOUS`, `CURRENT_BOOKING_CHANGED`, `BOOKING_NOT_ELIGIBLE`, `REGISTERED_MOBILE_MISSING`, `INVALID_REGISTERED_MOBILE`, `REGISTERED_MOBILE_AMBIGUOUS` |
| 429 | `RATE_LIMITED` with Retry-After |
| 503/500 | `ERP_DATABASE_UNAVAILABLE`, `INTERNAL_VERIFICATION_ERROR` |

Errors contain no `data` or registered mobile. Database/schema/configuration failures never return an invented booking, owner, contact or eligibility success.

## Portal integration and publication

All three selling methods now use the same protected workflow:

1. Fill the existing four-step form and choose Self Sell, Shalimar Agent or an external Authorized Agency.
2. The portal derives the source and expected customer from its verified ERP customer link/current property reference, validates the form/representative and calls the originating ERP's new endpoint.
3. It creates a property/session/customer/submission-bound challenge and sends the code to the returned authoritative registered mobile. Browser-provided source/customer/booking/mobile fields cannot override that destination. A Self Sell public contact is separate and optional.
4. Correct property OTP verification unlocks a private preview of the exact frozen public listing details. Before verification, preview and direct publication are denied.
5. Confirm & Publish refetches current ownership and calls the same ERP endpoint again, with the OTP-authorized expected booking. It compares booking, verified owner/role, mobile and eligibility proof; revalidates mapping/source/project/representative under local locks; then atomically saves only the selected property and one live listing and consumes the authorization.

No admin approval is required for these newly OTP-verified publications, as required by this task. Existing pending/approved/sold/cancelled/rejected listings are not automatically changed or reopened. Legacy pending creation remains available to existing internal code; the public customer submit route now requests OTP and cannot directly create/publish a listing.

Codes are cryptographically random, hashed, expire within five minutes, allow at most five attempts, use a 60-second resend cooldown and existing customer/IP/mobile rate protection. Draft/proof/registered mobile are encrypted at rest. Resend re-resolves the current registered mobile and invalidates the old code. The public browser gets only a masked OTP destination. Listings retain a private mobile HMAC/proof audit, not the full private registered-mobile value; intentionally chosen public contacts remain public.

Changed/failed fresh proof invalidates the old authorization. ERP unavailability cannot publish. The transaction rechecks the locked verified challenge and frozen details and consumes it with the listing/property writes. Unique active-property and submission/verification keys prevent duplicate publications; repeated successful confirmation returns only the customer's existing result. No bulk ERP property import, ERP posting, financial transfer or ERP SMS is introduced. SGC-backed Shalimar Agent provenance remains SGC when handling a Zalmi property.

Local SMS testing continues to use the previously configured **03427056415** override through the existing gateway adapter. The API never sends SMS. Automated tests use captured SMS transports and sent no real messages.

## Portal migration and routes

Applied locally: `app/Database/Migrations/2026-11-05-000005_CreateResaleOwnershipVerification.php`.

- Creates `resale_otp_challenges` with hashed OTP/session binding, encrypted payload, attempt/cooldown/expiry/verification/consumption state.
- Adds nullable unique `portal_listings.resale_verification_id` and private `owner_verification` proof.
- Does not rewrite existing listings, properties, ERP customer links, roles or ERP tables.

Other portal deployments run the portal migration normally (`php spark migrate`). **Neither ERP database needs a migration.**

Customer routes retain existing authentication/session/CSRF filters:

```text
POST customer/resale/submit       # now requests property OTP
POST customer/resale/otp/verify
POST customer/resale/otp/resend
GET  customer/resale/preview?challenge=<opaque ID>
POST customer/resale/publish
```

`customerPortal.bookingPaths` defaults to the new direct PHP path for SGC and ZALMI. Existing ERP Sources, credentials, login OTP, agency sync/mapping, Shalimar Agent management, RBAC and public local-database queries are reused.

## Modified files

Both ERP API folders:

- New `src/CurrentBookingVerification.php`, `v1/verify-current-booking.php`.
- Updated `index.php`, `src/routes.php`, `src/BearerAuthentication.php`, `src/CustomerController.php`, `src/HealthController.php`.
- New `CURRENT-BOOKING-API.md` endpoint/deployment contract.
- Existing SELECT-only CustomerDatabase and private configuration are preserved.

Portal:

- New migration above; `app/Services/CurrentBookingProvider.php`, `CurrentBookingApi.php`, `ResaleOwnershipOtp.php`.
- Updated `app/Services/ResaleSubmission.php`, `app/Controllers/Customer/Resale.php`, `app/Config/{CustomerPortal,Services,Routes}.php`.
- New `app/Views/customer/resale/public_preview.php`, `public/assets/customer/js/resale-preview.js`.
- Updated resale live_create/submitted views, customer layout route configuration and `public/assets/customer/js/resale-live.js`.
- Updated protected `.qa` service/API/HTTP/browser fixtures; new resale_ownership_checks and package helper. No production test bypass is added.

## Verification and limitations

Tests exercise the real PHP HTTP endpoints and prepared MySQL/mysqli/PDO SQL using isolated representative schemas, including transfers A→B, former-owner privacy, co-owners, cancellation/return/pending/ambiguous/current/missing booking, invalid/missing/ambiguous mobile, stale expected booking, exact 16 Head/Subhead flag combinations, wrong/missing bearer, injection, method/body bounds, rate protection, database outage, no sensitive logs and unchanged ERP records. Existing customer/ownership/floor/agency/dealer endpoints are checked in the same suite.

Portal service and actual HTTP/Edge checks cover wrong/expired/exhausted/session/customer-bound OTP, no preview before verification, registered versus login/public mobile, encrypted storage, no writes before publication, resend destination refresh, final changed owner/booking/mobile/flags/outage denial, immutable draft price/contact/representative, all three selling methods, shared SGC agent on both ERPs, CSRF at each stage, idempotency, live publication and preserved historical agency/agent/RBAC behavior. Desktop/mobile wizard and preview layouts fit without uncaught browser errors.

Commands/results are recorded in the final verification note below. Tests do not contact live ERP databases, send real SMS, publish real customer listings or change ERP business records. Multiple distinct registered mobiles, malformed/ambiguous ownership, unknown statuses, inactive owners and missing schema require ERP-office correction rather than a guessed fallback. After uploading, live data must satisfy these same checks; the portal fails closed while the new endpoint is absent/unavailable.

## Upload

- `.qa/erp-api-upload/sgc-smp-api-current-booking.zip`
- `.qa/erp-api-upload/zalmi-smp-api-current-booking.zip`

Both archives are rooted at `smp-api/`. Upload the SGC archive to SGC and the Zalmi archive to Zalmi, merging their respective live folders. **Keep the host's existing `src/catalog.local.php`**; it is excluded from both archives. Include hidden `.htaccess` files. Do not nest `smp-api/smp-api`.

Zalmi's destination remains `/home/zalmipk_user/www/html/smp-api`; its private configuration can continue pointing at `/home/zalmipk_user/www/html/ERP786-ZALMIDEV/includes/config.php`. No SSH, token reset, ERP SQL migration or new Apache rule is required. Health build after upload: **2026-11-05.booking.9** for both APIs. Refresh the portal form before testing the new workflow.

## Final verification results

All passed:

```text
php .qa/verify_customer_apis.php
php .qa/resale_ownership_checks.php
python -u .qa/verify_shalimar_agents_http.py
php .qa/shalimar_agent_checks.php
python -u .qa/verify_agency_http.py
python -u .qa/verify_customer_live.py
php .qa/sms_test_recipient_checks.php
python .qa/package_current_booking_apis.py
```

Final API and publication/browser reruns passed after the last changes. The API suite also proves co-owner isolation, absent bearer rejection before data lookup, PDO operation, rate-limit and database-outage error contracts, and no full mobiles in access/application logs. The OTP service suite includes cooldown, fresh-recipient resend and invalidation of the old code. Existing authorized-agency count/mapping/bulk creation, customer login/ownership/floor/source-retry, shared-agent assignment, permissions and CSRF behavior passed their regression suites.

All changed portal PHP/JavaScript and 45 PHP files across the API folders passed syntax checks. Archive integrity, correct root, new endpoint, matching verification logic, build marker and private-config exclusion passed: **SGC 34 files**, **Zalmi 27 files**. Tests use disposable databases/session directories and fake API/SMS transports; real customer listings and ERP business records were not created or changed. The portal-only migration is applied locally.
