# Market deals: sales, resales and check-ins

Implemented locally on 11 November 2026. This updates the existing ERP market report, homepage chart, KPI counters and project deal counts. It supersedes resale-only and resale-plus-check-in totals; no live deployment has been performed.

## Counting and dates

- A deal total is qualifying sale/resale bookings plus recorded check-in events. These are activity counts, not unique properties, completed legal sales or monetary sales volume.
- Booking rules: the API's configured sales company, exact `sale_type != 'sale_return'`, `locked='no'`, and the saved booking `transfer_date`. Sales and resales are included; sale returns are excluded. Date, lock and company rules are unchanged. NULL sale types are excluded by the SQL inequality. Sales without a valid saved deal timestamp are not assigned an invented date. The internal database field is preserved; public UI calls this a deal date.
- Check-ins come from `m_check_in`, exact `status='check-in'`. The source's configured sales company, `Shalimar O & M Services` and `Smart Business District` are included. Company IDs are resolved independently on each ERP; optional maintenance companies absent on a source contribute no events. Ambiguous names fail safely. Zalmi's existing configured company override is preserved.
- Each check-in ID counts once. Both owner and tenant check-ins are included. Check-out records do not count. A historical check-in remains counted if a later check-out/replacement locked its record; current occupancy is a separate concern.
- `m_check_in.date` is a Unix timestamp stored as VARCHAR. Only absent/blank/zero values fall back to a valid saved `start_date`, supporting the observed `DD-MM-YYYY` format and strict `YYYY-MM-DD`. A nonempty malformed date is not replaced. Impossible dates, missing dates and future events do not receive an invented date. `created_at` is never a date fallback.
- All periods use Pakistan time, independent of the database session timezone. Today, previous full month, this month to the report instant, 30 daily dates and 12 calendar months retain their existing boundaries.
- Head/Subhead labels come from `pro_inventory`, never stale booking/check-in dimension fields. Sale/resale bookings retain their company-matched inventory join. O&M/SBD check-ins refer to sales-company inventory in the inspected schema, so check-ins join through `property_no = pro_inventory.id` with inventory labels restricted to the approved company scope. Missing/out-of-scope inventory remains an explicit Unassigned reporting bucket without dropping the event.
- Check-in property category comes from inventory; unsupported categories remain an unassigned category instead of dropping counts. Sale/resale category remains the booking category. This task does not add maintenance-status classification to the market report.

The authenticated API now identifies its population with `completion_basis=sales_resales_checkin_deals_v1`, and retains `dimension_basis=inventory_head_subhead_v1`. Periods return `completed_deals`, `sale_deals`, `resale_deals`, and `check_ins`. Breakdowns include `deal_kind` so sales, resales and check-ins remain separate in reports and equal numeric IDs across tables cannot collide. The portal validates component totals, period series and breakdowns. Its cache key changes with the population basis, so an old resale-only or resale-plus-check-in report cannot be advertised as the new combined report.

Both sources must return complete valid reports. Existing five-minute fresh cache, bounded stale cache, failure cooldown, public refresh budget, authenticated HTTPS transport, response limits and aggregate limits remain. No customer/tenant names, CNIC, mobile numbers, rents, financial balances or private record payloads are added to public responses.

## Public text

Active homepage market copy, chart accessible labels, hero/project monthly counters and illustrative story/viewer text use Deal/Deals instead of Transfer. Shared unavailable messages also use deal wording. The market report and CSV use deal labels and distinguish Sale, Resale and Check-in. The previously removed Price vs Last Year KPI stays removed. Stored ERP field names and business code are not renamed.

## Files and database impact

ERP API files:

- `D:/xampp/htdocs/sgcacc/smp-api/src/MarketIntelligence.php`
- `D:/xampp/htdocs/sgcacc/zalmi-smp-api/src/MarketIntelligence.php`

Portal files:

- `app/Services/ErpMarketIntelligenceService.php`
- `html/includes/home/common.php`
- `html/includes/home/hero-b.php`
- `html/includes/home/sections/market_band.php`
- `html/includes/home/sections/spotlight.php`
- `html/insights.php`

Read-only reconciliation SQL: `database/sql/market-deals-checkins-readonly.sql`. This is optional diagnostic SQL; importing it is not a deployment step. The API uses prepared, SELECT-only queries. No database migration, ERP record write, ownership/status mutation, credential/private configuration change, route change or SMS call was required.

## Validation

**122 targeted checks passed:** 53 per actual API copy against guarded disposable MySQL schemas, plus 16 homepage rendering checks. Coverage includes sale inclusion, sale_return exclusion, locked sale exclusion, NULL sale-type exclusion, separate sale/resale components, inventory grouping, all three company scopes, absent/ambiguous company names, cross-company inventory references, owner/tenant and locked historical check-ins, check-out exclusion, epoch dates, date/start-date precedence, DD-MM-YYYY and ISO fallback, invalid dates, future/out-of-window exclusion, midnight boundaries, database timezone independence, overlapping numeric IDs, category/project filters, combined totals/components, old-contract rejection and fresh cache reuse. Homepage rendering covers fresh/cached/stale/unavailable states, story viewer output, accessible labels and absence of transfer wording/removed price KPI. PHP syntax and diff whitespace checks passed. Existing homepage agency count regression also passed **20 checks**.

Read-only reconciliation against the existing local SGC snapshot matched independent sale-type-grouped booking SQL plus separate PHP date parsing: October 2026 **1 sale + 55 resales + 28 check-ins = 84 deals**, November to the check instant **0 sales + 9 resales + 9 check-ins = 18**, Today **0**. Of all historical check-ins in the inspected company scope, 209 used valid start-date fallback and 25 had no usable recorded date. These are local snapshot counts, not live results. No real ERP/portal records were changed.

QA runners: `.qa/market_inventory_checks.php`, `.qa/market_checkin_readonly.php`, `.qa/checkin_home_render.php`, `.qa/home_agency_count_checks.php`. Temporary schemas/cache are cleaned up. Test cache uses the workspace drive because the machine's C: temporary drive ran out of space during one initial run; reruns passed using the workspace drive.

## FTP rollout

Bundle: `D:/xampp/htdocs/shalimar-properties/deploy/live-market-deals-checkins`. Its README lists all eight source/destination files. Upload both API files first, then the portal service and five public PHP files. Deploy the complete set; the new portal deliberately rejects older report-population API contracts. Existing `.env`, API `catalog.local.php`, endpoint entrypoints and credentials must be preserved.

Live endpoints remain authenticated POST `/smp-api/v1/market-intelligence.php` on both ERP hosts. No new endpoint or cron setup is needed. The public website reads cached aggregates, not per-customer property APIs. This change has not been uploaded or verified from the live portal server.
