> 11 November 2026 follow-up: the user explicitly requested property OTP display when resaleOtpPreviewOnly=true. Temporary preview-only testing is restored by [the current repair package](deploy/live-resale-otp-preview-fix/README.md), requiring resaleOtpPreviewOnly, showOtpPreview and sms.testMode together. This supersedes the historical preview-retirement paragraph below for this explicit testing mode. Normal SMS mode, fresh ERP ownership checks, sealed drafts and pending admin review are preserved. The package has not been deployed by the agent.

# Phase 2 of 6 — customer Sale and Rent submissions

Implemented locally on 11 November 2026. Phase 3 has not been started. No database migration, live database change, ERP API change, credential change or production deployment was performed in this phase.

## Phase 1 preflight and preservation

The actual local `portal_listings` table has the Phase 1 `listing_purpose` Sale/Rent enum, separate four-value `review_state` enum, stored generated `active_erp_property`, and unique `unique_active_erp_property` index. The existing key includes ERP source, authoritative ERP property ID and purpose. Existing migrations and both ERP API repositories remain byte-identical to the start of this task.

Read-only before/after hashes match for the actual local listings, owned-property snapshots, customers, ERP identity links, photo associations and lifecycle events. The existing **43 listings, 9 photos and 9 events** are unchanged. Existing Sale/Rent, live, Sold and cancelled records retain their data and states. No new sample/customer/listing records were inserted into the real portal database. All test mutations use guarded disposable schemas on the separate loopback QA database.

Live schema has not been inspected or changed. If Phase 1 was installed manually through phpMyAdmin, validate the actual columns and generated index with the package's read-only `CHECK-PHASE1-SCHEMA.sql`. Phase 2 needs no additional columns/indexes and does not require rerunning migrations. A different or incomplete live Phase 1 schema must be corrected deliberately before uploading this application update.

## Property actions and routes

My Properties remains a real HTML table at **360, 390, 768, 1024 and 1440px**. Its mobile card/list conversion was removed only from this screen. Horizontal scrolling stays inside a focusable, labeled table wrapper. The Actions column has distinct **List for Resale** and **List for Rent** controls with at least 44px touch height. Property details expose the same two actions.

Both controls call existing `POST /customer/resale/prepare` with an opaque session property reference and validated `listing_purpose`:

- Sale opens existing `GET /customer/resale/create?property=<opaque-reference>&purpose=sale`.
- Rent opens the same protected wizard route with `purpose=rent`, displaying the new rental fields.

No dead or duplicate route was introduced. Old Sale links without purpose still default to Sale. Server-side `currentProperty()` resolves the opaque reference to the authenticated customer's separately verified ERP identity and re-fetches current ERP ownership. Client source/customer/project/property IDs do not establish identity. The exact ERP, project, property number, purpose and normalized type appear in the form.

`CustomerPortfolio` now provides separate Sale/Rent action metadata. A current Sale blocks only another Sale; a current Rent blocks only another Rent. Own current records produce **View Sale Status / View Rent Status** links. Another customer's historical/current listing reference is not disclosed as a management link. Mapping/visibility and admin-unpublished restrictions remain enforced. Listing eligibility and ownership remain separate; a hidden/ineligible property remains in its owner's portfolio.

The prepared server review binds purpose as well as customer, reference, source/property/mapping, submission key, expiry and any lifecycle context. Switching purpose in a prepared POST is rejected. A different purpose receives a separate key/draft; the shared underlying ERP ownership reference cannot authorize a forged identity.

## Sale and Rent forms/storage

Sale retains the existing wizard, asking sale price, Negotiable, description, private photos, selected representative, OTP and sealed preview. Rent reuses that infrastructure with an explicit **Monthly Rent (PKR)** input and `/ month` formatting. It does not send the Sale price input as the rental form amount.

Rental terms use existing `portal_listings.payload.rental`:

| Field | Validation/storage |
| --- | --- |
| `monthly_rent` | Required positive whole PKR amount, 1–999,999,999,999. Canonical decimal string. |
| `security_deposit` | Optional whole PKR amount; explicit zero is valid, blank becomes null. |
| `advance_rent` | Optional whole PKR amount; explicit zero is valid, blank becomes null. This is an amount, not a number of months. |
| `furnishing` | Optional `furnished`, `unfurnished`, `partially_furnished`, or null. |
| `lease_duration_months` | Optional integer 1–120, or null. |

Existing `asking_price DECIMAL(14,0)` and `payload.price` hold the canonical monetary amount for either purpose; `listing_purpose` defines whether it is the sale price or monthly rent. Rent-specific terms remain in the JSON payload. Conflicting rental/Sale amounts, malformed numeric types, negative values, invalid furnishing/duration, missing rental description and descriptions over 500 characters are rejected server-side. Missing optional terms are not fabricated. Self listings now require an explicitly entered valid public mobile; public email remains optional and validated.

The original secure photo infrastructure still validates/re-encodes JPEG/PNG/WebP, count/size, customer/session/submission binding, expiry and photo order. New submission photo metadata is pending; listing and media eligibility remain private until review. No new public upload directory was added.

Both purposes use real ERP types. The Phase 1 API rule `m_property_maintain.status === 'constructed'` continues to normalize House. Apartment/Shop/Plot/other valid types remain intact. Rent is not limited to House. Tests include a Shop rental and both ERP constructed-to-House cases.

## Representatives and public contacts

The existing directory and assignment services are reused for both purposes:

- **Direct Owner / Self Sell:** the entered public mobile and optional public email are canonical/OTP-bound. Private ERP mobile is never copied into public contact fields. The owner identity belongs to the authenticated customer.
- **Shalimar Agent:** the existing customer generic-agent rule is retained: SGC agency **193**, generic ERP dealer ledger **11770**, active/current/eligible links and central portal status. Agency 193 is not a ledger ID and the local portal agent ID is not hardcoded to 5. New selection also requires existing `contact_approved=1` and a valid central public business mobile/email. Both SGC and Zalmi properties retain the selected agent's SGC provenance. No SGC dealer identifier is sent into a Zalmi transaction/write API.
- **Authorized Agency:** existing source-specific mapped, present, active, external and authorized agency links are used. As previously requested, `restricted` alone is not a customer agency exclusion. Unmapped/revoked/locked/portal-disabled agencies are not selectable. A required missing/invalid central business mobile disables the option or rejects submission with a correction message. An optional nonempty invalid email is rejected. The central admin-managed contact is used, never raw ERP `contact_json` or support contact as a fallback.

There is no second agency directory and no automatic mapping, approval, contact-number invention or source substitution. Representative eligibility and contact changes between OTP and final submission invalidate the reviewed draft. Existing public seller-contact resolution is unchanged.

Read-only **local** readiness at completion: SGC has 51 eligible external agency links, of which 41 pass the new business-contact validation; Zalmi currently has zero eligible external agency links. The generic agent currently has zero new selectable entries after the contact-approval gate. These are directory/configuration dependencies, not fabricated empty results. Correct/confirm the actual business contact and existing approval flag, and sync/confirm actual source-specific Zalmi mappings before testing those choices with real data. No real directory record was changed during this task. Live directory state may differ.

## Ownership, OTP and Under Review

Existing authentication, session validation, CSRF, identity linking and rate-limiting services remain in use. Login identity-link metadata now carries its server-derived local customer ID solely to identify that customer's existing listing actions; login authorization/linking rules did not change.

At request time, the portal re-resolves the originating ERP identity/property, validates current booking and Head/Subhead/mapping eligibility, validates the selected representative and private photo selection, then sends OTP using the **current booking owner's registered ERP mobile** returned by the originating API. Public contact and login mobile are not substituted as that destination.

