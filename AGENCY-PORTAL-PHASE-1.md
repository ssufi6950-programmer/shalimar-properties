# Authorized Agency Portal — Phase 1

> Mobile follow-up (7 November 2026): the latest user request replaces the original table-only phone requirement. Below 768px, active listings render as a compact continuous list with all fields and a visible View Details action. Tablet/desktop retain the table. The existing records, filters, pagination and authorization are reused.

Implemented locally on 7 November 2026 in the existing CodeIgniter application.

## Actual schema and reused functionality

The authoritative central key is **authorized_agencies.id**. Local eligibility uses portal_active=1, agency_type='external' and is_shalimar_agency=0. There is no separate local approval flag. A confirmed, present agency_erp_links record from an enabled SGC/Zalmi source supplies source authorization: mapping_status='mapped', erp_authorization_status='authorized', exact erp_authorize='yes', erp_locked='no', erp_restricted='no'.

Customer publication already saves portal_listings.portal_agency_id, agency_erp_link_id, erp_source_id and selling_method='authorized_agency'. These dedicated relationships authorize access. Names and ERP agency IDs do not identify a portal session.

Existing ErpVisibility, agency-directory eligibility conventions, Admin can() permissions, AdminAuditService, CI4 CSRF/session handling and local Bootstrap/Inter/icon/branding assets are reused. The customer app's presentation conventions informed a separate Agency layout and scoped stylesheet. Agency pages use local listing records.

## Migration and local data

**app/Database/Migrations/2026-11-07-000001_CreateAgencyPortalAccess.php** creates:

- agency_portal_accounts: one unique portal_agency_id FK per central agency; unique binary normalized_identifier; display username/type; password hash; enabled/must_change_password flags; temporary expiry; auth_version; creation/update actors and timestamps; last login.
- agency_login_attempts: hashed IP/login identifiers and indexed attempt timestamps for persistent login throttling.

No agency master or ERP data is duplicated. The migration changes no existing agency/link/listing rows and seeds no account.

Applied locally. Before/after counts: **86 agencies, 183 ERP links, 1 customer, 43 listings, 5 saved properties**. New real account table: **0 accounts**. A read-only reconciliation query found **0 agency listings with missing or mismatched central agency/link/source associations**. No legacy assignment was guessed or repaired.

## Admin credential management

Open **Admin → Authorized Agencies → View Agency Details → Agency Portal Access**.

The existing agency detail contains a clean form with read-only agency name, phone/custom choice and a server-generated password. Phone selection shows the saved phone as read-only; custom selection exposes an editable username. Existing-account controls show Enabled/Disabled, actual username/type, creation/last-login timestamps and password-setup state. Reset and Enable/Disable use confirmations.

Viewing requires existing agencies.view. Generate/reset/access-status actions require existing agencies.edit, Admin authentication, its mandatory-password policy and CSRF. No role grant is installed.

| POST route | Action |
| --- | --- |
| /admin/agencies/{id}/portal-access | Generate credentials once per central agency |
| /admin/agencies/{id}/portal-access/reset | Generate a replacement temporary password |
| /admin/agencies/{id}/portal-access/status | Enable/disable the account |

Unique database constraints prevent duplicate agencies/accounts/usernames. Reset and access changes increment auth_version to revoke previous sessions. Inactive/unapproved agencies cannot receive usable credentials. An account can still be disabled after its central agency becomes ineligible.

Plaintext temporary passwords exist only for the generating request and authorized Admin response. They are not stored in the database, session, flash or audit data, and are not sent by SMS/email/WhatsApp. The response uses private/no-store/no-cache/no-referrer headers. Copy controls read visible inputs; leaving clears the password input and browser Back-cache restoration returns to the agency detail. Later GET requests cannot retrieve a password. Lost credentials require Reset Password.

## Login identity and security

Phone login uses existing Pakistani mobile normalization: 03…, 923… and +923… variants share one phone:923… identity. The existing display format is retained without asserting OTP verification. Invalid/missing mobiles such as 92 are rejected; the administrator may choose a custom username.

Custom usernames accept 3–50 ASCII letters/digits plus dots, underscores and hyphens, start with a letter/digit, trim spaces and normalize case. Numeric custom usernames use the same phone normalization and cannot duplicate a phone login.

Temporary passwords use random_bytes(24), **192 random bits**, with uppercase/symbol prefix. They expire after **48 hours**. Only Argon2id hashes, or bcrypt when Argon2id is unavailable, are stored. First sign-in requires a different 12–72 character password with uppercase, lowercase, digit and symbol, current password and matching confirmation.

Agency identity uses separate agency_portal_* session keys. Login/password change/logout regenerate the session ID. Idle timeout is **30 minutes**, maximum authenticated lifetime **8 hours**, subject also to existing CI4 session expiry. Protected requests revalidate account enabled/version, agency active/external classification, temporary expiry and current source authorization.

