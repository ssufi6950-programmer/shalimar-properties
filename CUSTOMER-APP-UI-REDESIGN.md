# Customer portal app interface

> OTP testing follow-up (7 November 2026): customerPortal.showOtpPreview enables the session-bound **Testing OTP** above login/link and resale Create/Edit/Republish code inputs on localhost, LAN or live hosts. It is enabled locally for temporary testing; set it to false before actual customer use. See CUSTOMER-OTP-TEST-PREVIEW.md for rollout and the single setting used to hide it.

> Contact-policy follow-up (6 November 2026): eligible central Shalimar Agent and Authorized Agency business phone/email values now display automatically, without a separate contact approval. Existing ownership OTP, source authorization, public listing visibility and explicitly supplied Self Sell contact rules remain in place. See [PUBLIC-SELLER-CONTACT.md](PUBLIC-SELLER-CONTACT.md).

> Public seller-panel follow-up (6 November 2026): Self Sell now includes optional Public Contact Email in the existing wizard, review, restored edit/republish draft and OTP-authorized public preview. The customer app navigation, dashboard, responsive lists and CSS remain unchanged. See [PUBLIC-SELLER-CONTACT.md](PUBLIC-SELLER-CONTACT.md) for contact-source rules, the small agency email approval migration and deployment instructions.

> Resale eligibility follow-up (6 November 2026): current properties with exact ERP `booking_in_process='yes'` now offer List for Resale when the remaining ownership, mapping and visibility rules permit it. ERP approval remains pending. Property-specific OTP and final fresh ownership verification are still required. NULL/unexpected processing values remain blocked. See [IN-PROCESS-RESALE.md](IN-PROCESS-RESALE.md) for the two live API patches and verification.

> Compact phone headings (6 November 2026): below 768px, page titles replace the repeated introductory taglines, descriptions and Back to Home links (Home remains in the bottom navigation). Properties has Refresh beside its title; Resales has a shorter Sell Property action. Successful ERP loads share a compact line with source names and counts; loading/retry/error messages remain visible in full. The dashboard launcher introduction is hidden on phones. Desktop explanatory text, custom detail back links, ownership rules and the existing continuous phone lists are preserved.

Compact-heading validation: the existing customer app browser suite passed **147 checks** across 360, 390, 768, 1024 and 1440px; the customer live HTTP/browser suite passed **50 checks**, including retry, partial-source errors and refresh recovery. PHP and JavaScript syntax checks passed. Screenshots for Dashboard, Properties, Resales and Profile were reviewed at 390px. Logs: `.qa/customer-app-compact-mobile.log` and `.qa/customer-app-compact-live.log`. Tests used disposable data and fake ERP/SMS providers. No database migration or ERP API upload is required.

> Mobile follow-up (6 November 2026): the latest request supersedes the original table-only phone requirement. Below 768px, Properties, Resales, History and listing activity now show compact, continuous list rows with visible actions and no sideways scrolling. Tablet/desktop layouts remain tables. The same records and controls are reused; no backend rule or data source changed.

Implemented locally on 6 November 2026. The existing CI4 customer portal now uses one responsive app layout. This is a customer UI update, with customer-scoped presentation queries and the explicitly requested removal of customer manual Sold actions. It does not create a native app or change the Admin/public website designs.

## Customer screens