The sealed draft includes source/property, purpose, monetary/rental terms, method/contact, photos and lifecycle context. OTP expiry, attempts, resend cooldown, session/customer binding, one-use consumption and final fresh booking/ownership verification remain authoritative. A Sale OTP cannot authorize Rent or another property. Final material-field injection invalidates the challenge. Source outages, transferred ownership and changed booking/mobile/eligibility fail closed without creating a listing.

**Sale/Rent OTP preview is retired.** Property request/resend responses never include the code, even if old `showOtpPreview` or `resaleOtpPreviewOnly` environment flags remain enabled. Preview-delivery challenges from the old workflow cannot authorize submission. SMS failure has no preview fallback. Existing customer login testing controls are otherwise unchanged. Existing SMS test-recipient routing also remains configured as before; production owner delivery requires the appropriate production SMS configuration. No real SMS was sent by tests and real gateway delivery was not verified.

Every successful customer final submission now saves `status='pending'`, `review_state='under_review'`, with no approval event or new publication timestamp. It returns and displays this exact message:

> Your property listing has been submitted successfully and is under review. It will appear on Shalimar Properties after admin verification and approval.

The existing `/customer/resale/publish` route is retained as a compatibility endpoint; it no longer publishes a customer submission. Repeated confirmation returns the same customer-owned listing without duplication. A new record receives a `customer_submitted` event and audited submission action. Material edits and withdrawn-listing resubmissions retain the listing ID/history but return to review with `customer_edit_submitted` / `customer_resubmitted` events. Unlike Phase 1's compatibility behavior, they cannot remain live without review. Existing original publication metadata is preserved as historical data; it does not confer current visibility on the pending revision.

Pending listings have View Status/Details, with no customer Approve/Publish shortcut. The existing protected Withdraw action remains for approved active listings; resubmission requires fresh OTP and review. Sold/rejected/admin-unpublished restrictions and the disabled manual customer Mark Sold route remain. No admin approval/rejection/photo-review interface or endpoint was introduced.

## My Listings and privacy

Active customer navigation now says **My Listings**, including the mobile Listings tab. Each owned listing shows For Sale/For Rent, correctly labeled price/monthly rent, ERP/project, method, submitted timestamp, status and authorized actions. Rental details/preview show optional terms, Negotiable and actual saved photos. Queries remain scoped to the authenticated local customer. Historical Sold/Withdrawn records remain stored and read-only as appropriate.

Phase 1's shared approval predicate still gates public catalog/detail/search, public contact, media and Agency Portal Active Listings. A pending guessed public detail or media URL returns 404; another customer's detail/photo request also returns 404. Approved legacy listings remain public under their existing source/project/mapping rules. No existing standalone public listing API or sitemap bypass was found; public pages use the gated local catalog adapter. Customer HTML/JSON/photo responses retain `Cache-Control: no-store, private`. Market cache/reporting remains ERP-only and does not use portal submissions or Rent listings.

## Files created and modified

New application files:

- `app/Services/ListingRentalTerms.php`
- `app/Services/ListingSellerContact.php`
- `app/Views/customer/components/property_listing_actions.php`
- `app/Views/customer/resale/rental_fields.php`
- `app/Views/customer/resale/rental_terms.php`

Modified application files:

- `app/Controllers/Customer/PortalController.php`
- `app/Controllers/Customer/Resale.php`
- `app/Helpers/customer_helper.php`
- `app/Services/CustomerOtpService.php` (server-derived identity-link metadata only)
- `app/Services/CustomerPortfolio.php`
- `app/Services/ResaleOwnershipOtp.php`
- `app/Services/ResaleSubmission.php`
- `app/Services/ShalimarAgents.php` (new customer selection/contact gate only; historical/public eligibility unchanged)
- `app/Views/customer/components/listing_actions.php`
- `app/Views/customer/dashboard/index.php`
- `app/Views/customer/layouts/header.php`
- `app/Views/customer/layouts/footer.php`
- `app/Views/customer/properties/index.php`
- `app/Views/customer/properties/details.php`
- `app/Views/customer/resale/live_create.php`
- `app/Views/customer/resale/live_index.php`
- `app/Views/customer/resale/live_details.php`
- `app/Views/customer/resale/public_preview.php`
- `app/Views/customer/resale/submitted.php`
- `public/assets/customer/css/customer-app.css`
- `public/assets/customer/js/customer-live.js`
- `public/assets/customer/js/resale-live.js`

