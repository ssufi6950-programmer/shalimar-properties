# Market reporting: inventory Head/Subhead

Implemented locally on 11 November 2026. Supersedes the Head/Subhead selection in the earlier transfer-date report. Live upload is still required.

Both SGC and Zalmi market adapters now take project dimensions exclusively from `pro_inventory.head` and `pro_inventory.sub_head`, joined on `i.id=b.property_id AND i.company_id=b.company_id`. They no longer read the booking's Head/Subhead columns. Each qualifying booking remains one deal, using the existing company scope, `sale_type='resale'`, `locked='no'` and booking `transfer_date` rules. Category remains the existing booking category; ownership and customer property APIs are unchanged.

A LEFT JOIN preserves qualifying deals whose inventory is missing or belongs to another company. These receive an explicit Unassigned ERP project/segment reporting bucket, never a fallback to the booking's stale dimensions. The portal accepts the canonical `0` identifier only in reporting dimensions and filters; mapping and public listing eligibility rules remain unchanged.

The API includes `dimension_basis=inventory_head_subhead_v1`. The portal requires it and versions its cache key with it, preventing old booking-dimension reports/caches from masquerading as inventory-based reports. Upload both API files before the portal file. Existing private company/database/token settings must be preserved.

## Files

- Portal: `app/Services/ErpMarketIntelligenceService.php`
- SGC: `D:/xampp/htdocs/sgcacc/smp-api/src/MarketIntelligence.php`
- Zalmi: `D:/xampp/htdocs/sgcacc/zalmi-smp-api/src/MarketIntelligence.php`

No database migration, ERP business-record update, credential change, public UI change or OTP/SMS call is required. Price comparison remains N/A because verified transaction-price history is unavailable.

## Verification

54 checks passed against both actual API copies and the portal service using guarded disposable local MySQL schemas. Tests cover missing booking dimension columns, inventory grouping, company isolation, missing inventory, one count per booking, locked/non-resale/future/zero/out-of-window exclusion, calendar periods, project/category filters, unassigned filters, combined totals, cache reuse, rejection of the old dimension basis, malformed IDs and inconsistent totals. Disposable schemas/cache files are cleaned up. Runner: `.qa/market_inventory_checks.php`.

Read-only local SGC reconciliation also passed: the new report's Today/Last Month/This Month counts matched independent booking-only SQL, and its complete response passed portal validation. This was a local snapshot check, not a claim about live counts. Existing ERP records were not modified.

PHP syntax and Git diff whitespace checks passed. New code has not been deployed or verified from the live portal server. A downloaded local checkout without `vendor`/valid database credentials is a separate deployment issue.

## FTP bundle

Folder: `D:/xampp/htdocs/shalimar-properties/deploy/live-market-inventory-fix`.

Upload in this order:

| Local bundle file | Live destination |
| --- | --- |
| `sgc-api/src/MarketIntelligence.php` | SGC website's existing `smp-api/src/MarketIntelligence.php` (use the folder already serving SGC; its absolute server root is unconfirmed) |
| `zalmi-api/src/MarketIntelligence.php` | `/home/zalmipk_user/www/html/smp-api/src/MarketIntelligence.php` |
| `portal/app/Services/ErpMarketIntelligenceService.php` | `/home/propertys_user11/www/app/Services/ErpMarketIntelligenceService.php` |

The bundle omits all `.env` and `catalog.local.php` files. Retain the working Zalmi company setting. Reload the website after uploading all three files; a failure retry may take up to 60 seconds. No manual cache deletion or database edit is needed. If the report remains unavailable, check the current authenticated API responses/logs rather than replacing private configuration.
