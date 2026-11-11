# ERP Market Intelligence — current transfer-date definition

Updated 11 November 2026. This supersedes the previous approval-history definition in `ERP-MARKET-INTELLIGENCE.md` and older deployment packages.

## Qualification

Within each source's configured reporting company, count each booking once when:

```sql
sale_type = 'resale' AND locked = 'no'
```

Date-wise grouping uses **`pro_payment_booking.transfer_date`**, an integer Unix timestamp, converted to Asia/Karachi. Creation and approval timestamps are not used. Approval, cancellation, return and processing states add no restrictions. Locked bookings are excluded, including formerly unlocked bookings that later become locked; these are current qualifying bookings grouped by transfer date.

The existing company scope remains: resolve the exact company name independently for each source, default `Shalimar Sales & Marketing`, overridable through private `SMP_API_MARKET_COMPANY_NAME`. No company ID is hardcoded. Head/Subhead active flags and Portal listing publication do not determine counts.

## Query and periods

```sql
SELECT DATE(DATE_ADD(CAST('1970-01-01 00:00:00' AS DATETIME),
           INTERVAL (b.transfer_date + 18000) SECOND)) AS day,
       b.head AS head_id, b.sub_head AS subhead_id,
       b.property_catagory AS category, COUNT(DISTINCT b.id) AS deals
FROM pro_payment_booking b
WHERE b.company_id = ?
  AND BINARY b.sale_type = BINARY 'resale'
  AND BINARY b.locked = BINARY 'no'
  AND b.transfer_date > 0
  AND b.transfer_date >= ? AND b.transfer_date < ?
  AND b.transfer_date <= ?
GROUP BY day, b.head, b.sub_head, b.property_catagory
ORDER BY day, b.head, b.sub_head, b.property_catagory
LIMIT 50001;
```

Bound parameters are company ID, inclusive start, exclusive end and report instant. Explicit epoch arithmetic is independent of SQL session timezone. The report requires only companies, bookings and Head/Subhead labels; approval/return tables are not queried.

Today starts at Pakistan midnight. Last Month is the previous complete calendar month. This Month runs from the current month's start through the report instant. The daily chart contains 30 local dates including today, with genuine zero days. The monthly chart contains 12 calendar months. Null/zero/invalid transfer dates cannot be assigned to date-wise totals; future timestamps wait until reached. Dates are never inferred or backfilled.

## API and cache

Both APIs retain authenticated POST `/smp-api/v1/market-intelligence.php`. The new response basis is `resale_transfer_date_v1`. Numeric response keys named `completed_resales` remain for compatibility; their meaning is now identified by the new basis. The Portal rejects the old approval-based basis and versions its cache key so old totals cannot be reused.

Both configured sources must succeed for a new combined report. No partial totals are advertised as complete. Existing five-minute fresh/thirty-minute maximum stale cache, explicit stale timestamp, sixty-second failure cooldown and refresh limits remain. HTTPS, bearer references, host/DNS restrictions, disabled redirects, timeouts and response limits remain enabled.

Price comparison remains N/A: no actual transaction-price history was added. Booking balances, fees and commissions are not sale prices. Historical size/sector/block snapshots remain unsupported. Customer ownership, OTP, listings and approval workflows are unchanged.

## Files and verification

Six application files changed: the SGC and Zalmi API `src/MarketIntelligence.php` files, Portal `ErpMarketIntelligenceService.php`, `ErpMarketApi.php`, `html/includes/home/sections/market_band.php` and `html/insights.php`.

125 checks passed: 38 service/cache/privacy checks and 87 real API/HTTP/browser checks. Both actual API copies run against isolated disposable MySQL, including tests with approval/return tables absent, date-only movements and locked/type filtering. Edge rendering passed at 320, 360, 390, 430, 768, 1024 and 1440 px. Screenshots contain synthetic values. All six files pass PHP 8.2 syntax checks; PHP 7.4 API syntax was retained, but that runtime was unavailable for execution.

Read-only local SGC reconciliation: Today 0, October 2026 46, November 2026 0 at the check instant, matching independent direct SQL. No undated qualifying rows found. ERP checksums unchanged. These are local snapshot values, not live totals.

Upload [deploy/live-market-transfer-date.zip](deploy/live-market-transfer-date.zip). Its [README](deploy/live-market-transfer-date/README.md) lists full local/live paths and upload order; its [REPORT](deploy/live-market-transfer-date/REPORT.md) contains test details. Existing market endpoint entry points/routes and source profiles must already be installed. No production migration or ERP business-data update is required. The change has not been uploaded or tested live.
