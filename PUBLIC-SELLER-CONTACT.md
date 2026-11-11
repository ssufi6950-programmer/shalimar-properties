# Public resale seller information and contact panel

> Latest policy (6 November 2026): at the user's explicit request, eligible central Agent and Agency business contacts now display automatically. Neither `contact_approved` nor `public_email_approved` gates phone/email display. Missing/invalid contacts stay hidden. Source-specific agency authorization, agent eligibility, listing visibility and the explicit Self Sell contact rules are unchanged. The earlier separate contact-approval workflow is superseded. No new database migration or ERP upload is needed for this follow-up.

Implemented locally on 6 November 2026. The existing `html/listing.php` page now presents the actual resale selling method through the existing `PublicListingContact` resolver. The rounded white panel, availability/asking price, publication verification, initials avatar, green WhatsApp action, outlined contact actions and responsive page layout are retained.

## Display and authoritative sources

| Method | Badge / main name | Secondary description | Phone / WhatsApp | Email |
| --- | --- | --- | --- | --- |
| Self Sell | DIRECT OWNER / Property Owner | Property Owner | Only the listing's explicit `payload.poster.phone`; never the account or ERP booking mobile | Explicit optional `public_email` saved to `payload.poster.email`, with `poster.email_public=true` |
| Shalimar Agent | OFFICIAL SHALIMAR AGENT / currently selected central agent name | Shalimar Properties · Official Resale Agent | Valid current central `shalimar_agents.mobile`, without separate contact approval | Valid current central `shalimar_agents.email`, without separate contact approval |
| Authorized Agency | AUTHORIZED AGENCY / currently selected central agency name | Shalimar Properties · Authorized Resale Agency | Admin-confirmed central `authorized_agencies.phone` under the existing contact policy | Valid central `authorized_agencies.email`, without separate contact approval |

Public listing details do not show the ownership-verification badge or date. The internal validated proof and verified-only catalog filtering remain intact. Public Self Sell phone numbers remain explicitly labeled **not OTP verified**.

## Public detail label follow-up — 7 November 2026

Removed the “Ownership verified at publication” badge and its date from the public listing detail sidebar. The owner/method identity and contact actions remain. This is presentation-only: publication proof, lifecycle state, verified-only filtering, and ownership checks are unchanged.

The selling-method pill now sits at the top-right beside availability and reads **Owner**, **Agent** or **Agency**. It no longer uses “Authorized”; the representative name/contact routing remain in the seller panel.

Existing agent/agency schema has no approved public photo/logo field, so the panel uses escaped initials. It never invents an avatar URL. Unresolvable/ineligible representatives show **Seller unavailable**, without falling back to a snapshot, customer name or developer identity.

The selected agent must match the saved central agent, exact agent link and SGC source, plus the existing verified internal-agency configuration and eligibility checks. The configured SGC agency identifies its dealers; no individual ledger ID is used to identify the entire agency. The same SGC agent can represent SGC and Zalmi properties. No SGC ledger IDs are sent to Zalmi transaction endpoints.

Agencies retain their exact saved central agency/link and property's ERP source. Mapped/present/authorized/unlocked/unrestricted/external and portal-active checks are reused from `AgencyDirectory::eligibleSql()`. A name match does not authorize an agency. Revoking SGC authorization does not silently revoke a separate valid Zalmi link.

## Independent contact actions

The resolver returns phone and email independently. Missing/invalid phone suppresses Call/WhatsApp while retaining a valid saved business email. Missing/invalid email suppresses Email while retaining eligible phone actions. With neither available, the page displays **Public contact details are currently unavailable.**

- WhatsApp Owner / Agent / Agency uses the normalized public phone at `https://wa.me/…` and an encoded message containing the public title/reference.
- Call Owner / Agent / Agency uses a normalized `tel:+…` URI.
- Email Owner / Agent / Agency uses a safely encoded `mailto:` address and the encoded subject **Resale Property Enquiry — SP-{id}**. There is no SMTP sender or email submission service.
- Names, subtitles, badges and link attributes are escaped. Email validation rejects arrays, invalid addresses, control characters and CR/LF header injection. Whitespace is trimmed and the domain is lowercased; the local part is preserved.