| Screen | Result |
| --- | --- |
| Sign In, Sign Up, OTP and identity linking | Existing forms/services/routes with the app typography, spacing, touch targets, focus states and responsive authentication layout. Local-only OTP preview is preserved. |
| Home | Real customer name, four live/local summary measures, six working navigation options and source availability. No fake counters, property cards or notification shortcuts. |
| My Properties | Responsive records fed by the existing authenticated, paginated SGC/Zalmi property APIs: table on tablet/desktop, compact list rows on phones. Search, project/source/eligibility filters, 10/20/50-row pagination, source errors, partial/loading/empty states and existing View Details/List for Resale actions. |
| Property details | Dedicated live ownership detail screen showing actual ERP fields, floor, booking processing, source, size, eligibility and the existing authorized resale action. |
| My Resale Listings | Customer-scoped local records (desktop table / phone list) with reference/property, project, ERP source, asking price, Negotiable, selling method, status, publication time and authorized actions. Sold records are excluded from this current-list view. |
| Resale History | Dedicated customer-scoped local history (desktop table / phone list) of inactive/historical records, including Sold, withdrawn, admin-unpublished and historical pending records. Search/status filters and server pagination. Sold records remain read-only. |
| Listing details | Dedicated record view with saved description, price/Negotiable, actual uploaded photos, representative, source/property information, stored timestamps and the existing lifecycle event history table. No raw ownership proof is rendered. |
| New resale, Edit and Republish | Same four-step wizard, photos, selling methods, booking OTP, buyer preview, final verification and direct publication. Styled to fit the app shell. Valid same-session drafts survive navigation. |
| Buyer preview and success | Existing components and confirmation behavior inside the new shared layout. |
| My Profile | Actual account name, masked mobile and existing verified ERP connections. Existing identity-linking action remains; no unsupported profile editing. |
| Help & Support | New dedicated screen using the public site's existing business telephone, WhatsApp, email and address constants. Working contact links and workflow help; no fake ticket form or chat service. |
| Unavailable/error screens | Existing safe messages/back links within the shared app shell. |

The old Notifications route is retained for compatibility, but its empty screen is not promoted as a working feature. No notification bell, unread dot, inbox count or invented feature was added.

## Home and navigation

Home exposes My Properties, My Resale Listings, Sell My Property, Resale History, My Profile and Help & Support. Sell My Property opens My Properties so a currently owned eligible property is selected before the existing wizard starts.

Desktop has a compact branded navigation header, account menu, Support and sign-out access. Phones have Home/Properties/Resales/Profile bottom navigation, active states and safe bottom spacing. History is accessible from Home/account navigation and uses the Resales mobile tab.

All six destinations fit above bottom navigation on the tested 360px/390px phone viewports. Phone navigation tiles intentionally use concise labels; descriptive copy remains on desktop. Neither layout duplicates customer business logic.

Summary sources:

- My Properties: existing live ERP reads for the signed-in customer's separately verified identities. A failed/incomplete source yields an explicit partial count, not a fabricated zero. Existing retry, composite deduplication and API pagination are retained.
- Active Resales: local customer-owned records with active status/deal.
- Resale History: local customer-owned records outside those active states.
- Sold Listings: local customer-owned records with Sold status/deal; links to the Sold history filter.

The summary does not bulk-import properties. A live listing record's status and current public source/project/mapping eligibility remain separate concerns, as before.

## Tables, details and actions

Properties, resale listings, history and listing activity reuse one semantic table data structure. At 768px and wider they render as tables with scrolling contained inside the table region. On phones, records form a continuous compact list with thin separators, without individual card borders, rounded corners, shadows or gaps. Property/reference, project, source, price/status and actions remain readable. Publication/closed timestamps remain available through View Details rather than taking space in each phone row. No data or action is duplicated. Explicit table/row/cell roles and visually hidden column headings retain accessible structure. Loading/empty messages, filters and pagination use the same elements in both layouts. Detail links use the existing opaque property references or owner-authorized listing IDs.

`CustomerListingReadModel` provides presentation-only count, search/filter and pagination queries. Every query is scoped to the authenticated customer, independent of request-supplied IDs. Existing `ListingLifecycle::decorate()` supplies the customer-withdrawn distinction rather than inventing a second transition rule.

Current listings offer View Details, Edit, Withdraw and eligible fresh-OTP Republish. History rows offer View Details; a customer-withdrawn record's detail page may expose the existing secure Republish action. Admin-unpublished/rejected/Sold restrictions remain enforced server-side. Withdraw uses a shared Bootstrap confirmation with cancel, focus handling and the existing CSRF-protected/version-checked endpoint.

History uses stored asking prices/timestamps and says **Not recorded** when a timestamp is missing. It does not manufacture an original price or claim an ERP transfer occurred. Saved revision records remain intact.

## Intentional Sold rule update

The earlier Phase 2 implementation had a customer manual Mark as Sold action. This task explicitly supersedes that behavior: **no customer Mark as Sold button or HTTP endpoint remains**. The controller also rejects any transition action other than Withdraw, rather than relying on hiding the button.

Existing Sold rows/events are preserved and read-only, excluded from current listings and included in history/counts. The internal lifecycle service is unchanged; no old rows or status history were rewritten. No ERP automatic Sold detector was added.

