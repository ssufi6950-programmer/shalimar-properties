# Phase 1 — critical workflow safety fixes

Implemented locally on 6 November 2026. Scope: legacy publication/reactivation, public posting entry points, and public listing contact routing. No database migration, ERP database change, ERP API update or credential change was needed.

## Legacy publication and reactivation

The unsafe application endpoint was `POST /public/admin/classic?tab=listings` with `do=approve` or `do=reopen`. Both previously wrote `status=live`; Re-open also changed a closed `deal` back to `available`.

These actions now return **HTTP 409**, including for a super administrator. Existing RBAC and CSRF checks run first. A clear message explains that publication requires customer current-booking OTP plus final ownership verification, that admin approval is unnecessary, and that a secure reactivation workflow is not yet available. Denials are audited as `resale.legacy_reactivation_blocked` without changing the listing.

The mutation branches were removed from Classic Admin and replaced with rejection guards. Its Approve/Publish/Re-open buttons were removed. Old pending records are labeled as historical records, rather than an approval queue. Image review remains separate; approving an image cannot change a listing's publication status.

`LegacyListingPolicy` also guards the shared `PortalCatalogStore::write()` adapter. A locked existing record must already be active and available before a legacy writer can retain that active state. Sold, cancelled, pending, unlisted or missing records cannot become active through that writer. The guard recognizes the case/space behavior of the database status collation and rolls back the entire write on rejection. This also blocks a stale legacy payload from reopening a listing unpublished by another request.

The existing `POST /admin/resale/{id}/unpublish` service was preserved, including terminal-state protection, audit transaction, ownership/history preservation and idempotence. No new listing statuses or approval workflow were introduced.

Other entry points inspected:

- Direct `html/admin.php` and `html/admin-classic.php` already reject POST with HTTP 405 and forward GET to protected CI4 administration.
- Eight executable sample `admin.php` copies now use that same protected entry adapter. Their old standalone handlers cannot run.
- `html/includes/home/admin.php` already exits through the protected adapter; it was checked and not modified.
- Customer publication remains the existing OTP-authorized `/customer/resale/publish` route.
- Existing project/catalog/mapping visibility toggles remain unchanged. They do not change listing status/deal or reopen a terminal listing; restoration of eligibility for an otherwise active listing remains the existing catalog behavior.
- The explicit CLI legacy import retains its existing insert-only-missing behavior. It is not an HTTP publication endpoint and was not run against real portal data.

No remaining direct legacy Approve/Re-open handler was found reachable through the reviewed public/admin entry points. This is a focused route review, not a claim about uninspected server configuration or external database access.

## Post Property routing

The main `html/post-property.php` and all eight sample copies are now redirect-only compatibility entry points. They never start the old poster session, inspect submission fields, generate/show a demo OTP, create synthetic holdings or write a listing. Old GET requests return 302; old POST requests are discarded with a 303 redirect. Query parameters, including old purpose/return parameters, cannot choose a different destination.

All existing Post Property/Sell/List with us links continue to work through those aliases without redesigning the public navigation. The destination uses the configured portal base **path**, not a request-supplied host or redirect URL:

1. `/public/customer/post-property`
2. Guest: existing customer login and OTP. After success, a one-time fixed session intent returns the customer to `/public/customer/properties`.
3. Signed-in customer: directly to My Properties.
4. Select a current property and use the existing resale wizard → current-booking OTP → buyer-facing preview → final verification → direct publication.

Normal login still opens its existing dashboard. The post-entry intent is cleared by session login/logout. No second login, OTP or resale wizard was created.

## Public contacts

`PublicListingContact` is a read-only local database resolver used by the existing public detail page. It first applies the same source/project/mapping/Head/Subhead/status/deal public eligibility predicate as the catalog. Hidden/sold/pending listings cannot expose seller contact actions through this resolver, including when viewed in an admin preview.

| Selling method | Authoritative public contact |
| --- | --- |
| Self Sell | Only the customer-entered listing `payload.poster.phone`. No customer-profile, ERP registered mobile or OTP recipient fallback. The page explicitly states that this public number is **not OTP verified**. |
| Shalimar Agent | Selected portal agent, exact saved agent link/source, existing SGC internal-agency eligibility, portal-active status and `contact_approved=1`. Uses the currently approved central business mobile. A Zalmi property still uses its selected agent's SGC identity. |
| Authorized Agency | Exact saved central agency/link, the property's ERP source, existing mapped/present/authorized/unlocked/unrestricted/external eligibility, and central directory phone. The schema has no separate agency contact-approval flag: this is the existing admin-confirmed/mapped and editable central business contact, never raw ERP `contact_json`. |

Agent and agency eligibility SQL is shared with existing customer selection, rather than maintaining a second eligibility definition. Selling-method labels use the actual listing column rather than assuming an optional payload field is authoritative.

Mobile/business numbers are normalized before creating `tel:+…` and `https://wa.me/…` links. Invalid, ambiguous, blank, unapproved or ineligible contacts produce **Public contact details are currently unavailable.** Seller Call/WhatsApp buttons are omitted; no other number is substituted. A separately labeled **Shalimar Support** button retains the existing support line.

All public output is escaped. The resolver never reads customer identity/mobile, booking-mobile or OTP tables. It does not publish private ownership proof or directory contacts that lack the existing authorization. Listing status and historical assignments are not changed when a contact becomes unavailable. The existing page structure, classes, gallery and responsive layout remain in use.

## Exact application files

New:

- `app/Services/LegacyListingPolicy.php`
- `app/Services/PublicListingContact.php`
- `app/Libraries/PortalLinks.php`
- `html/includes/customer-entry.php`

Updated:

- `app/Config/Routes.php`
- `app/Controllers/Admin/Workspace.php`
- `app/Controllers/Customer/Auth.php`
- `app/Controllers/Customer/Resale.php`
- `app/Filters/CustomerAccess.php`
- `app/Services/CustomerSession.php`
- `app/Services/ShalimarAgents.php`
- `app/Services/AgencyDirectory.php`
- `app/Libraries/PortalCatalogStore.php`
- `html/admin-classic.php`
- `html/post-property.php`
- `html/listing.php`

Redirect-only replacements of existing sample handlers:

- `html/samples/v1/admin.php`
- `html/samples/v1/post-property.php`
- `html/samples/v2/admin.php`
- `html/samples/v2/post-property.php`
- `html/samples/shalimar-property1/admin.php`
- `html/samples/shalimar-property1/post-property.php`
- `html/samples/demos/design-1/admin.php`
- `html/samples/demos/design-1/post-property.php`
- `html/samples/demos/design-2/admin.php`
- `html/samples/demos/design-2/post-property.php`
- `html/samples/demos/design-3/admin.php`
- `html/samples/demos/design-3/post-property.php`
- `html/samples/demos/design-4/admin.php`
- `html/samples/demos/design-4/post-property.php`
- `html/samples/demos/design-5/admin.php`
- `html/samples/demos/design-5/post-property.php`

New test support: `.qa/phase1_fixture.php`, `.qa/phase1_test_router.php`, `.qa/verify_phase1_safety.py`. This report is `PHASE-1-WORKFLOW-SAFETY.md`.

## Tests and preservation

All test mutations use explicitly guarded disposable portal schemas and isolated sessions, with fake ERP/SMS dependencies. They do not repair/query ERP bookings or send real SMS.

| Executed check | Result |
| --- | --- |
| `.qa/verify_phase1_safety.py` | Passed: server-level Approve/Re-open blocks, RBAC/CSRF/audit, unchanged records, shared storage guard including collation variants, all direct legacy admin/posting aliases, discarded POSTs, guest login return and signed-in routing, both ERP identities, existing property endpoint, all three contact methods including cross-ERP agents, source-specific revocation, unapproved/disabled/mismatched contacts, no private-mobile/OTP output, invalid contact rejection, public visibility, real Unpublish, desktop/mobile and visible actionable contact card. |
| `.qa/admin_portal_data_checks.php` | Passed: real local read models, composite relationships, filters, audit rollback, idempotence and terminal-state protection. |
| `.qa/verify_admin_portal_http.py` | Passed: actual Admin Customers/Properties/Resale/Dashboard, filters/details/pagination, RBAC/CSRF, Unpublish, browser layouts/theme and absence of admin ERP/SMS calls. |
| `.qa/verify_shalimar_agents_http.py` | Passed: existing customer login/OTP, property-specific OTP, preview/final verification/direct live publishing, SGC and Zalmi properties assigned to one SGC agent, and real admin records/counts. |
| `.qa/verify_agency_http.py` | Passed: agency sync/mapping, source-specific assignment restrictions, bulk confirmation, RBAC/CSRF, alerts and preservation of existing records. |
| `.qa/resale_ownership_checks.php` | Passed: sealed authorization, expiry/attempts/resend, fresh proof/draft checks, SMS failure and no unauthorized publication. |
| `.qa/run_visibility_http.py catalog` | Passed: mapping gate, public URL hide/restore, sold remains hidden and mapping UI. |
| `.qa/run_visibility_http.py` | Passed: existing admin authentication/RBAC, classic/read modules, ERP Sources, audit, session expiry, logout and throttling. |
| Pure safety checks | Passed: 10 phone normalization/rejection cases; 20 inactive/closed/missing-to-active variants rejected; deactivation allowed. |
| PHP syntax | Passed for all 32 application files above and the two new PHP test helpers. |

The mobile browser test initially sampled the existing reveal animation before it settled. The test now waits for visible opacity before asserting and capturing the contact card; no page CSS/animation change was needed.

Eleven previously hashed customer/OTP/preview/publication files remain byte-identical. Only customer entry/return routing files were excluded from that old baseline because they intentionally changed here. `ResaleSubmission`, `ResaleOwnershipOtp`, the customer ERP/current-booking providers and live wizard/preview JavaScript were preserved.

Read-only check of the existing local portal still shows **1 customer, 2 ERP links, 2 saved properties, 39 total listings, 2 customer workflow listings and 1 active customer listing**. No existing seed/history/customer/property rows were deleted or updated by these tests.

Logs: `.qa/phase1-safety-checks.log`, `.qa/phase1-admin-service.log`, `.qa/phase1-admin-http-regression.log`, `.qa/phase1-publication-regression.log`, `.qa/phase1-agency-regression.log`, `.qa/phase1-otp-service-regression.log`, `.qa/phase1-visibility-regression.log`, `.qa/phase1-rbac-regression.log`.

Screenshots: `.qa/phase1-public-desktop.png`, `.qa/phase1-public-mobile.png`.

## Environment and remaining phases

- No live deployment or live API/SMS verification was performed. No ERP API upload is needed for these portal-only changes.
- The previously observed local SGC crashed ledger and missing local Head/Subhead `smp_active` columns were not repaired or modified. They do not establish live ERP state. Regression checks therefore use isolated valid schemas and fake API transport.
- Existing local unapproved/blank agent contacts remain unapproved/blank. Their listings now correctly display unavailable seller contact actions with separate Support; this change does not invent or approve a number.
- Safe customer republish/reactivation, customer edit/withdraw/Mark as Sold, Buyer Inquiries, business notifications and periodic ownership verification remain future work. Negotiable/photo-upload work is also outside these three fixes.
- No broad redesign, new approval requirement, property bulk import or new listing statuses were introduced.
