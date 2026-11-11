> Superseded on 11 November 2026 by [ERP-MARKET-TRANSFER-DATE.md](ERP-MARKET-TRANSFER-DATE.md). The approval-history rules below are archived and no longer apply.

# ERP-powered Market Intelligence

10 November 2026. This replaces the Portal-publication-based Market Intelligence feature. The homepage/report now use authenticated ERP reporting APIs exclusively. The former local-listing calculation was removed; its class is only a compatibility facade forwarding to the ERP service.

**Code and isolated integration tests are complete. Reliable full reporting on the actual data is blocked by missing official completion history and deployment.** These limits are deliberately surfaced as unavailable, never as zero or Portal-listing counts.

## Actual repositories and schema

- SGC: full Core PHP source and local database `u830963356_sgcacc_db` were inspected, plus `D:/xampp/htdocs/sgcacc/smp-api`.
- Zalmi: `D:/xampp/htdocs/sgcacc/zalmi-smp-api` is accessible. The user previously confirmed that its live ERP is an unchanged SGC copy. Its source-specific adapter/profile implements that confirmed workflow and checks its own database columns on every request. A separate live Zalmi schema/business source was **not** independently inspected or verified.
- Portal: its existing ERP Sources supply connection configuration. Its project mappings may label/filter ERP segments, but a Portal listing or a mapping is not required for a deal to count.

The actual ERP fields used are:

| Table | Fields / purpose |
| --- | --- |
| `companies` | `id`, `name`: resolve the exact verified business company name, without copying local IDs to live. |
| `pro_payment_booking` | `id`, `company_id`, `property_id`, `sale_type`, `property_status`, `in_process`, `cancel`, `head`, `sub_head`, `property_catagory`, `created_at`: official resale booking identity/state/dimensions. |
| `booking_in_process_approval` | `booking_id`, `company_id`, `approved`, `approved_by`, `created_at`, `updated_at`: actual final approval state/time. |
| `booking_approval` | `booking_id`, `company_id`, `approved`: exclude pending/rejected/unknown approval states where present. |
| `pro_return_booking` | `booking_id`, `company_id`, `recovered`: exclude unrecovered reversed bookings. |
| `pro_return_approval` | `booking_id`, `company_id`, `approved`, `restored`: exclude approved, unrestored returns. |
| `item_location`, `item_sublocation` | `id`, `name`: public Head/Subhead labels only. Their current active flags do not determine historical deal eligibility. |

Inspection also covered `pro_inventory`, `property_unit`, `pro_payment_booking_return` and `voucher`. Their current area/price, booking totals, balances, fees and commissions are not treated as immutable completed resale consideration.

Schema evidence: `.qa/erp-market-schema-inspection.json`. No ERP business PHP file, booking, owner, ledger, voucher or financial row was changed.

## Official completion criteria and deduplication

The observed `admin/property/property-resale.php` writes `sale_type='resale'`, `property_status='resale'` and sets `transfer_date=time()` **during creation**, while the schema defaults `in_process='yes'`. Thus creation/transfer date does not prove final approval.

`in-process-booking-approval.php`, `property-booking-in-process.php` and related approval handlers clear `pro_payment_booking.in_process` to `'no'` and write `booking_in_process_approval.approved='yes'`, `approved_by`, `updated_at=time()`. Reporting therefore uses the recorded official approval timestamp, not Portal publication, booking creation or owner changes.

A qualifying completed deal requires:

1. Exact `sale_type='resale'`, `in_process='no'`, `cancel='no'` in the scoped company; recognized finalized property state (`resale`, `booked`, `complete`, `ownership`).
2. All associated in-process approval rows are explicitly `'yes'`, have a positive approver and a positive, ordered approval timestamp.
3. The final required approval time is `MAX(booking_in_process_approval.updated_at)`, a Unix timestamp not preceding booking creation.
4. No pending/rejected/unknown separate booking approval, active reversal or approved/unrestored return.
5. Completion is within the requested interval and not later than the report instant.

Approval rows are grouped by `(company_id, booking_id)` before joining, then deals use `COUNT(DISTINCT pro_payment_booking.id)`. Source identity is kept separate: SGC booking 100 and Zalmi booking 100 are independent deals. Vouchers/debit/credit workflow rows are never counted as separate deals.

