# Shalimar Properties — Agents and Agencies: Current Implementation Report

Prepared: **14 November 2026**. Live read-only snapshot: **13:25 PKT**.

Purpose: give ChatGPT/Codex sufficient current context to write the next implementation command. This is a description of the existing application, not authorization for additional features or database changes.

## 1. Short context to copy into the next task

The existing Shalimar Properties portal uses CodeIgniter 4, PHP, MariaDB/MySQL and Bootstrap. The public website is in `html`; CI4 routes are served through `/public/`, including the existing `html/public` bridge on live hosting.

There are two different central directories:

- **Authorized agencies:** one `authorized_agencies` record with multiple `agency_erp_links`.
- **Shalimar staff agents:** one `shalimar_agents` record with multiple `agent_erp_links`.

ERP identity keys are source-specific. Never treat an SGC ID as a Zalmi ID. Confirmed cross-source agent mappings produce one approval-dropdown choice, not two people. Names alone do not automatically merge records.

Customers choosing Shalimar Agent do not choose the individual agent. Admin selects the central agent when approving the listing. Publication saves an exact ERP agent link/source, queues one assignment SMS and attempts delivery after the approval transaction commits.

Agencies are selected by the customer from the property's own source. External-agency authorization is source-specific. Shalimar agents can represent properties from either source through an eligible linked identity.

Preserve the existing directories, exact saved assignments, listing history, customer privacy, RBAC, CSRF and confirmed ERP mappings. Inspect the current files/database before implementing the next task. Do not recreate these modules.

## 2. Application and live locations

| Component | Location |
| --- | --- |
| Local portal | `D:/xampp/htdocs/shalimar-properties/` |
| Local SGC API source | `D:/xampp/htdocs/sgcacc/smp-api/` |
| Local Zalmi API source | `D:/xampp/htdocs/sgcacc/zalmi-smp-api/` |
| Live portal root | `/home/propertys_user11/www/` |
| Live public website document root | `/home/propertys_user11/www/html/` |
| Live SGC API | `/home/sgc/www/html/smp-api/` |
| Live Zalmi API | `/home/zalmipk_user/www/html/smp-api/` |
| Live Zalmi Core PHP ERP | `/home/zalmipk_user/www/html/ERP786-ZALMIDEV/` |

Do not move `app`, `vendor` or `.env` into the public website folder. Preserve the current bridge and deployment layout.

## 3. Current live identities — verify again before changing data

These IDs are observations, not application constants:

| Item | Current live value |
| --- | --- |
| SGC portal source ID | `1` |
| Zalmi portal source ID | `2` |
| Central Shalimar Properties agency | `authorized_agencies.id = 86` |
| SGC Shalimar business agency | ERP agency `193` |
| Zalmi Shalimar business agency | ERP agency `2` |

| Local agent | Local ID | SGC ledger | Zalmi ledger | Current profile source |
| --- | --- | --- | --- | --- |
| Hassan Nawaz | `18` | `944` | `94` | SGC |
| Ms. Bisma | `19` | `6709` | `95` | SGC |

Earlier deployment notes mention local agent IDs `8/9` and `10/11`. Those are historical. A later live inspection confirmed `18/19`; **do not execute old ID-based consolidation instructions again**.

Both current staff identities report actual ledger agency `90`. This is distinct from the Shalimar business-agency IDs `193/2`. Do not replace the actual ledger membership with a business-agency ID.

Live source settings were enabled and verified. Their last successful sync timestamps were SGC **13:01:22 PKT** and Zalmi **13:01:23 PKT**, 14 November 2026.

### Agency snapshot counts

| Source | Stored ERP agency links | Present + authorize=yes | Of those, mapped | Of those, unmapped |
| --- | ---: | ---: | ---: | ---: |
| SGC | 184 | 53 | 53 | 0 |
| Zalmi | 2 | 1 | 1 | 0 |

These are source-link counts, include internal-agency links and are not the count of unique public external agencies. Public directory counts must use `PublicAgencyDirectory`, which excludes Shalimar/internal agencies and groups confirmed central records.

## 4. Authorized agency data model

### `authorized_agencies`

One local business record. Important fields:

- `id`, `agency_name`, `normalized_name`.
- `portal_active`, `agency_type` (`external`/`internal`), `is_shalimar_agency`.
- Central business `phone`, `email`, `city`, `address`.
- Timestamps. The inspected schema also contains `public_email_approved`; do not assume that flag is enforced by every consumer without checking its actual caller.