Generic login errors do not identify valid users. The ten-minute failed-login limits are **5 per normalized username** and **10 per IP**, persisted as hashes. A short MySQL named lock serializes attempts by IP across PHP workers; the account row is locked for known-identity attempts. Unknown usernames perform password-hash work too. Agency login uses neither customer OTP nor ERP passwords; there is no self-registration.

## Screens and routes

Paths are relative to the existing public application base.

| Method | Route | Result |
| --- | --- | --- |
| GET/POST | /agency/login | Phone/custom username and password |
| GET/POST | /agency/change-password | Mandatory first change / later change |
| GET | /agency/dashboard | Actual agency name and real active-listing count |
| GET | /agency/listings | Search, source filter, pagination and HTML table |
| GET | /agency/listings/{id} | Independently authorized read-only details |
| POST | /agency/logout | Clear agency session |

Local URL: http://localhost/shalimar-properties/public/agency/login

## Exact listing access rules

AgencyPortalListings supplies one shared predicate for count/table/details:

1. Exact saved portal_agency_id matches the authenticated agency, and selling_method='authorized_agency'.
2. Existing active publication statuses are live/approved/active, with deal available/active.
3. Exact saved agency link ID, central agency ID and listing source agree; the link is currently mapped, present, authorized, unlocked and unrestricted.
4. Source is enabled SGC/Zalmi. Project mapping source equals listing source and mapping is active.
5. Central agency is active/external. Cached Head/Subhead are present with exact string smp_active='yes'; local project is published.

The existing status/deal fields remain authoritative. Available saved publication timestamps are shown; missing timestamps say **Not recorded**. No new lifecycle status or publication bypass is introduced.

One central account sees eligible SGC and Zalmi listings. Revocation in one source excludes only that source while a separate authorization remains. Remapping a link to another central agency leaves the original listing assignment intact and makes mismatched records inaccessible to both agencies. Direct URLs repeat every check; an inactive record returns 404 even after it was previously visible in the table.

The table shows reference, plot/title, local project, ERP source, asking price, Negotiable, saved publication date, Active and View Details. Search uses reference/plot and treats SQL wildcard input literally. Pagination uses 10/20/50 rows with bounded server-side page numbers. Sold/History/status filters are absent.

Details whitelist safe public fields from saved listing snapshots. Raw payload, customer CNIC/mobile/email, booking proof, OTP rows, internal notes and audits are not returned. Photos require images_status='approved': uploaded markers use the existing protected public media endpoint, and allowed legacy images use local listing uploads. No live ERP lookup occurs.

There are no agency listing create/edit/delete/publish/withdraw/republish/Sold routes or buttons. Agency-only sessions cannot authorize existing Admin/Customer write endpoints. Password change and logout are the agency's account actions.

## Responsive UI

Login, first-password change, Dashboard, Listings and Details share a dedicated layout and Agency stylesheet. Customer branding/type/green palette/Bootstrap conventions are reused. Actual agency name remains visible in the header; mobile navigation has real Home/Listings/Account destinations. Labeled forms, keyboard skip link, safe bottom spacing and touch controls are included.

At **360/390px**, listings are compact continuous list rows with simple separators, readable metadata and full-width View Details. No individual cards or sideways scrolling are needed. The same semantic records retain explicit accessibility roles and column headings. At **768/1024/1440px**, they render as the original table with any scrolling contained inside its focusable region. Search, source filtering and pagination use the same controls on every width.

Browser screenshots use disposable fixture data:

- .qa/agency-portal-login-390.png and -1440.png
- .qa/agency-portal-first-password-390.png and -1440.png
- .qa/agency-portal-dashboard-390.png and -1440.png
- .qa/agency-portal-listings-390.png and -1440.png
- .qa/agency-portal-details-390.png and -1440.png
- .qa/agency-portal-admin-390.png and -1440.png

## Exact files

Created application files:

- app/Database/Migrations/2026-11-07-000001_CreateAgencyPortalAccess.php
- app/Libraries/AgencyPortalIdentity.php
- app/Services/AgencyPortalAccounts.php
- app/Services/AgencyPortalSession.php
- app/Services/AgencyPortalListings.php
- app/Filters/AgencyPortalAccess.php
- app/Controllers/Agency/Auth.php
- app/Controllers/Agency/Dashboard.php
- app/Controllers/Agency/Listings.php
- app/Views/agency/layouts/main.php
- app/Views/agency/auth/login.php
- app/Views/agency/auth/change_password.php
- app/Views/agency/dashboard/index.php
- app/Views/agency/listings/index.php
- app/Views/agency/listings/show.php
- app/Views/admin/agencies/portal_access.php
- app/Views/admin/agencies/credentials_once.php
- public/assets/agency/css/agency-portal.css