`locked='yes'` is **not** an exclusion: the ERP locks previous bookings when a later resale occurs. Valid historical resales remain counted. No current inventory owner/status, Portal publication/status or today's Head/Subhead `smp_active` flag is used to erase history. Explicit reversals/cancellations do remove invalidated deals on refresh, following the observed ERP return process. Deleted/archived reversed bookings do not count.

No separate correction-completed event or actual-price audit feed was found. The adapter recognizes explicit ERP resale/approval semantics; it does not infer a resale from owner changes, generic transfers or booking corrections.

## Scope

Each ERP resolves the exact company name **Shalimar Sales & Marketing** independently. In the inspected SGC database it resolves to **company 3**; that ID is not hardcoded in either adapter. Other businesses in the same ERP are excluded. The observed SGC resale population belongs to this company.

The Zalmi profile uses the user-confirmed copied company/workflow. If its actual company name differs, set only `SMP_API_MARKET_COMPANY_NAME` in that API's existing private `src/catalog.local.php` to its verified company name. Missing/ambiguous company names or missing schema return safe unavailable, not a guessed scope or zero.

All qualifying Head/Subhead populations within this company are included, including deals never advertised on the Portal. Historical current-active flags are intentionally ignored. Report project options come from ERP-derived aggregate dimensions, including unmapped projects. A legacy Portal project slug may resolve to its exact source/Head/Subhead metadata as an explicit filter; it is not the default reporting population.

## Completion-history blocker found in actual data

Read-only SGC inspection found **625** uncancelled resale rows marked `in_process='no'` without a valid dated approval. After the adapter's rejection/return exclusions, **624** finalized candidates remain without verifiable completion history. There are also dated approvals, but presenting just that subset as complete SGC totals would be misleading.

The endpoint therefore returns **HTTP 503 `market_completion_history_unverified`** when this gap exists in the relevant company population. It cannot safely assign undated finalized records to any calendar period. It does not substitute `transfer_date`, `created_at`, `updated_at` on the booking, or a made-up date. The existing transfer date is known to be set before finalization.

The Portal distinguishes this missing-history state from transport outages and genuine zero. A recent last **complete** cached report may still be shown with its timestamp; otherwise combined metrics remain unavailable. This applies even if one ERP returns a valid subset: no partial total is advertised.

**Required clarification:** identify the authoritative historical completion-date field/report for those records, or an explicitly verified reporting-history policy. A question requesting that information was sent. No completion dates were invented or written back. Actual local ERP checksums remained unchanged during inspection (`.qa/erp-market-local-schema-probe.log`).

## APIs, authentication and transport

Both API folders now implement **POST `/smp-api/v1/market-intelligence.php`** through the existing authenticated router. Profiles select separate `SgcResaleMarketAdapter` / `ZalmiResaleMarketAdapter` classes; the shared copied-workflow implementation checks each host's actual required columns.

The request is a bounded JSON object with `as_of` in Pakistan ISO time, optional exact Head/Subhead `pairs` and a supported property category. The timestamp must be within five minutes of the ERP clock. Arbitrary date-range requests, invalid identities/categories, over 30 pairs and bodies above 8 KiB are rejected. The reporting window is a fixed 12 calendar months, with aggregate/response capacity limits. No unbounded transaction download or property import is implemented.

Responses contain only safe counts, 30 daily values, 12 monthly values, Head/Subhead/category aggregates, public project names, scope/completion-basis metadata and an unsupported-price reason. They contain no transaction/customer identities, CNIC, mobile, booking payloads, credentials or internal notes.

Existing bearer token hashes/private DB configuration are preserved. `CustomerDatabase` restricts this endpoint to prepared **SELECT** operations and never loads ERP business/session handlers. Portal transport reuses existing `erp_sources`, `erp.allowedHosts`, `erp.credentialRefs`, server-side bearer tokens and public IP overrides. It enforces verified HTTPS, approved hosts/public pinned destinations, no proxy/redirect following, response-size bounds, 2-second connection/6-second total timeouts. Browser code never receives bearer credentials or calls protected ERP URLs.

No Apache/proxy changes or new credentials are introduced. Deployed HTTPS routing remains the existing server responsibility; no unreliable PHP proxy-HTTPS inference was added.