### `agency_erp_links`

One source identity per unique **`(erp_source_id, erp_agency_id)`**:

- `portal_agency_id`: nullable before mapping.
- Original `erp_agency_name`, `normalized_name`.
- Original `erp_authorize`, `erp_locked`, `erp_restricted` and legacy aggregate `erp_authorization_status`.
- `mapping_status`, `present`.
- `contact_json`: fetched ERP contact/branding metadata, including the agency logo filename.
- Discovery, last-seen and mapping timestamps/actor.

The original string flags matter. Do not replace string `yes/no` checks with boolean assumptions.

### Mapping and matching

1. Normalize names by trimming, lowercasing and collapsing repeated whitespace.
2. A matching name suggests an existing local agency; it does not automatically confirm mapping.
3. Admin can map to an existing agency or create a local agency and map.
4. Multiple ERP sources can point to the same central agency.
5. Existing confirmed mappings survive ERP renames and later syncs.
6. The existing bulk review/confirmation workflow can handle multiple agency mappings.
7. Authorized unmapped links appear in the existing pending count/alerts until mapped.

## 5. Agency synchronization and eligibility

The authenticated agencies API exports **all authorization states**, not just authorized agencies. This allows complete local syncs to detect revocation and disappearance. The public/Admin authorized views apply local filters afterwards.

Sync uses bounded, ID-based pagination, validates the complete response and performs source-local upserts. Only after a complete successful snapshot does it reconcile `present`. A failed/incomplete source retains its previous data and mappings; it does not cause historical deletion.

**Important: current eligibility rules differ by purpose.**

| Consumer | Relevant checks |
| --- | --- |
| Admin authorized agency list / pending mapping count | Enabled source, present link, raw `erp_authorize === 'yes'` |
| Public agencies page / homepage agency count | Enabled source, present link, raw authorize=yes; excludes internal/Shalimar agencies; groups mapped central agencies |
| Customer external-agency selection / public listing agency contact | Exact property source, mapped + present link, central portal-active external agency, authorize=yes and locked=no |
| Internal Shalimar business agency eligibility | Configured source mapping, portal-active internal central agency, verified enabled source settings, authorize=yes, locked=no **and restricted=no** |

The user explicitly allowed **restricted external agencies**. Therefore external selection uses raw authorize/locked flags and does not reject an agency merely because `erp_restricted='yes'` or the legacy aggregate status says unauthorized.

An agency appearing in the broad authorized directory does not guarantee it is selectable for every property. Source, confirmed mapping, central enablement and lock status still matter. Public directory branding/contacts must not expose private ERP contact fields simply because a raw agency exists.

## 6. Shalimar agent data model

### `shalimar_agents`

One local person:

- `id`, `name`, central business `mobile`, `email`, `image_filename`.
- `portal_active`.
- Legacy `contact_approved`.
- `created_at`, `updated_at`.

`contact_approved` still exists and sync may reset it when contacts change, but it is **not an eligibility/public-contact approval requirement** in the current agent flow. Do not accidentally restore the old approval gate.

### `agent_erp_links`

One ERP staff identity per unique **`(erp_source_id, erp_ledger_id)`**:

- `portal_agent_id` points to the central person.
- `erp_source_id`, `erp_ledger_id`, optional `erp_dealer_id`.
- Actual `erp_agency_id`, original dealer/locked flags.
- `erp_active`, `erp_eligible`, `present`.
- Discovery and last-sync timestamps.

The ledger is the authoritative staff identity. The current API returns `identity_type=ledger` and `dealer_id=null`.

### Settings and logs

| Table | Purpose |
| --- | --- |
| `shalimar_agent_settings` | Shared central business-agency reference and central configuration revision |
| `shalimar_agent_source_settings` | Separate source business-agency ID, enablement, version, verified version and last successful sync |
| `agent_sync_runs` | Per-source success/failure history |
| `agency_sync_runs` | Agency synchronization history |
| `admin_audit_logs` | Admin mapping/status/configuration actions and sync audit |

The old SGC-only columns in `shalimar_agent_settings` remain for compatibility. Updated code uses `shalimar_agent_source_settings` for each source's business agency and verification. Do not drop legacy columns casually.

## 7. What Sync Shalimar Agents actually does