Public listing/status behavior was not changed in this customer UI task. The existing public site excludes Sold offers from active availability. A future ERP detector/public Sold archive can consume the preserved records in its own phase; this task does not claim that future automation/archive was implemented.

## Wizard state and security

The existing server review can be retained only while its original expiry, customer, opaque reference, exact source/property/mapping and lifecycle version/fingerprint match. Navigating away does not extend its lifetime or change any authoritative identifier. Changed/expired contexts start a fresh review.

The browser's session storage holds only that review's entered material fields, photo references and step, under a customer/submission-specific key. It does not store CNIC, private ERP mobile, OTP, authorization proof or credentials. Logout clears the draft keys. Multipart photo responses also refresh the native sign-out form CSRF fields through the existing shared token updater. Private upload binding, final validation and one-use OTP authorization remain authoritative.

Returning to an editor restores the proposed changes and explains that they must be reviewed before requesting a code. Existing code verification, preview and final fresh booking/ownership checks still run; navigation does not publish or authorize an edit.

## Exact application files

New:

- `app/Services/CustomerListingReadModel.php`
- `app/Views/customer/components/page_header.php`
- `app/Views/customer/components/listing_actions.php`
- `app/Views/customer/components/pagination.php`
- `app/Views/customer/components/confirm_dialog.php`
- `app/Views/customer/resale/live_details.php`
- `app/Views/customer/support/index.php`
- `public/assets/customer/css/customer-app.css`
- `public/assets/customer/js/customer-app.js`

Updated:

- `app/Controllers/Customer/Dashboard.php`
- `app/Controllers/Customer/Resale.php`
- `app/Config/Routes.php` (customer entries only)
- `app/Helpers/customer_helper.php`
- `app/Views/customer/layouts/main.php`
- `app/Views/customer/layouts/header.php`
- `app/Views/customer/layouts/footer.php`
- `app/Views/customer/layouts/auth.php`
- `app/Views/customer/dashboard/index.php`
- `app/Views/customer/properties/index.php`
- `app/Views/customer/properties/details.php`
- `app/Views/customer/resale/live_index.php`
- `app/Views/customer/resale/live_create.php`
- `app/Views/customer/profile/index.php`
- `public/assets/customer/js/customer-live.js`
- `public/assets/customer/js/resale-live.js`
- `public/assets/customer/js/listing-lifecycle.js`

Shared components cover navigation, page titles/back links, status/method/date formatting, action authorization presentation, table/pagination styling and confirmation dialogs. The appended customer-only stylesheet is loaded after the existing CSS and is scoped to `.customer-app`; Admin/public stylesheets are untouched.

Customer route additions: `GET /customer/resale/history` and `GET /customer/support`. Existing customer paths remain. The previous customer `POST /customer/resale/{id}/sold` route is removed; Withdraw remains.

## Verification

The original full redesign passed **533 automated checks**, plus PHP/JavaScript syntax checks. The phone follow-up has a separate browser verification below. Tests use guarded disposable portal schemas, isolated sessions and fake ERP/SMS providers. No real SMS is sent or live ERP data modified by these tests.

| Suite | Result |
| --- | --- |
| `.qa/verify_customer_app.py` | **129 passed**: normal login/Home return, six genuine options, actual summary counts, customer-only tables/details, search/status filters, both table pagination paths, read-only Sold/history, manual Sold HTTP rejection, all five widths, 360/390px first-screen destinations, draft return, real confirmation cancellation, auth layouts and no uncaught JS errors or UI data mutations. |
| `.qa/verify_customer_live.py` | **50 passed**: existing identity linking, property authorization/floor/eligibility, foreign references, retry/partial-source behavior including Zalmi, refresh, OTP/browser login and logout. Browser assertions target the current property/summary UI. |
| `.qa/verify_phase2_lifecycle.py` | **93 passed**: existing protected edit/withdraw/Republish, photo uploads/ordering/removal, Negotiable, OTP binding/final verification, concurrency and admin restrictions. The former manual Sold expectation is updated to require rejection and preserved read-only Sold records. |
| `.qa/verify_phase1_safety.py` | **65 passed**: legacy Approve/Re-open restrictions, real customer entry routing, public contact privacy, source restrictions and modern Unpublish. |
| `.qa/verify_shalimar_agents_http.py` | **69 passed**: actual browser wizard, current-booking OTP, preview and direct SGC/Zalmi publication, representative eligibility and admin record integration. |
| `.qa/customer_auth_checks.php` | **44 passed**: existing authentication/identity/ownership controls. |
| `.qa/resale_ownership_checks.php` | **37 passed**: sealed authorization, expiry, attempts, resend and failure handling. |
| `.qa/admin_portal_data_checks.php` | **32 passed**: existing real admin read models, ownership/history, audit rollback, Unpublish and terminal-state protection. |
| `.qa/run_visibility_http.py catalog` | **14 passed**: existing public visibility/mapping/Sold restrictions and mapping layout. |
| Syntax | Passed for customer PHP files and all four affected JavaScript files. |