## Dates, counts and chart

All business dates use `Asia/Karachi`; ERP approval values are Unix epoch seconds. SQL converts epoch to a Pakistan-local day using explicit `+18000` seconds, independent of MySQL session timezone/timezone tables.

- **Deals Today:** sum of both ERPs' distinct qualifying completions from local midnight inclusive to tomorrow midnight exclusive, capped at now.
- **Last Month:** sum of both ERPs from the first day of the previous complete calendar month inclusive to the first day of this month exclusive.
- **This Month:** sum from this month's first day inclusive to next month's first day exclusive, capped at now.
- **Deals per day · last 30 days:** exactly 30 local dates, today minus 29 days through today. Missing days are zero only after successful complete reports. Its total is the sum of those dates, independent of calendar-month counts.

The exact SELECT/grouping/completeness guard is in each API's `src/MarketIntelligence.php`. The Portal validates source identity, basis, completeness, period boundaries, all dates/counts, chart sum, current/previous monthly totals, breakdown sums, duplicate dimensions and filter scope before combining either response.

## Actual-price comparison

**Public price metric is N/A.** `total_price` is assigned from `net_rate`; resale JavaScript can populate the rate/installments from `main_data.pre_balance` / previous pending installments. Neither that carried balance, `sale_rate`, mutable inventory `actual_price`, transfer fee, commission nor advertised Portal price is verified as the actual resale consideration with immutable historical area snapshots.

No completed-sale price feed or genuine historical area/consideration distribution was found, so no price percentage is calculated from these fields. The report clearly explains this; size/sector/block history panels remain honest unavailable states. ERP Head/Subhead/category filters and daily/monthly charts remain functional.

If verified comparable actual consideration is provided later, the required formula is:

```text
(median current-MTD actual price per verified area unit
 - median equivalent prior-year-MTD actual price per verified area unit)
 / median equivalent prior-year-MTD actual price per verified area unit * 100
```

Equivalent MTD uses Pakistan calendar days/time, with February 29 clamped to February 28 in a non-leap prior year. Minimum five valid transactions per comparable period, positive denominator and verified compatible property/project/size/unit scope are required. Current data does not establish these prerequisites.

`ErpMarketPeriods` includes tested exact-frequency median/formula helpers for a future approved comparable distribution. They merge frequency bins before computing a median; they do **not** average or take the median of two source medians/percentages. These helpers do not turn the current unsupported price API into a price source, and are not used to invent a live combined YoY figure.

## Portal integration, report and caching

`ErpMarketIntelligenceService` reads local ERP connection configuration and optional mapping labels only. Its SQL is guarded in tests against any access to Portal listings, listing events, ownership, customers or OTP records. Both source responses must be complete; one/both failures never fall back to Portal listing activity.

Private aggregate files live in `writable/cache/erp-market-intelligence`:

- Complete results are fresh for **300 seconds**.
- A previously complete combined result may be used on failure up to **1800 seconds**, explicitly labeled with Last updated / cached state.
- Failed refreshes have a **60-second** retry cooldown; across all public filter keys there is a maximum **12 paired refreshes per minute**.
- Source enable/configuration/credential changes, filters and Pakistan date boundaries change/invalidate the cache identity. A disabled/missing source cannot silently reuse prior combined totals.
- Homepage and unfiltered report normalize empty filters to the same cache entry/update time.
- Cache files are private and replaced atomically under a lock. No business data, transaction list or credential is cached.

The existing gold button still opens `html/insights.php`. Its cards, daily/monthly Chart.js panels and grouped CSV use the same ERP service. Verified ERP project/category filters remain; unverified historical sector/block/size and price data show unavailable. CSV contains grouped ERP month/source/Head/Subhead/category counts, escaped against spreadsheet injection; no private transaction records.

The original green/dark gradient, gold accents, decorative pattern, heading, four cards, chart panel, typography and responsive direction are preserved. The shared hero/project counters use completed ERP resale labels too.

## Files and deployment

New Portal files:

- `app/Services/ErpMarketApi.php`
- `app/Services/ErpMarketIntelligenceService.php`
- `app/Services/ErpMarketPeriods.php`

Updated Portal files:

- `app/Services/MarketIntelligenceService.php` (compatibility facade; old local-listing SQL removed)
- `app/Libraries/PortalCatalogStore.php`
- `html/includes/market.php`
- `html/includes/home/common.php`
- `html/includes/home/sections/market_band.php`
- `html/includes/home/hero-b.php`
- `html/includes/home/sections/spotlight.php`
- `html/insights.php`
- `html/includes/header.php`
- `html/assets/css/home-base.css`

In **each** ERP API folder, new files are `src/MarketIntelligence.php`, `src/market-profile.php`, `v1/market-intelligence.php`; updated files are `index.php` and `src/routes.php`. Private `catalog.local.php` is not included or replaced. No Core PHP business source file changed.

**No database migration, SQL import, ERP record write, authentication/RBAC/CSRF or customer resale workflow change is required.** Existing Phase 2 schema remains required for pre-existing Portal modules, independently of Market Intelligence.

FTP package: `deploy/erp-market-intelligence`. Its README maps exact local files to live destinations. Upload the **Portal 13 files** to matching `/home/propertys_user11/www/` paths. Upload each source's **five API files** into its existing live `smp-api` folder. Zalmi's local folder is named `zalmi-smp-api`, but the live configured endpoint uses `/smp-api/`.

The old `deploy/live-market-intelligence` listing-based bundle is superseded and must not be deployed for this task.

## Executed verification and limits

| Suite | Result |
| --- | --- |
| Real authenticated ERP endpoint copies + Portal HTTP/browser (`.qa/verify_erp_market.py`) | **68 passed**: both adapters/scopes/counts, pending/cancelled/reversed/rejected/future/undated exclusions, state restoration, duplicate approvals, historical locked rows, requests/auth/input bounds, missing completion-history failure, no private output, ERP deals without Portal listings, source/project filtering, shared homepage/report values, grouped CSV and all required widths. |
| Service/cache/math (`.qa/erp_market_service_checks.php`) | **38 passed**: complete source combination, strict response validation, 30 days/sums, no local business reads, complete-cache fallback/expiry/cooldown, missing-history reason, no partial totals, calendar/UTC/year/leap boundaries, no actual-price fallback, valid actual-price math, no median-of-medians and shared normalized cache identity. |
| Customer Portal regression | **56 passed**. |
| Phase 1 Admin/public/workflow safety regression | **65 passed**. |
| Agency Portal regression | **153 passed**. |

**380 automated checks passed**, plus PHP syntax checks. Fixtures use uniquely scoped disposable MariaDB schemas on loopback port 33317, actual inspected ERP column types and separate authenticated endpoint copies. ERP HTTP transport is mocked/injected only in the QA router; production host/TLS restrictions were not weakened. No real SMS or real ERP transaction mutation was performed. Existing regression assertions remain unchanged.

Responsive checks at **360, 390, 768, 1024 and 1440px** passed: no page overflow, clipped figures or broken four-card/graph layout. Real Chart.js renders the 30-day ERP series. QA screenshots: `.qa/erp-market-index-390.png`, `.qa/erp-market-index-1440.png`, `.qa/erp-market-insights-390.png`, `.qa/erp-market-insights-1440.png`. Screenshot counts are isolated fixtures, not live deals.

Logs: `.qa/erp-market-checks.log`, `.qa/erp-market-service-final.log`, `.qa/erp-market-customer-regression.log`, `.qa/erp-market-safety-regression.log`, `.qa/erp-market-agency-regression.log`, `.qa/erp-market-local-schema-probe.log`, `.qa/erp-market-live-probe.log`. Of 267 previously protected existing App/API PHP files, 263 remain byte-identical; only the four intended original API index/route files differ. New files/intentional Portal adapter/template changes are separately listed above.

Live read-only probes returned **SGC HTTP 404** and **Zalmi HTTP 301** for the new endpoint. Neither returned a verified live report. Redirects were deliberately not followed with bearer credentials. API upload/routing and post-upload verification remain required. FTP/server deployment was not performed by this agent.

Remaining blockers are the missing dated completion history, independent verification of Zalmi's deployed schema/workflow, and successful deployment/verification of both reporting endpoints. Do not describe the feature as fully live or invent a complete total while these conditions remain unresolved.