1. Uses enabled SGC/Zalmi source records and each source's saved settings.
2. Calls the authenticated `dealers.php` endpoint with that source's configured business-agency ID.
3. Staff discovery selects **`BINARY ledger.sp_Agent = BINARY 'yes'`**.
4. Staff selection does **not** require `ledger.dealer='yes'`, does not require ledger agency `193/2`, and no longer uses a fixed ID list.
5. The API also returns locked flagged staff with inactive eligibility. Removing the flag makes the identity disappear from a subsequent complete snapshot.
6. Upserts source links; successful disappearance reconciliation sets missing links `present=0`.
7. Verifies the source-settings version only after that source's complete successful sync.
8. New identities receive a local person record. A name match does not automatically merge people.
9. Existing confirmed mappings are reused, so the next sync does not recreate mapped duplicates.
10. Invalidated existing assignments may be flagged for Admin review; history is retained.

**Sync does not assign properties, approve listings or send assignment SMS.** A failed source keeps its previous complete data; independently completed sources remain saved.

UI trigger: **Admin → Shalimar Agents → Sync Shalimar Agents**.
CLI trigger: `php spark erp:sync-agents`.
No automatic/cron scheduling was verified for this report.

## 8. Central agent mapping and profile behavior

Admin screen displays one row per central agent with all linked ERP memberships. `ERP Links / Map` lets Admin confirm that an unused ERP identity belongs to an existing local person. Name matches are suggestions only.

Mapping is transactional, audited and serialized against synchronization. It checks current ownership, rejects a second identity from the same source on the target central agent and is idempotent. An emptied donor person is retained disabled rather than deleted.

**History protection:** the moving identity/local record must have no listing or SMS history. When one identity has history, keep that person as the central record and link the unused identity into it. If both have history, the current mapping action refuses the operation; a separate reviewed history migration would be required. It does not rewrite approved listings/events/SMS jobs to conceal this conflict.

### Central business profile

- The **oldest linked ERP identity, by lowest `agent_erp_links.id`**, supplies the central name/mobile/email/photo.
- Secondary source sync updates its membership/eligibility but does not overwrite the shared profile.
- The profile source is independent of the ERP link saved on a particular listing.
- There is no separate per-link stored contact/photo profile or Admin-selectable primary-profile-source field in the inspected schema.
- If the primary source is unavailable, the existing central profile is retained; another filename is not silently substituted.

## 9. Customer submission and Admin approval

Existing selling methods: `self_sell`, `shalimar_agent`, `authorized_agency`.

| Method | Representative selection |
| --- | --- |
| Self Sell | Customer supplies the listing's public owner contact; owner name is not displayed publicly |
| Shalimar Agent | Customer chooses this method only; Admin selects the individual local agent during approval |
| Authorized Agency | Customer selects a mapped, eligible agency for that property's exact ERP source |

The customer agent API intentionally returns no agent choices and explains that Admin assigns them. A stale/forged customer agent ID cannot bind the final representative.

### Shalimar Agent approval

1. Admin reviews the listing and selects one eligible **local person**.
2. The dropdown groups by local agent ID and labels eligible sources, such as `SGC + ZALMI`.
3. A valid business mobile is required; email is optional but validated when supplied. `contact_approved` is not required.
4. New assignments prefer an eligible identity from the property's source. If unavailable, another eligible linked SGC/Zalmi identity can represent the property.
5. Exact `portal_agent_id`, `agent_erp_link_id` and `agent_source_id` are saved, together with `agent_snapshot` and the public representative payload.
6. Approval changes the listing to approved/live, records its version/event and queues an assignment SMS in the same transaction.
7. Delivery is attempted only after the transaction commits.

Existing assignments continue to validate their exact saved link. They do not silently switch to another source when their original identity becomes unavailable.

Approval still runs existing validation, RBAC, CSRF and concurrency checks. Fresh ownership verification depends on the existing `AdminReview` configuration; both verified and manual approval paths exist. Selecting an agent does not bypass those rules or guarantee public eligibility.

Public appearance additionally depends on the existing published project, active mapping/source, Head/Subhead `smp_active='yes'`, approved active listing and available deal rules. Sold/cancelled/rejected/hidden offers do not become public just because an agent is eligible.

## 10. Assignment SMS and delivery logs

Service: `AgentAssignmentSms` → existing `JazzCustomerSms` business-message transport.