The separate **Need Help? Contact Shalimar Support** section retains `SITE_TEL`. The Shalimar UAN/support email is never used as an Owner, Agent or Agency fallback. Public contact resolution reads only local listing/catalog/directory tables, without customer identity, OTP-table, API or ERP database reads.

## Optional public Self Sell email and authorization

The existing Self Sell panel has an optional **Public Contact Email** input explaining that buyers can see the address. The same field participates in browser draft restoration, the wizard review, server draft validation, immutable OTP-authorized preview, edit/republish restoration and final draft comparison. It is disabled in the browser for other selling methods and ignored for those methods server-side.

Email edits require the same customer, existing listing version/fingerprint, current-booking OTP and fresh final ERP verification as other material edits. Additional email data at final confirmation invalidates the old authorization. Stored `listing_events.previous_record` / `next_record` already preserve the complete listing payload, including public email, so no second revision mechanism is needed.

New Self Sell payloads use **Property Owner** as the public poster name. Existing personal names are not substituted into this public panel. Older payload email values without the explicit `email_public=true` marker are not treated as authorized public email; older listings without email continue working. No private profile/ERP email is imported into this field.

An OTP challenge created before this deployment may need a fresh code because its sealed draft predates the new optional field. Existing saved listings are preserved.

## Automatic central business contacts

Agent and agency directory phone/email values now display automatically for an eligible saved representative. Their source/assignment/authorization checks still apply. Shared customer selection, buyer preview and the public resolver use the same central contact policy, so OTP-authorized previews remain consistent with public contact resolution. Syncing a changed agent phone/email does not require reapproval.

The Admin agent approval button and agency email approval checkbox were removed. Agency contact edits retain existing RBAC, CSRF, validation and audit logging. Existing legacy approval columns, migration files and audit history are retained for compatibility; contact resolution no longer depends on those columns. The legacy agent contact-flag endpoint remains protected and records historical metadata only; its response explicitly explains that contact display is automatic.

No new migration, token change, ERP API upload, database contact rewrite or invented contact was needed for this follow-up. Upload the changed portal services and Admin view/controller files when deploying it. Self Sell continues to use only its explicitly entered public contact; private account/booking contacts are never substituted.

The automatic-contact follow-up changes six application files: `app/Services/PublicListingContact.php`, `app/Services/ShalimarAgents.php`, `app/Services/AgencyDirectory.php`, `app/Controllers/Admin/Agents.php`, `app/Views/admin/agents/index.php` and `app/Views/admin/agencies/show.php`.

## Availability and Sold records

Public contacts require the same local source/project/exact mapping/Head/Subhead/status/deal predicate as the public catalog. Hidden, pending, withdrawn, admin-unpublished and Sold records cannot expose seller contact actions. Representative revocation removes the affected identity/contact actions while preserving saved assignments and history under the existing policy.

There is no public Sold archive in the current application, so Sold URLs continue to return 404. No visibility bypass or automatic Sold detector was added. The existing detail view handles Sold status without labeling it AVAILABLE and omits seller actions in an admin preview. A future Phase 3 archive must retain these contact restrictions; this task does not claim to implement that archive.

## Exact files

New application files:

- `app/Services/PublicContactEmail.php`
- `app/Database/Migrations/2026-11-06-000002_AddAgencyPublicEmailApproval.php`
- `database/sql/portal-agency-public-email-approval.sql` (equivalent file-only deployment option)

Updated application files:

- `app/Services/PublicListingContact.php`
- `app/Services/AgencyDirectory.php`
- `app/Services/ResaleSubmission.php`
- `app/Controllers/Customer/Resale.php`
- `app/Services/ShalimarAgents.php` (automatic central business contacts)
- `app/Controllers/Admin/Agents.php` (legacy flag response wording)
- `app/Views/customer/resale/live_create.php`
- `public/assets/customer/js/resale-live.js`
- `app/Views/admin/agencies/show.php`
- `app/Views/admin/agents/index.php` (approval wording only)
- `html/listing.php`

No Customer Portal dashboard/navigation/table CSS, public stylesheets, authentication, current-booking API, OTP destination/service, lifecycle service, photo service, source CRUD, project mapping or ERP files were changed in this task.