Services/controllers reused: current customer Auth/Session/OTP/identity linking, Properties and Resale controllers, CustomerPortfolio, CurrentBookingApi/provider, ResaleOwnershipOtp, ResaleSubmission, ResalePhotos, ListingLifecycle, ListingPolicy, ErpVisibility, ShalimarAgents, AgencyDirectory, AdminAuditService and customer read model. Routes, database migrations, public/Admin design, public contact service, source CRUD/RBAC and Market Intelligence implementation were not changed.

**375 of 397 baseline PHP/JavaScript/CSS files remain byte-identical.** New QA files: `.qa/phase2_schema_check.php`, `.qa/phase2_directory_readiness.php`, `.qa/sale_rent_fixture.php`, `.qa/sale_rent_validation_checks.php`, `.qa/verify_sale_rent.py`, `.qa/verify_sale_rent_browser.py`, `.qa/run_phase2_regressions.py`. Existing test helpers/suites were updated for opt-in extra fixture properties, required contacts, retired property previews and reviewed lifecycle revisions. The old lifecycle regression simulates already-approved states only inside a guarded disposable fixture to test successive approved revisions; it does not add a production approval action.

## Executed verification

| Suite | Passed checks |
| --- | ---: |
| New Sale/Rent HTTP + browser workflow | 388 |
| Rental/contact validation | 40 |
| Existing property ownership OTP service | 76 |
| Customer authentication/identity/ownership service | 71 |
| Both real ERP API copies against isolated SQL fixtures | 221 |
| Phase 1 schema/backfill/concurrency/classification foundation | 54 |
| Admin real-data/Unpublish/audit rollback service | 32 |
| Existing lifecycle/photo/OTP/concurrency HTTP + browser | 113 |
| Request-local configuration | 8 |
| Legacy/public safety HTTP + browser | 65 |
| Agency Portal HTTP + browser | 153 |
| Existing login/live-properties HTTP + browser | 56 |
| ERP Market service/API/HTTP/browser | 106 |
| **Total** | **1,383** |

The new suite submits both purposes through all three methods from both ERP sources; validates optional terms, normalized House/Shop types, public/OTP contact separation, foreign references/IDs, purpose changes, wrong/expired/foreign/reused OTP, idempotence, same-purpose duplicates, public/foreign/media/agency privacy, final ownership changes, source-ineligible/disabled/unapproved representatives, material edit review and preservation of existing records. The foundation suite runs actual simultaneous same-purpose database insert races and confirms Sale/Rent coexistence under the unique constraint.

Browser coverage is **360, 390, 768, 1024, 1440px**: computed table/table-row/table-cell display, container scrolling, touch-sized Sale/Rent controls, actual button navigation for SGC/Zalmi, form overflow and the complete native JavaScript rental OTP/preview/confirmation journey. An early browser test interacted before the page scripts finished loading; the harness now waits for document completion before clicking. No production layout workaround was needed.

PHP syntax passed for all 24 changed/new application PHP files; both changed JavaScript files passed `node --check`. Logs: `.qa/phase2-sale-rent-{final,validation,otp,auth,api,foundation,admin,lifecycle,config,safety,agency,live,market}.log`. The additional browser-only development run is not double-counted. Screenshots: `.qa/phase2-sale-rent-properties-390.png`, `.qa/phase2-sale-rent-properties-1440.png`, `.qa/phase2-sale-rent-rental-390.png`. Before/after real-data proof: `.qa/phase2-sale-rent-local-before.json`, `.qa/phase2-sale-rent-local-after.json`.