Current message format:

> Shalimar Properties: Property {property}, project {project}, has been assigned to you. Listing SP-{id} is approved for {sale|rent}.

No customer CNIC, private ownership mobile or OTP is included.

Table: **`listing_assignment_sms`**. Important fields: listing/version, local agent, exact source/link, message, status, attempts, test mode, masked recipient, error category and attempted/sent timestamps.

Statuses include pending, sending, sent, failed, skipped and uncertain. Repeated approval does not resend a notification. Admin can explicitly retry eligible failures; uncertain accepted deliveries are not blindly resent. An SMS failure preserves the already committed listing approval.

`sent` means the configured gateway response was accepted by the sender; it is not independent proof of handset delivery.

**Live snapshot:** SMS enabled, **test mode ON**, configured test recipient ending **6415**. Production routing uses the selected central agent's business mobile when test mode is off. No credentials or full test phone are included in this report.

Relevant environment keys: `sms.enabled`, `sms.testMode`, `sms.testRecipient`, gateway configuration and the existing test-host/network safeguards. Preserve safeguards and do not copy secrets into task prompts.

WhatsApp OTP support elsewhere in the application is separate. The inspected assignment-notification service sends **SMS**, not an additional WhatsApp assignment message.

## 11. Public contacts, photos and agency logos

`PublicListingContact` resolves only locally public-eligible listings and currently eligible exact representative identities. It batches lookups rather than fetching ERP data per card.

- Agent business contacts and photo use the central person profile, with the correct profile source for the photo URL.
- Agency name/phone/email use the mapped central directory; the assigned source link supplies only validated logo metadata for branding.
- Raw ERP contact metadata is not used as a private-contact fallback.
- Valid phone enables Call/WhatsApp links; valid public business email enables Email.
- Missing/ineligible contacts show unavailable messaging and the separate existing Support option.

### Card presentation — deployed

- Compact **34px circular avatar**, name alongside it and small `Shalimar Agent` / `Agency` label.
- Agent photo or agency logo when available.
- Initials when no image exists or the browser image request fails.
- Agency logos use contain sizing; agent portraits use cover sizing.
- Owner cards continue to show **Owner**, without the customer's name/photo.

Image roots:

| Source | Verified URL builder |
| --- | --- |
| SGC | `https://sgcacc.com/customer-images/{safe-filename}` |
| Zalmi | `https://zalmidevelopments.com/ERP786-ZALMIDEV/customer-images/{safe-filename}` |

Agency sync can cache logos in `writable/agency-logos/`; `html/agency-logo.php` serves cached logos. Listing contacts prefer an available cached logo and otherwise provide the validated public source URL. Public rendering performs no server-side image download or ERP API fetch.

A saved filename does **not** prove the underlying file exists. Recent live browser checks displayed initials where images were missing/unreachable. Hassan has a saved filename; Bisma has no saved filename in the current central profile. A read-only Zalmi check found Hassan's saved file absent from both checked image folders. Do not promise that every stored filename will produce a visible photo.

## 12. Agency portal access

Agency sign-in: `https://shalimarproperties.com/public/agency/login`.

- `agency_portal_accounts` belongs to a central `portal_agency_id`, not a raw ERP agency ID.
- Admin creates/resets/enables credentials from agency details; passwords are hashed, with existing expiry/change-password/auth-version controls.
- External agency access requires current local enablement and an authorized source mapping.
- The configured internal Shalimar agency can use this same portal when its source-settings/mapping verification is valid.
- External agency portfolios use exact local agency/link/property-source assignment.
- The internal agency portfolio uses exact eligible Shalimar agent assignments; it does not assume every property or agent must belong to SGC.
- Portfolio queries are local and public-eligibility scoped. Private ownership/OTP details are not published.

**There is no separate individual Shalimar Agent login in the inspected route set.** Linking an agent to SGC/Zalmi does not create that person's username/password. The internal agency account is a business-level portfolio account. A future individual-agent login requires its own scoped design.

## 13. Admin/public URLs and routes

