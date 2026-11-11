# Existing customer portal activation

Implemented 5 November 2026 in the existing CodeIgniter application. The existing customer layout/assets remain in use; browser-only sample authentication and the fixed demo OTP are no longer part of the live flow.

## What is implemented

- CNIC + registered Pakistani mobile sign-in/sign-up, without passwords.
- Server-to-server identity lookup through dynamic ERP Sources and approved endpoint paths.
- Six-digit random SMS OTP with no fixed production bypass.
- Hash-only OTP storage; encrypted challenge/link mobile data and HMAC identity/rate keys.
- Five-minute expiry, maximum five incorrect attempts, minimum sixty-second resend cooldown, single use, replacement invalidation, and per-mobile/CNIC/IP rate limits.
- Generic decoy challenge for a definite unmatched CNIC/mobile pair; no SMS is sent for that result. Provider failure is a temporary service error, not a negative identity result.
- Customer accounts and separately verified source-specific ERP identity links. Same CNIC under a different ERP mobile requires its own authenticated OTP linking flow under My Profile.
- Customer sessions with ID regeneration, idle/absolute expiry, active-account/version checks, logout and route/AJAX authorization separate from admin permissions.
- On-demand current property reads, per-source failures, counts/filtering/pagination, current-owner checks, and live refresh.
- Ownership remains visible when Head/Subhead public flags are inactive or mapping is absent.
- Opaque session-bound property references and server-side ownership revalidation before resale preparation.
- Database uniqueness for future active resale listings by source/property.
- No property/booking bulk import on login, refresh or resale preparation.

## ERP API deployment

Customer identity and current-owned-property endpoints are implemented in both downloaded API folders and their paths are configured in the local private portal environment. Upload `.qa/erp-api-upload/sgc-smp-api-agencies.zip` to SGC and `zalmi-smp-api-agencies.zip` to Zalmi, replacing their matching `smp-api/` contents including hidden files. Health build: `2026-11-05.agencies.7`. No server administration or ERP business-code changes are required.

See `CUSTOMER-ERP-API-CONTRACT.md` for contracts, actual schema/booking rules and safe deployment checks. The live hosts currently serve the previous customer build, which excludes in-process bookings. Upload the corrected build to change those counts. Missing endpoints on another deployment can still return 404 and verification unavailability. A portal endpoint-path validation delimiter bug has also been corrected. Local automated tests do not establish successful live deployment or real SMS delivery.

The resale submission wizard was previously a browser demo. The live route now provides a verified property review/integration point with submission unavailable. It does not pretend to create a listing. A future valid submit implementation must recheck ownership/eligibility and write only the selected property/listing in a transaction; it must not import the whole portfolio.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/customer/login` | Existing real sign-in UI |
| GET | `/customer/register` | Existing sign-up UI; account created only after OTP and ownership eligibility |
| GET | `/customer/verify-otp` | Session-bound OTP screen |
| POST | `/customer/otp/request` | Validate/normalize input, lookup registered ERP identity, issue OTP |
| POST | `/customer/otp/verify` | Consume OTP, check live ownership, link/create account, regenerate session |
| POST | `/customer/otp/resend` | Cooldown/rate-limited replacement code |
| POST | `/customer/logout` | Clear customer authorization, retain unrelated admin keys |
| GET | `/customer/dashboard` | Protected dashboard |
| GET | `/customer/properties` | Protected My Properties |
| GET | `/customer/api/properties/{owned-link-id}?page=1` | On-demand property page, verified owned link only |
| GET | `/customer/properties/{opaque-reference}` | Revalidated current property detail |
| GET | `/customer/profile` | Verified account and ERP links |
| GET | `/customer/link` | Separately verify another ERP mobile/identity under the same CNIC |
| POST | `/customer/resale/prepare` | Revalidate ownership + exact mapping + public flags + duplicate eligibility |
| GET | `/customer/resale/create?property={opaque-reference}` | Revalidated existing resale integration point |

Local login: `http://localhost/shalimar-properties/public/customer/login`.

Protected AJAX returns 401 for guests. Other customers' links/references/listings return 404. Existing admin authorization remains separate. CSRF now covers customer POST routes as well as existing admin POST routes. The UI updates rotated tokens after AJAX responses.

## Database migration

`app/Database/Migrations/2026-11-05-000002_CreateCustomerAuthentication.php`

Created:

- `portal_customers`: HMAC CNIC/mobile identity keys, encrypted verified mobile, display name, status/version.
- `customer_erp_links`: unique `(erp_source_id, erp_customer_id)`, portal customer FK, encrypted verified mobile, verification timestamps.
- `customer_otp_challenges`: session binding, hashes, encrypted verified identities, expiry/cooldown/attempts/delivery/consumption state. Consumed/failed challenges clear their payload/hash.
- `customer_rate_limits`: HMAC bucket keys and fixed fifteen-minute counters.

