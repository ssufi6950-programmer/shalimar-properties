> Superseded by [ERP-MARKET-INTELLIGENCE.md](ERP-MARKET-INTELLIGENCE.md). The following records the previous listing-based implementation and is not the current reporting source.

# Dynamic Market Intelligence

Implemented locally on 10 November 2026. The existing homepage section keeps its dark green gradient, decorative pattern, heading, gold report button, sparkline panel and four-card layout. The existing report at `html/insights.php` now uses the same database read model. No ERP API, migration, credential or customer publication workflow change is required.

## What the numbers mean

These metrics represent **first successful published resale listings**, not completed ERP sales. Neither inspected API folder exposes an authoritative completed-resale feed that the portal already synchronizes. Local catalog/ownership tables and a Sold listing status do not establish a completed transaction date or sale price.

The public labels are **Resale Listing Activity**, **Today**, **Last Month**, **This Month**, **Asking Price vs Last Year**, and **New resale listings per day · last 30 days**. The heading remains “Negotiate with real numbers, not rumours.” The description explicitly distinguishes a published listing from a completed sale. The existing homepage hero/project counts, which share this data, use “New resales” labels as well.

The chart has exactly 30 Pakistan-local calendar dates, including today. Missing days are zero; the displayed total is the sum of all 30 values. This rolling window is independent of the current-calendar-month card. The SVG retains its original gold stroke/area and now has an accessible description containing all 30 dates/counts.

## Inspected schema and authoritative publication event

The existing migrations and transactional writer `ResaleSubmission::save()` establish these real tables/fields:

| Table | Use |
| --- | --- |
| `listing_events` | First `customer_published` event with `to_status='live'`; `created_at` is its successful publication time. `next_record` retains the original listing/asking-price snapshot. |
| `portal_listings` | Current local listing identity, customer/source/mapping relationship and suppression state. Its mutable `created_at`, `updated_at`, `asking_price` and payload `published_at` are **not** historical publication/price fallbacks. |
| `resale_otp_challenges` | Join the **first event's** `authorization_id`, not the listing's latest verification. Require consumed `delivery_state='sent'`; exclude preview-only testing. Only delivery/customer relationship fields are inspected, never OTP hashes, encrypted payloads or mobile numbers. |
| `erp_project_mappings` | Exact source/Head/Subhead/project relationship and active mapping. |
| `erp_sources`, `erp_heads`, `erp_subheads`, `portal_projects` | Existing `ErpVisibility::joins()`/`mappingSql()` eligibility, including exact string `'yes'` and published projects. |

`MIN(listing_events.id)` for the original successful `customer_published` event yields one observation per listing. Drafts, `customer_submitted`, OTP retries, `customer_edited` and `customer_republished` do not create new-listing activity. No publication event means no guessed publication date. Invalid snapshots are excluded safely.

Previously published Sold and customer-withdrawn records retain their original count while their source/project/mapping remains publicly eligible. Rejected, pending, removed, admin-unlisted and non-customer cancellation records are excluded. A source, mapping or Head/Subhead deactivation suppresses its historical aggregates on the next request. Explicit sample/demo/test/Jaageer source markers and preview-only publications are excluded. No rows are deleted or rewritten.

There is no separate historical aggregate-suppression column in the inspected schema. Existing public catalog/admin suppression is applied. Old test records that were sent through the normal SMS path and have no identifying test marker cannot be reliably distinguished retrospectively; they must not be silently classified using names, phone numbers or invented criteria.

## Time periods and exact count queries

The business timezone is `Asia/Karachi`. Existing `ShalimarAgents::now()`/`ListingLifecycle::record()` store **Pakistan-local DATETIME**, so the default service does not incorrectly add five hours to these values. An explicit `UTC` storage constructor option is tested for a deployment that actually converts its stored history; it is not enabled speculatively.

All calendar periods use inclusive starts and exclusive next-day/month ends, with a cap excluding timestamps later than the current instant:

- Today: local midnight through tomorrow midnight, capped at now.
- Last Month: first day of the previous complete calendar month through first day of this month.
- This Month: first day of this month through first day of next month, capped at now.
- Chart: today minus 29 calendar days through tomorrow midnight, capped at now.

[`database/sql/market-intelligence-queries.sql`](database/sql/market-intelligence-queries.sql) contains **complete, runnable read-only reference SQL** for Today, Last Month, This Month and the zero-filled 30-day chart, including the exact publication eligibility query. These are inspection references, not a migration/import requirement.