| Screen/action | Route relative to `https://shalimarproperties.com` |
| --- | --- |
| Agency directory Admin | `/public/admin/agencies` |
| Agency details and portal credentials | `/public/admin/agencies/{id}` |
| Agency ERP link details | `/public/admin/agency-links/{id}` |
| Agent directory | `/public/admin/agents` |
| Agent assignments | `/public/admin/agents/{id}` |
| Agent identity mapping | `/public/admin/agents/{id}/links` |
| Agent sync | POST `/public/admin/agents/sync` |
| Source settings | POST `/public/admin/agents/configuration` |
| Confirm agent identity link | POST `/public/admin/agent-links/{link_id}/map` |
| Listing review / assignment | `/public/admin/listings/{id}/review` |
| Assignment SMS retry | POST `/public/admin/listings/{id}/assignment-sms/retry` |
| Public agency directory | `/agencies.php` |
| Agency sign-in | `/public/agency/login` |
| Agency portfolio | `/public/agency/dashboard`, `/public/agency/listings` |

Existing Admin RBAC and CSRF protect mutations. Mapping requires agent-view/edit permissions; approval requires listing-approval permission; sync and settings retain their additional ERP permissions.

## 14. ERP APIs and owning source files

Existing base URLs: SGC `https://sgcacc.com/smp-api/v1`; Zalmi `https://zalmidevelopments.com/smp-api/v1`. Verify the current `erp_sources` rows before changing them.

| Endpoint appended to base | Purpose |
| --- | --- |
| `/agencies.php` | All agency authorization states, contacts and logo filenames |
| `/dealers.php?agency_id={configured-business-agency}` | Explicit sp_Agent staff directory, statuses, mobile/email/image filename |

Requests are authenticated server-side using existing bearer credential references. No public browser-direct ERP call, token exposure or ERP write is required.

In **both** local API folders, relevant files include `src/AgencyController.php`, `src/DealerController.php`, `src/CustomerDatabase.php`, `src/routes.php` and the existing `v1/agencies.php` / `v1/dealers.php` entry points.

Dealer API business-agency constants currently differ by package: **SGC 193, Zalmi 2**. Portal configuration resolves each source separately; changing a configured business ID also requires checking the source API's validation. Staff selection itself remains sp_Agent=yes, independently of the ledger's actual agency/dealer values.

## 15. Main portal files for the next task

| Responsibility | Files |
| --- | --- |
| Agency fetch/sync/matching | `app/Services/ErpAgencyApi.php`, `AgencySync.php`, `AgencyDirectory.php` |
| Agent fetch/sync/mapping/eligibility | `app/Services/ErpDealerApi.php`, `ShalimarAgentSync.php`, `ShalimarAgents.php` |
| Agent Admin UI | `app/Controllers/Admin/Agents.php`, `app/Views/admin/agents/index.php`, `links.php` |
| Agency Admin UI | `app/Controllers/Admin/Agencies.php`, `app/Views/admin/agencies/` |
| Listing approval / read model | `app/Services/AdminListingReview.php`, `AdminListingReviewReadModel.php` |
| Approval UI | `app/Views/admin/listing_review/detail.php` |
| Assignment SMS | `app/Services/AgentAssignmentSms.php`, `BusinessSmsGateway.php`, `JazzCustomerSms.php` |
| SMS status/retry UI | `app/Views/admin/listing_review/assignment_sms.php` |
| Customer submission | `app/Controllers/Customer/Resale.php`, `app/Services/ResaleSubmission.php` |
| Public contacts/cards | `app/Services/PublicListingContact.php`, `html/includes/bootstrap.php`, `html/assets/css/public-listings.css` |
| Public detail | `html/listing.php` |
| Public agency directory | `app/Services/PublicAgencyDirectory.php`, `html/includes/agencies-directory.php` |
| Photos/logos | `app/Services/ShalimarAgentImage.php`, `AgencyLogo.php`, `AgencyLogoCache.php`, `html/agency-logo.php` |
| Agency account/session/portfolio | `app/Services/AgencyPortalAccounts.php`, `AgencyPortalSession.php`, `AgencyPortalListings.php` |
| Route/security/audit | `app/Config/Routes.php`, `app/Filters/AdminPermission.php`, `app/Services/AdminAuditService.php` |
| ERP connection configuration | `app/Config/ErpConnections.php`, existing `erp_sources`, server-side `.env` credential references |

## 16. Deployment history and current verification

Relevant packages, in chronological order:

1. `deploy/live-sp-agent-sync/` — earlier staff-flag/image integration.
2. `deploy/live-agent-source-settings/` — separate source verification/configuration.
3. `deploy/live-central-shalimar-agents/` — one local person/multiple source links and grouped Admin choices.
4. `deploy/live-listing-profile-avatars/` — card avatars, agency branding and verified Zalmi image-path correction.