Viewport coverage: **360, 390, 768, 1024 and 1440px**. Current follow-up checks require flat list rows without horizontal scrolling at 360/390px, and `table`/`table-cell` layout at 768/1024/1440px. Actions remain visible with touch targets of at least 44px. Filters, pagination, fixed bottom navigation and wizard action placement remain covered. Bootstrap confirmation tests wait for its real shown event before interacting.

Screenshots include:

- `.qa/customer-app-dashboard-1440.png` and `.qa/customer-app-dashboard-390.png`
- `.qa/customer-app-properties-1440.png` and `.qa/customer-app-properties-390.png`
- `.qa/customer-app-resale-1440.png` and `.qa/customer-app-resale-390.png`
- `.qa/customer-app-resale-history-1440.png` and `.qa/customer-app-resale-history-390.png`
- `.qa/customer-app-resale-101-1440.png` and `.qa/customer-app-resale-101-390.png`
- `.qa/customer-app-resale-101-edit-1440.png` and `.qa/customer-app-resale-101-edit-390.png`
- `.qa/customer-app-property-details-1440.png` and `.qa/customer-app-property-details-390.png`
- `.qa/customer-app-profile-1440.png` and `.qa/customer-app-profile-390.png`
- `.qa/customer-app-support-1440.png` and `.qa/customer-app-support-390.png`
- `.qa/customer-app-login-1440.png` and `.qa/customer-app-login-390.png`
- `.qa/customer-app-register-1440.png` and `.qa/customer-app-register-390.png`

The test screenshots contain disposable fixture names/counts, not newly inserted real customer data.

Logs: `.qa/customer-app-ui.log`, `.qa/customer-app-live-regression.log`, `.qa/customer-app-lifecycle-regression.log`, `.qa/customer-app-phase1-regression.log`, `.qa/customer-app-publication-regression.log`, `.qa/customer-app-auth-regression.log`, `.qa/customer-app-otp-regression.log`, `.qa/customer-app-admin-service-regression.log`, `.qa/customer-app-public-regression.log`.

New QA support: `.qa/customer_app_fixture.php`, `.qa/verify_customer_app.py`. Existing fixture provider `.qa/customer_http_fixtures.php` supports an explicitly requested test-only larger property collection for pagination. Existing live/lifecycle test selectors and the intentional Sold contract are updated; production services do not use this fixture option.

A preserved checksum baseline of **514 existing core-service/Admin/public files** remains unchanged (`.qa/customer-app-backend-baseline.json`). This includes the existing OTP, publication, portfolio, lifecycle, photo, agent/agency, contact and legacy protection services, customer session/Auth controller and Admin/public implementation files.

## Database and rollout

**No new database migration, ERP API change, bulk property import or credential change.** Existing customer/account/snapshot/history relationships are reused. The completed Phase 2 migration remains required as before.

Upload the listed customer application/assets/read-model/routes/helper files together for deployment; the existing CI4/PHP/Bootstrap infrastructure is sufficient. Neither ERP's API folder needs an upload for this UI update. Live deployment/API/SMS verification was not performed.

No requested UI screen remains blocked. Existing environmental ERP availability issues are surfaced through the current source/error states; this task does not repair ERP ledgers/schema or implement notifications, inquiries, automatic Sold detection or scheduled verification.

## Phone list layout follow-up

The responsive record implementation uses `app/Views/customer/properties/index.php`, `app/Views/customer/resale/live_index.php`, `app/Views/customer/resale/live_details.php`, `public/assets/customer/js/customer-live.js` and `public/assets/customer/css/customer-app.css`, plus the matching browser assertions in `.qa/verify_customer_app.py` and this report.