The read-only diagnostic `.qa/diagnose_public_seller.php` was also added. For the user's listing #40, the selected central agent Malik Jahanzaib Awan (agent #3) has a valid saved phone and a blank email. The previously blocking legacy contact flag is now ignored. A read-only actual localhost HTTP check of listing #40 confirmed Call and WhatsApp present, Email absent, and HTTP 200. No saved contact, listing or ERP data was changed.

New QA support: `.qa/public_email_checks.php`, `.qa/public_email_migration_checks.php`, `.qa/public_seller_fixture.php`, `.qa/verify_public_seller.py`. Existing QA updates: `.qa/verify_phase1_safety.py` recognizes Email links; `.qa/verify_phase2_lifecycle.py` includes secure email edit cases; `.qa/resale_ownership_checks.php` checks explicit email persistence in lifecycle history. Documentation: this report and the follow-up note in `CUSTOMER-APP-UI-REDESIGN.md`.

## Verification and screenshots

The historical table below records the original panel implementation. The latest policy checks are recorded in `.qa/automatic-contacts-public.log`, `.qa/automatic-contacts-agents.log`, `.qa/automatic-contacts-phase1.log` and `.qa/automatic-contacts-publication.log`. They enforce automatic contacts even when legacy approval flags are zero, while retaining invalid/missing-contact rejection, source restrictions, protected edits and private OTP data exclusion.

Automatic-contact verification: **241 checks passed** (74 public seller HTTP/browser checks, 32 agent service/synchronization checks, 65 Phase 1 safety checks and 70 full cross-ERP publication checks), plus syntax checks for all six changed PHP application files. The actual local listing #40 returned HTTP 200 with Call/WhatsApp buttons; its blank email correctly has no Email button. No real SMS was sent and no existing customer/listing/business contact data was rewritten.

The original panel implementation passed **654 automated checks** before the automatic-contact policy follow-up, plus syntax checks for all ten changed application PHP files and the affected JavaScript file.

| Executed suite | Passed | Log |
| --- | ---: | --- |
| Public seller HTTP/approval/browser | 74 | `.qa/public-seller-http.log` |
| Public email normalization/injection | 18 | `.qa/seller-email-validation.log` |
| SQL/CI4 migration and rerun safety | 5 | `.qa/seller-email-migration.log` |
| Phase 1 safety/legacy/public contact | 65 | `.qa/seller-phase1-regression.log` |
| Customer authentication/ownership | 44 | `.qa/seller-auth-regression.log` |
| Resale OTP service and email history | 47 | `.qa/seller-otp-regression.log` |
| Phase 2 lifecycle/photos/secure email edit | 97 | `.qa/seller-phase2-regression.log` |
| Agency mappings/RBAC/CSRF | 55 | `.qa/seller-agency-regression.log` |
| Admin data services/Unpublish | 32 | `.qa/seller-admin-regression.log` |
| Complete SGC/Zalmi in-process publication | 70 | `.qa/seller-publication-regression.log` |
| Customer app UI at five widths | 147 | `.qa/seller-customer-ui-regression.log` |

The existing local Apache page `/shalimar-properties/html/listing.php?id=39` also returned HTTP 200 with the dynamic seller panel and separate Support section. That read-only page check did not send a message or alter listing/ERP data.

Focused HTTP/browser checks cover all three methods, SGC and Zalmi assignment rules, matching name/badge/subtitle, public phone/mailto/WhatsApp links, independent missing/invalid/unapproved phone/email, explicit admin approval with RBAC/CSRF, source-specific revocation, hidden/Sold/pending restrictions, no private name/mobile/proof exposure and read-only public resolution. The real browser captured all three panels at 1440px and 390px:

| Method | Desktop | Mobile |
| --- | --- | --- |
| Owner | [Screenshot](.qa/public-seller-owner-desktop.png) | [Screenshot](.qa/public-seller-owner-mobile.png) |
| Agent | [Screenshot](.qa/public-seller-agent-desktop.png) | [Screenshot](.qa/public-seller-agent-mobile.png) |
| Agency | [Screenshot](.qa/public-seller-agency-desktop.png) | [Screenshot](.qa/public-seller-agency-mobile.png) |

Screenshot names and contact details are disposable fixtures, not fabricated live directory entries. Tests use guarded disposable databases and fake ERP/SMS providers. No live ERP data was changed, SMS sent or live deployment performed.