Extended existing tables:

- `portal_listings`: nullable portal customer/source/property references, plus a unique generated active-property key. Existing legacy rows remain intact and unassigned; they cannot be exposed to a customer merely because they share a project.
- `portal_owned_properties`: nullable portal customer FK for a future valid selected-property resale save. No records are created here by authentication or dashboard reads.

The migration has been applied locally. Deployments run `php spark migrate`. There is no new public user seeder or test account.

## Existing SMS implementation reused

Inspection source: `D:\xampp\htdocs\sgcacc\admin\property\send_sms.php`, function `send_sms(...)`.

Observed integration:

- Gateway: `https://www.jaageer.com/jazz-sms-api.php`, the existing Jaageer/Jazz relay. The former apex URL redirects to this canonical host; the adapter selects www directly while keeping redirects disabled.
- Existing GET parameters: `key`, `phone`, `msg`.
- Existing message-space convention: `<<>>`.
- Success response: exact trimmed `Message Sent Successfully!`.
- Adapter phone format: national `03XXXXXXXXX`, derived from the verified canonical `923XXXXXXXXX` identity. The adapter can use the configured alternative canonical format if the existing provider requires it.

`App\Services\JazzCustomerSms` reuses this contract. It does not execute/include the legacy campaign script (which contains campaign writes/eval/output) and does not expose a new public SMS relay. TLS is verified, redirects disabled, timeouts/body limits applied, and provider failures are treated as failures. Gateway responses, raw URLs containing secrets, OTP and full CNIC/mobile are not logged by the adapter.

The existing literal gateway key was copied into the ignored private `.env` without displaying it. No live SMS was sent during verification.

## Private configuration

- `customerPortal.enabled`: enables the real module. The existing enabled local setting is reused; this is no longer a sample-auth flag.
- `customerPortal.identityKey`: stable base64-encoded 32-byte encryption/HMAC secret generated privately locally. Keep it private and stable; existing encrypted records depend on it.
- `customerPortal.identityPaths`, `customerPortal.propertiesPaths`: implemented relative API paths by source code; configured locally for SGC and ZALMI.
- `sms.enabled`, `sms.gatewayURL`, `sms.gatewayKey`, `sms.phoneFormat`: existing provider configuration, private and server-only.
- Existing ERP Sources/credential references, approved hosts, TLS validation and DNS overrides are reused.

No server restart/SSH change or new unrelated SMS provider was introduced. Preserve the existing private environment file when deploying. Do not paste secrets in issue reports or this documentation.

## Eligibility and data handling

- A new authenticated session requires at least one current property from a verified ERP identity.
- Definite no ownership: respectful no-properties state; no unnecessary account/link persistence.
- Inconclusive/unavailable ownership: temporary service error.
- One valid owned-property source and another unavailable: access allowed with a partial-source warning.
- Same CNIC/mobile verified in both ERPs can share a portal customer.
- Different registered ERP mobiles are never automatically granted by matching CNIC/name; use the separate authenticated OTP linking flow.
- Source/customer link conflicts are rejected instead of silently transferring an identity between portal accounts.
- Current ownership requires the authenticated ERP identity to occur in the normalized current-owner proof and statuses to be `current`/`active`; transferred/cancelled rows are excluded.
- Public resale requires both live and locally synchronized Head/Subhead flags to be exactly `yes`, a present active mapping, published project, and no active/pending listing for that source/property.
- All owned rows remain visible regardless of that public eligibility outcome.
- Property detail and preparation re-fetch current ownership and never authorize by a browser-supplied ERP customer/property ID.
- Server session references last ten minutes; a prepared-resale pointer lasts five minutes. These are authorization pointers, not persisted property imports.

## Retention and rate limits

OTP lifetime is at most 300 seconds, wrong attempts at most five, cooldown at least sixty seconds. Fixed fifteen-minute issue buckets allow up to 3 requests/resends per mobile, 5 per CNIC and 10 per IP. Verification additionally has a 30-attempt IP bucket. Challenges are tied to their initiating server session binding; replacing a valid pair challenge invalidates previous ones.

Expired state can be pruned with `php spark customer:prune-auth`; it only removes challenges/rate windows older than one day and leaves customer/link/property records intact. No scheduler was installed automatically.

## Created/modified implementation files

New backend files:

- Config: `app/Config/Sms.php`; expanded `app/Config/CustomerPortal.php`.
- Libraries: `app/Libraries/CustomerIdentity.php`, `CustomerSecrets.php`.
- Interfaces: `app/Services/CustomerIdentityProvider.php`, `CustomerSmsGateway.php`.
- Services: `CustomerErpApi.php`, `JazzCustomerSms.php`, `CustomerRateLimiter.php`, `CustomerOtpService.php`, `CustomerPortfolio.php`, `CustomerSession.php`.
- Models: `app/Models/PortalCustomerModel.php`, `CustomerErpLinkModel.php`.
- Filter: `app/Filters/CustomerAccess.php`.
- Exception: `app/Exceptions/CustomerFlowException.php`.
- Migration above; command `app/Commands/CustomerAuthPrune.php`.

Connected/modified:

- `app/Config/Services.php`, `Routes.php`, `Filters.php`.
- `app/Controllers/Customer/PortalController.php`, `Auth.php`, `Properties.php`, `Resale.php`.
- Existing customer auth/dashboard/properties/profile/notifications/layout views, plus `resale/ownership_review.php`, `resale/live_index.php`, `properties/unavailable.php`.
- `app/Helpers/customer_helper.php` for the public website link.
- `public/assets/customer/js/customer-live.js`; the old demo JS is no longer loaded by the live layout.

The existing sample library/old wizard source is not an authentication or ownership fallback. Explicit real routes, server guards and live layouts determine access.

## Tests and verification

```text
php .qa/customer_auth_checks.php
python -u .qa/verify_customer_live.py
```

The first uses isolated MySQL and injected test-only ERP/SMS dependencies to cover SGC/Zalmi/both, repeated login, different registered mobiles/separate linking, bad inputs, no demo bypass, wrong/expired/single-use OTP, cooldown/rate limits, SMS failure, no ownership, partial/complete outages, transfer/cancellation, inactive/unmapped properties, same-project multiple mappings, encrypted/hash state, duplicate listing prevention and no bulk saves.

The second boots the actual CI4 routes/filters/controllers in an isolated local test server and database. The test-only front controller is under `.qa/`, verifies a scoped schema and injects fixtures there; no production route/configuration can enable a mock OTP bypass. It checks CSRF, session regeneration, real account rendering, per-source property pages, customer/admin separation, cross-customer IDOR, fresh resale ownership checks, no listing/property writes, and browser desktop/mobile behavior. Temporary test accounts/state/databases are cleaned up; browser screenshots remain under `.qa/customer-live-*.png`.

Existing catalog/admin regression checks should continue to pass. Test success is not evidence of real SMS delivery or a working live ownership API; live upload status must be checked separately as described above.

## Final verification result (5 November 2026)

Passed: customer OTP/identity/ownership service checks, actual CI4 customer HTTP and desktop/mobile browser journey with isolated fixtures, the existing admin/RBAC/ERP CRUD HTTP regression, PHP syntax checks and JavaScript syntax check. The migration is applied locally. The read-only production-database check still shows 3 projects, 7 ERP mappings, 74 Heads, 472 Subheads, 37 existing listings, and zero newly persisted customer/link/owned-property rows. Real SMS delivery was not tested, and no working live identity/current-ownership endpoint is claimed.

## Local SMS testing preference

The owner requested that portal OTP messages be redirected to one private test number instead of actual customers. This is now enabled locally through `sms.testMode = true` and `sms.testRecipient` in the ignored `.env`. It changes only the SMS delivery destination: ERP lookup, the challenge's original verified identity and the registered-mobile account relationship are not rewritten.

The override works only for a localhost/loopback app base URL and local requests. A missing/invalid test recipient or use on a public deployment blocks delivery; it never silently falls back to a customer number. Leave the test mode enabled until the owner explicitly requests real-customer delivery. This preference affects the portal's OTP adapter, not independent legacy ERP campaign scripts.

`php .qa/sms_test_recipient_checks.php` verifies routing, gateway phone formats, blocked fallback and local-only enforcement with a capturing transport. It sends no real SMS. No actual message was sent while applying this configuration.


## Customer API fix verification (5 November 2026)

Both upload archives were checked for PHP syntax, required direct endpoint files, hidden access rules, preserved private configuration and byte-for-byte archive integrity. Passed: actual customer API HTTP/SQL tests for both sources, mysqli/PDO, all sixteen raw flag combinations, current ownership/transfer/cancellation/return/co-owner ambiguity checks, API client path validation, catalog regression, OTP/ownership service checks, captured SMS routing checks, and the actual CI4 HTTP plus desktop/mobile browser journey. No real SMS was sent.

Read-only live deployment probe: both health endpoints returned HTTP 200 with the OLD build `2026-11-05.catalog-fix.3`. SGC customer endpoints returned 404; Zalmi returned 301. These results confirm the new customer build was not being served at verification time. Upload remains necessary; no claim of successful live verification is made.