**These are historical snapshots, not interchangeable full releases. Uploading an older package over newer source can undo later fixes. Build the next deployment package from the current owning application files.**

Most recent focused checks: **116 passed** using guarded disposable schemas and fake ERP/SMS providers. Coverage includes source isolation, sync preservation, central mapping, exact assignments, audit rollback, duplicate prevention, source revocation, profile/image source, agency logo safety and representative privacy.

Browser checks cover compact avatars and missing/broken-image initials at 360/390/1440px; central Admin directory/mapping UI and approval interaction were also checked with fixtures. Live homepage/Buy/current public detail checks returned HTTP 200. Authenticated live Admin browser behavior was not exhaustively exercised.

Report preparation performed read-only live inspection only: no new SQL, migration, API/ERP changes, mappings, approval, SMS, deployment or account changes were executed for this report.

## 17. Known limitations and guardrails for the next command

1. New matching agent names are not automatically linked; Admin confirmation is required.
2. Agents do not have the same nullable-unmapped/link queue schema as agencies: a new agent identity initially gets a local record, and Admin consolidates unused duplicates.
3. Merging two already-used person identities is deliberately blocked; do not bypass the historical-reference guard.
4. Primary business profile source is the oldest link, not an Admin-selectable preference. Secondary contact/photo variants are not independently stored per link.
5. One source's failure preserves previous synced state; it does not prove current remote eligibility until a later successful sync.
6. External-agency restricted handling differs from internal staff business-agency handling. Preserve the documented distinction.
7. Directory counts, selectable-agency counts and exact assignment eligibility are different concepts.
8. An individual agent has no dedicated sign-in account yet.
9. Assignment WhatsApp notifications, delivery webhooks/handset receipts and automatic cron scheduling were not established by this implementation.
10. Missing image files require initials; do not substitute customer identity/document photos or private contact data.
11. Deleting an unused source-linked agent does not permanently suppress the ERP record: a later sync may import it again if it is still flagged. Confirmed moved links are preserved and do not recreate mapped duplicates.
12. Do not hardcode current local IDs, infer missing schema, rewrite ERP business data, overwrite confirmed mappings, expose credentials or remove existing customer/public eligibility checks.

For the next task, describe the desired behavior separately, refer to this report as context, and ask the implementing agent to inspect the actual owning files and current schema first. If additional schema is genuinely required, prepare minimal idempotent manual SQL and respect the user's confirmation workflow before dependent implementation.

## 18. Copy-ready next-task brief

Use this as the starting block for a new task. Replace the bracketed portion only:

```text
Project: Shalimar Properties CI4 portal.

Read SHALIMAR-AGENTS-AND-AGENCIES-OVERVIEW.md first. It documents the current central Agency and Shalimar Agent implementation, source-specific ERP links, eligibility, assignment snapshots, public privacy rules and the live SGC/Zalmi API boundaries.

New requirement:
[Describe the one requested behavior clearly.]

Required preservation:
- Keep one central agency/person with separate SGC and Zalmi ERP links.
- Do not merge identities automatically by name or overwrite confirmed mappings.
- Preserve exact source-specific assignment history and existing listing records.
- Keep ERP requests server-side, authenticated and read-only.
- Preserve Admin RBAC, CSRF, audit logging and current customer/public privacy rules.
- Do not expose ERP credentials, customer private contact data or private images.
- Do not change ERP business records.
- If a schema change is genuinely needed, first provide idempotent MariaDB SQL for manual phpMyAdmin approval and stop before code that depends on it.

Scope:
- Inspect the current owning files and actual schema before changing anything.
- Keep the change limited to this requirement; do not replace unrelated agency/agent, listing, SMS or public website behavior.
- State any source-specific difference between SGC and Zalmi explicitly.

Verification required:
- Test both SGC and Zalmi source behavior.
- Test existing mapping/history preservation, authorization/eligibility, Admin permission and CSRF behavior.
- Test missing contacts/photos/logos safely fall back without leaking private data.

Final report:
- Changed files, any SQL, API endpoints used/added, test results, deployment paths and known limitations.
```

This is a context handoff only. It is not an instruction to reuse stale local IDs, credentials, old deployment folders or fixture results without first checking the live/current source of truth.