## Deployment

Package: `deploy/phase2-customer-sale-rent/`. Its README maps every file to the exact live FTP path under `/home/propertys_user11/www/`; customer CSS/JavaScript are supplied in both `public/assets/...` and `html/public/assets/...` for the existing live document-root bridge. The checksummed package contains no credentials, `.env`, database export, vendor, private photos or QA runtime data.

1. Verify actual live Phase 1 schema with the supplied read-only SQL; do not rerun migrations merely because migration bookkeeping differs after a manual phpMyAdmin install.
2. Retain a matching file/database backup and merge this package during a maintenance/upload window. Upload matching templates/services/assets together. Preserve existing `.env`, private uploads/photos, sessions and unrelated files.
3. Phase 2 has **no new SQL/migration to apply**. Do not replace live customer data with the local database. If Phase 1 schema is missing/different, stop deployment and resolve that separately.
4. Phase 2 needs **no new ERP API upload**. If Phase 1's constructed-classification extension was never deployed, deploy/verify its existing four-file API update on each ERP separately from the Phase 1 package. Live API state has not been verified here.
5. Refresh the browser, reopen My Properties and request fresh property verification codes. Pre-upgrade sealed drafts/testing-preview challenges are not reusable with the new material data/delivery rules.
6. Confirm real SMS routing/gateway delivery and actual directory contacts/mappings before customer UAT. No real OTP delivery or live property submission was attempted in this task.

## Exact manual UAT — run for SGC and then Zalmi

1. Sign in with a genuine customer whose current ownership is independently confirmed in that ERP. My Properties should show the correct ERP, plot/unit and type; a constructed property should say House when Phase 1's API extension is available.
2. On a property with no current Sale/Rent offers, click **List for Resale**. Confirm exact ERP/property/type and the Sale price form. Enter a positive sale price, Negotiable/description, approved public contact and optional photos.
3. Complete the listing-method step. Test Direct Owner, the eligible generic SGC agent and a mapped authorized agency for the property's originating source on separate eligible properties. If directory contacts/mappings are missing, resolve them through authorized directory management; do not select a different ERP's agency as a substitute.
4. Request the property OTP. For real owner-delivery testing, use production SMS routing only when authorized; otherwise the existing explicit test-recipient override remains a test configuration. Confirm the entered public contact is not used as the OTP destination.
5. Verify the received code, review the sealed preview and Confirm & Submit. Confirm the exact required success message, For Sale, correct price and Under Review in My Listings. Repeat final confirmation to verify a single listing ID.
6. As a guest, open its guessed `/listing.php?id=<id>` public URL (under the live HTML document root) and media URL: they must return 404. Another customer must not see its details/photos. Agency Active Listings must exclude it.
7. Return to the same property. Sale should offer its existing status, while **List for Rent** remains available. Open Rent; confirm Monthly Rent, deposit/advance amounts, furnishing and lease months. Use a Plot/Shop/other non-House property as well to verify there is no House-only restriction.
8. Submit Rent through fresh current-owner OTP. Confirm For Rent, `PKR … / month`, saved optional terms and Under Review. Both purpose records must coexist; each corresponding create action should now show its own status link.
9. Try a second same-purpose submission, changed property/source/owner parameters, another customer's reference, wrong/expired/replayed OTP and a changed final amount: all must fail without an extra listing or unauthorized public record.
10. Test an ERP outage/transfer between OTP and final confirmation; submission must fail closed. Check an unrelated approved legacy public listing and existing Sold/Withdrawn history remain intact.
11. Check 360/390px phones plus 768/1024/1440px widths. Scroll only the property table sideways to reach Actions; both controls should remain readable. Check rental fields, photo selection, OTP, preview and status navigation fit the screen.

New customer submissions intentionally cannot become public until Phase 3's authorized admin approval workflow exists and approves them. Full public Sale/Rent redesign and Agency/Admin terminology overhaul remain later phases.