Field labels, roles and presentation classes are reused on the existing records. The latest correction changes the phone CSS to a continuous list, with compact spacing and simple row dividers; individual cards are removed. Properties retain eligibility reasons; resales retain prices, Negotiable, source, representative method, status and dates. The same View Details/Edit/Withdraw/eligible Republish buttons are reused. Sold history stays read-only. Desktop queries, ERP requests, authentication, OTP and lifecycle policies are untouched.

Follow-up verification: **147 checks passed**, including continuous list rows without card borders/rounding/shadows, phone labels/actions without sideways scrolling at 360/390px, retained tables at 768/1024/1440px, search, filters, pagination, own-record access, read-only Sold history, draft return, confirmation dialogs and authentication layouts. PHP/JavaScript syntax checks passed. Protected service/Admin/public file checksums remain unchanged. Tests used disposable data and fake ERP/SMS.

Latest follow-up log: `.qa/customer-app-mobile-list.log`. Existing screenshot paths above now show the updated phone list and unchanged desktop table presentation.

## Dashboard banner and intermittent property loading fix

The large dashboard welcome banner has been removed. A plain welcome heading remains; navigation and summary data are preserved.

The reported intermittent loading issue was reproduced against actual localhost Apache with parallel SGC/Zalmi property requests. Direct live API samples returned HTTP 200 consistently (SGC 37 properties, Zalmi 1, approximately 0.25-0.34 seconds), and portal normalization returned the same counts. However, parallel local route requests intermittently returned empty HTTP 404 responses or generic errors.

The trace established a configuration-prefix mismatch: deployed `.env` keys use `customerPortal.*`, while CI4 BaseConfig looks for the lower-case short prefix `customerportal.*`. It therefore fell back to Windows process environment lookups. During threaded Apache requests, process environment cleanup could leave settings at defaults despite the correct value still being present in the request-local environment. Diagnostic logs captured `config_enabled=0` while `env_enabled=1` and the process value was absent. This disabled the portal for only one of the concurrent source requests. The single-threaded fake API/browser test server did not reproduce this Apache-specific race.

`Config\CustomerPortal::getEnvValue()` now reads the existing mixed-case keys from request-local ENV/SERVER values first, then retains CI4's normal lower-case/full-prefix fallback. It preserves the same enable flag, identity key and other configured values. An intentionally disabled or unconfigured portal still remains disabled. No credential was changed and no missing secret is generated as a fallback.

`Customer\PortalController` also uses distinct logging placeholder names for the exception origin, so logging context does not replace it with the caller location. Temporary filter/exception diagnostic hooks were removed after tracing the issue.

Validation after the fix:

- 20 actual localhost property requests in 10 concurrent source pairs: all HTTP 200, consistently SGC 37 and Zalmi 1.
- 5 actual browser dashboard loads: both sources complete, total 38, banner absent every time.
- 8 isolated request-local configuration checks, including missing/changing process environment, identity-key retention, typed settings, deliberate disable, SERVER-only values and lower-case compatibility.
- Existing mocked live-properties/auth/retry/partial-source/browser regression, resale OTP service regression and customer UI regression were rerun.

Live diagnostic requests were read-only and sent no OTP/SMS. Temporary diagnostic sessions were created separately and destroyed; existing customer sessions, ERP data, listings and ownership snapshots were not changed. Automated mutations continue to use guarded disposable schemas and fake ERP/SMS.

Application changes for this fix: `app/Config/CustomerPortal.php`, `app/Controllers/Customer/PortalController.php`, `app/Views/customer/dashboard/index.php` and `public/assets/customer/css/customer-app.css`. No ERP API upload, database migration, retry expansion or stale-property cache was introduced.

Logs: `.qa/property-loading-live-probe.log`, `.qa/property-loading-local-probe.log` (before), `.qa/property-loading-local-fixed.log`, `.qa/property-loading-actual-browser.log`, `.qa/property-loading-config-checks.log`, `.qa/property-loading-live-regression.log`, `.qa/property-loading-otp-regression.log` and `.qa/property-loading-ui-regression.log`. CLI-only diagnostic helpers live under the protected `.qa` folder and are not deployment files.