In the application the first three counts and daily/monthly chart buckets come from one parameterized SQL aggregate:

```sql
SELECT DATE(a.published_at) AS day, COUNT(*) AS activity
FROM (<MarketIntelligenceService::publicationSql()>) a
WHERE a.published_at >= :start
  AND a.published_at < :end
  AND a.published_at <= :now
  /* bound project / sector / block / category predicates */
GROUP BY DATE(a.published_at)
ORDER BY day;
```

`:start` is the first day of the same calendar month last year; `:end` is first day of next month. The service derives Today/Last Month/This Month from these daily aggregates and fills the 30 chart dates. A second query selects only original public price/area descriptors for the two comparison months. A third aggregates sizes/localities/months for the existing report, CSV and homepage project counts. A complete snapshot uses **three prepared reads**, independent of listing count. Report project labels reuse the already-loaded project directory, avoiding per-row project queries.

## Asking-price comparison

Historical price is `listing_events.next_record.asking_price` at original successful publication. Area and property category/locality also come from that original record's JSON payload. Today's edited price is never substituted for last year's observation.

Within each exact comparable group:

```text
rate = original asking price / explicitly recorded area
change (%) = (median(current-month rates) - median(same-month-last-year rates))
             / median(same-month-last-year rates) * 100
```

A group matches project **and exact ERP source/Head/Subhead**, property category, sector, block, area unit and exact normalized size. At least **five valid observations in each month per group** are required. Non-positive prices/areas, unsupported property categories, ambiguous size labels and missing historical snapshots are omitted. Zero denominators never yield a percentage.

Unit spelling aliases normalize (`square feet`, `sq ft`, `sqft`, `Marla`, etc.). The ERP/API may supply a raw size with no trustworthy unit, and the portal writer historically defaulted such values to marla. A nonempty unitless `size_label` is therefore rejected for price analysis. There is no verified universal marla-to-square-foot conversion saved with these records. Marla, kanal and square feet remain separate unit families; no 225/272.25 conversion or inferred area is introduced. Only explicitly compatible sizes/units are compared.

For one matched group, the displayed percentage is exactly the formula above. For a broader filter/site-wide display, each matched group contributes one price relative (`current median / previous median`). The overall index is **the median of those equally weighted relatives minus one**, multiplied by 100. Increasing one project's listing volume cannot change its weight or create apparent appreciation from a property-mix shift. The report documents this methodology and shows each included group's medians and sample counts.

Values round to one decimal (`+12.5%`, `-7.3%`, `0.0%`). Missing last-year history, fewer than five observations, or no compatible group yields **N/A / Insufficient data**, never a fabricated percentage. The fixture percentage in QA screenshots is not a claim about the live market.

## Existing market report and privacy

`Open the market report` retains its destination, `insights.php`. Project/Sector/Block filters and the existing Chart.js daily, size, monthly and YoY chart panels remain. A property-category filter applies the same scope to every metric. Labels now describe listing activity and median asking prices; sample transactions and unsupported completed-deal claims are removed from this report.

The last-month table/CSV contains grouped month/project/locality/category/size counts. It does not expose individual listing IDs, ownership/booking payloads, CNICs, private contacts, customer IDs, OTP values, credentials or admin notes. HTML is escaped; chart JSON is encoded for safe embedding; CSV cells that begin with spreadsheet control characters are escaped. A report failure shows unavailable/N/A, and CSV responds with safe HTTP 503 rather than outputting SQL or fake zeros.

There is no persistent cache: only a per-request PHP snapshot is reused. Subsequent requests automatically see publication, withdrawal, Sold, admin suppression and ERP visibility changes. No manual admin refresh, scheduled ERP fetch or new public API is required.

## Exact application files

New:

- `app/Services/MarketIntelligenceService.php`

Updated:

- `app/Libraries/PortalCatalogStore.php` — read-only `marketSnapshot()` adapter; existing write/contact/public visibility methods preserved.
- `html/includes/market.php` — shared snapshot/formatting helpers; old transaction helpers remain for unrelated legacy modules.
- `html/includes/home/common.php` — shared real statistics and sparkline input.
- `html/includes/home/sections/market_band.php` — original design, dynamic values and accurate labels.
- `html/includes/home/hero-b.php` — accurate shared counter labels.
- `html/includes/home/sections/spotlight.php` — real per-project listing counts and accurate label.
- `html/insights.php` — existing report consumes the same aggregates and safe grouped CSV.
- `html/assets/css/home-base.css` — wrapping/minimum sizing for longer chart labels and values, retaining existing layout/background.
- `html/includes/header.php` — cache version for the updated homepage stylesheet.