During the final browser test the local MariaDB service stopped and restart reported a corrupt Aria recovery checkpoint. Recovery files and all 24 Aria tables were backed up under `.qa/mysql-aria-backup-20261105-065140/`; the numeric Aria log was moved to that backup, keeping `aria_log_control` in place. The original MySQL executable/configuration was restarted; no portal/ERP table was replaced or account permission changed. The browser suite then passed. Read-only checks of the four customer/listing tables returned OK: 0 portal customers, 0 ERP identity links, 0 owned-property imports and 37 preserved existing listings.


## SMS redirect fix (5 November 2026)

The next real login attempts matched an ERP identity but their challenge delivery state was failed. A request without credentials/phone/message confirmed that `https://jaageer.com/jazz-sms-api.php` returns HTTP 301 to the www host. The adapter correctly refuses redirects, so its old URL prevented delivery. Updated the private environment/default to the canonical www URL and made the adapter canonicalize the validated legacy apex setting to the same known endpoint. No API upload, credential reset or ERP change is required. The test recipient remains 03427056415. Destination validation, TLS verification, disabled redirects and test-only routing are retained. Tests use a capturing transport; delivery to a handset requires another real login attempt.


## Current-booking count correction (5 November 2026)

Removed the API-only in_process=no ownership filter in both ERP API folders. Current locked=no, cancel=no, company-matched sale/resale bookings still require valid booking/inventory statuses and unique current-owner proof. In-process or unknown document/approval states remain privately visible but are labeled and blocked from new resale. No ERP business state is updated. Pending transfers cannot resurrect a locked historical seller. The raw processing flag, transaction type and actual ERP booking status are returned to the portal; details/cards display the processing state.

The user-specified Sale report (Head 49/Subhead 555) has 33 current Sale records in the local snapshot, of which 22 were omitted by the old filter. An actual patched API HTTP read and portal normalization return all 33 in that scope plus 3 current Smart City resales and 1 Gulberg property, total 37. All 22 in-process rows are resale-ineligible. API/SQL, OTP/portfolio and actual HTTP/browser regression tests pass. Upload the matching ownership-fix ZIPs and refresh to activate the corrected live response.


## Dashboard and My Properties source recovery (5 November 2026)

The reported dashboard screenshot showed a failed Zalmi property request and a misleading zero; My Properties subsequently loaded that source. Both pages already use the same per-link property endpoint and ownership filters. A fresh read-only live comparison returned SGC 37 and Zalmi 1, total 38, with no normalization losses. The historical error log recorded only a generic RuntimeException, so the original exception's exact cause cannot be recovered from that log.

The shared portfolio client now retries transient property GET failures (network failure, HTTP 408/500/502/503/504) at most three attempts, with short abortable delays. It never replays OTP or resale POST actions. A failed/unloaded source shows an em dash instead of an authoritative zero, and overall/eligibility counts show a plus while results are incomplete. Manual refresh restores counts and source messages without duplicate cards. Returning through the browser's back/forward cache reloads current properties. The script URL includes its file modification version so an older cached script cannot mask the update.

Unexpected customer-operation logs now include exception type plus basename/line, without exception arguments, messages, secrets or identity data. This is a portal-only correction; no ERP API package upload is needed.

Passed actual CI4 HTTP/browser regressions: a one-time Zalmi failure is retried and recovered, persistent failure stops after three attempts with an unavailable/partial count, successful refresh restores both sources, Dashboard/My Properties show the same Zalmi ownership, browser Back reloads sources, mobile/desktop layouts fit, no uncaught JavaScript errors, existing authentication/CSRF/IDOR/current ownership checks pass, and no bulk property/listing or real SMS write occurs. PHP/JavaScript syntax checks passed.


## ERP floors (5 November 2026)

Added actual child-unit floor metadata from pro_inventory_floor.sub_property_id to both customer APIs and portal cards/details/search/resale review. Example labels verified on local real ERP rows: BBT-03 FIRST FLOOR; BBT-04 SECOND FLOOR (Studio). The join is optional and deterministic so missing/duplicate floor data cannot remove or duplicate bookings. API response fields: floor_title, floor_category, floor. Local property counts and pending-approval resale restrictions remain unchanged. Passed both API SQL/HTTP suites, actual snapshot reconciliation, CI4 HTTP/browser desktop/mobile tests, floor detail/card/search assertions and PHP/JavaScript syntax checks. Upload the matching floor-fix ZIPs (build 2026-11-05.floor.6), then refresh My Properties. No ERP schema or business-state change is required.


## Authorized Agencies (5 November 2026)

The existing verified resale review now offers source-specific, mapped and portal-active authorized agencies from the local directory. Agency choices are confirmed server-side against freshly checked ownership and authorization. The full listing submission workflow remains unchanged. See AUTHORIZED-AGENCIES-IMPLEMENTATION.md for the applied migration, admin mapping/alerts, API upload packages and verification.