Modified:

- app/Config/Routes.php
- app/Config/Filters.php
- app/Config/Services.php
- app/Controllers/Admin/Agencies.php
- app/Filters/AdminPermission.php
- app/Services/AdminAuditService.php (safe audit fields for enabled/username_type)
- app/Views/admin/agencies/show.php

QA/report additions:

- .qa/agency_portal_fixture.php
- .qa/agency_portal_browser.py
- .qa/verify_agency_portal.py
- .qa/run_agency_portal_services.py
- .qa/agency_portal_local_audit.php
- AGENCY-PORTAL-PHASE-1.md

## Verification and rollout

Automated mutations use guarded disposable portal schemas, isolated sessions and fake ERP/SMS dependencies. No real agency credentials, ERP changes or SMS are produced by tests.

| Suite | Result | Log |
| --- | --- | --- |
| .qa/verify_agency_portal.py | 149 passed: credential creation/phone identity/custom collision, mandatory password change, expiry, reset, session revocation, throttling, CSRF/RBAC, independent identities, exact agency/source isolation, all six screens at five widths, real table scrolling and actual browser login/password forms | .qa/agency-portal-regression.log |
| .qa/run_agency_portal_services.py | 29 policy checks passed: inactive states/deals, source/link mismatch, historical remapping, disabled/revoked agencies, private fields, pagination boundaries, literal searches, malformed query defaults and identity normalization | .qa/agency-portal-service-checks.log |
| .qa/verify_customer_app.py | 147 passed: existing Customer app screens/navigation/properties/listings/history/auth layouts and mobile/desktop behavior | .qa/agency-portal-customer-regression.log |
| .qa/verify_agency_http.py | 55 passed: existing agency directory/sync/name suggestions/bulk mapping/source authorization/Admin RBAC and alerts | .qa/agency-portal-mapping-regression.log |
| .qa/verify_shalimar_agents_http.py | 70 passed: existing Admin, SGC/Zalmi resale wizard, current-booking OTP, immutable preview, final ownership check and direct publication | .qa/agency-portal-publication-regression.log |
| .qa/verify_public_seller.py | 80 passed: existing Owner/Agent/Agency identity, public Call/WhatsApp/Email, invalid/revoked contacts, visibility and responsive layout | .qa/agency-portal-public-contact-regression.log |
| .qa/verify_phase2_lifecycle.py | 97 passed: existing secure Edit/Withdraw/Republish, photo upload/ordering/privacy, OTP binding, terminal states, Admin Unpublish, blocked Classic Re-open and disabled Customer manual Sold | .qa/agency-portal-lifecycle-regression.log |
| PHP/Python syntax | Passed for 24 application PHP files and the new Python verification helpers | .qa/agency-portal-syntax.log |

Actual localhost Apache sign-in returns HTTP 200 with the real username/password form and private/no-store headers. The new tables exist locally; account count remains zero. Regression browser screenshots contain disposable fixture records.

Upload the listed application files/assets and run **php spark migrate** against the portal database before using the new authentication/Admin detail features. Existing session storage/base URL and shared assets are reused. No new secret/config key, RBAC grant or ERP API upload is required.

Existing agencies are reused; no duplicate directory exists. One agency has one account with phone or custom username and securely generated/hashed passwords. Agency Portal shows only its own active authorized-agency listings. Sold/History, Self Sell, internal Shalimar Agent listings and listing modification features are excluded.

No production deployment has been performed. The local migration is complete; actual agency accounts are intentionally left for an authorized administrator to generate. No implementation blocker remains. Live deployment and live login with an Admin-generated agency credential require rollout to that environment.

## Phone list presentation follow-up

The latest request supersedes the original table-only phone layout. Only the listing view, Agency CSS and matching browser expectations change:

- app/Views/agency/listings/index.php
- public/assets/agency/css/agency-portal.css
- .qa/agency_portal_browser.py
- This report

Below 768px, existing records form a continuous compact list with thin separators, no individual card borders/rounding/shadows and no horizontal scrolling. Plot/title, project, reference, source, asking price, Negotiable, publication date and Active status remain visible. Each row has a full-width View Details button. Search, source filters, pagination and empty states reuse the same elements. Explicit accessibility roles and hidden column headings preserve the data relationships.

At 768px and wider, the original table layout remains. No controller, query, authentication rule, agency assignment, OTP behavior or database migration changes in this follow-up.

Verification: **153 checks passed**, including 360/390px continuous rows, complete metadata, visible actions without sideways scrolling, retained tables at 768/1024/1440px, agency isolation/security, filtering, authentication and responsive layouts. PHP view syntax passed.

Latest log: .qa/agency-portal-mobile-list.log. The existing mobile/desktop screenshot paths above now show this version.