Supporting artifacts: this report, `database/sql/market-intelligence-queries.sql`, guarded `.qa/market_*` helpers, `.qa/verify_market.py` and `.qa/export_market_sql.php`. QA files/data are not deployment files.

## Verification

Tests run against a separate loopback MariaDB instance on port **33317**, using uniquely named disposable schemas created by the project's real migrations. Test account/property records are fixtures. Real customer data, real SMS and live ERP requests are not used.

| Executed suite | Result |
| --- | --- |
| `.qa/verify_market.py`: service/SQL checks | **52 passed**: true publication counting, drafts/pending/rejections/test exclusions, edits/republish deduplication, Sold/withdrawn history, visibility suppression, first-price snapshots, filters/SQL injection, fixed query count, Pakistan midnight/month/year/leap/UTC boundaries, 30 dates/zeros/totals, medians, unit rejection, positive/negative/zero/insufficient YoY, fixed group weighting, no private output and read-only checksums. |
| `.qa/verify_market.py`: HTTP/browser | **63 passed**: homepage/report agreement, honest labels, grouped CSV, filters, real Chart.js rendering/summed 30-day data, all required widths, original four cards/gold button/sparkline, no overlap/overflow/clipping, safe empty/unavailable states and preserved file hashes. |
| Runnable SQL references | **4 passed**: all three standalone count queries agree with the service, and the recursive chart query returns the same zero-filled 30 dates/total. |
| Existing `.qa/verify_customer_live.py` | **56 passed**: customer login/OTP/current ownership/properties/floor/retries/partial sources/refresh/layout/logout; fake providers. |
| Existing `.qa/verify_phase1_safety.py` | **65 passed**: Admin RBAC/CSRF/legacy publication blocks, customer entry routing, public contacts/visibility and real guarded Unpublish. |
| Existing `.qa/verify_agency_portal.py` | **153 passed**: agency authentication, assignment isolation, read-only listing details, RBAC boundaries, password changes/reset, responsiveness and logout. |

**393 automated checks passed**, plus PHP syntax checks. Existing suites use `.qa/market_regression.py` only to route their SQL connections to the separate QA instance; their assertions and production application code are unchanged.

Viewport coverage: **360, 390, 768, 1024, 1440px**. Both homepage/report have no page overflow or clipped metric values. Homepage cards do not overlap, the sparkline remains readable and the gold report link is accessible above phone navigation. All four existing report charts render real data. Screenshots: `.qa/market-index-390.png`, `.qa/market-index-1440.png`, `.qa/market-insights-390.png`, `.qa/market-insights-1440.png`.

Logs: `.qa/market-intelligence-checks.log`, `.qa/market-sql-reference-checks.log`, `.qa/market-customer-regression.log`, `.qa/market-safety-regression.log`, `.qa/market-agency-regression.log`. `.qa/market-preserved-files.json` records unchanged existing App/config/controller/service and both ERP API PHP file hashes, excluding the one intentionally extended catalog adapter and new market service. Screenshots wait for the existing reveal/text/sparkline animations to settle; production animations remain unchanged.

## Environment, limitations and FTP rollout

The existing local XAMPP MySQL instance cannot start: its privilege table **`mysql.db` is marked crashed**. Startup also reports pre-existing missing test tablespaces. No privilege-table/ERP-ledger repair or production-row change was attempted. Consequently existing real local database contents/counts could not be queried; inspection used the source migrations/writers/API implementation and integration tests used freshly migrated isolated tables. This is not a claim that live data or live MySQL has the same fault.

Legacy imported listings without a successful publication event and original price snapshot are intentionally excluded. No dates/prices are reconstructed from mutable payloads. Short price history will therefore correctly produce N/A. No live deployment or live-data/SMS verification was performed.

FTP package: `deploy/live-market-intelligence`. Upload its **10 application files** under `app` and `html` into the matching paths below `/home/propertys_user11/www/`. The included README maps each exact local file to its full live path. Upload the new service first, then the adapter/helpers, then the templates/CSS together. The domain's document root remains `/home/propertys_user11/www/html`; private `app` remains its sibling. No folder move, `.env` edit, SQL import, `public` bridge change or ERP API upload is required. The existing Phase 2/lifecycle schema must already be deployed, as before.
