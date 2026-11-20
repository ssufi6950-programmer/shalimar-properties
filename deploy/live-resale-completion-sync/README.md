# ERP resale completion → Shalimar Properties

This package connects the existing SGC and Zalmi resale-save handlers to an authenticated SMP callback. It records the previous public listing as **Sold** when the exact source agency and dealer IDs are unchanged, or **Superseded** (unlisted) when they differ. The selected new ERP agency/dealer IDs and event time are retained in the listing record and lifecycle event.

## Files and live destinations

| Local source | Live destination |
| --- | --- |
| `D:\xampp\htdocs\shalimar-properties\app\Config\Routes.php` | `/home/propertys_user11/www/app/Config/Routes.php` |
| `D:\xampp\htdocs\shalimar-properties\app\Controllers\CustomerResaleCompletionApi.php` | `/home/propertys_user11/www/app/Controllers/CustomerResaleCompletionApi.php` |
| `D:\xampp\htdocs\sgcacc\admin\property\property-resale-smp.php` | `/home/sgc/www/html/admin/property/property-resale-smp.php` |
| `D:\xampp\htdocs\sgcacc\admin\property\property-resale-zalmi.php` | `/home/zalmipk_user/www/html/admin/property/property-resale-zalmi.php` |

Upload by merging/replacing each exact matching path. Back up the live copy first. Do not upload the whole ERP project for this change.

## Configuration

On each ERP server, configure `SMP_CUSTOMER_RESALE_CALLBACK_URL` to:

```text
https://shalimarproperties.com/public/api/v1/customer-booking/resale-completed
```

Local testing may use `http://localhost/shalimar-properties/public/api/v1/customer-booking/resale-completed`. The callback reuses the already configured `SMP_CUSTOMER_BOOKING_VERIFY_TOKEN`; do not copy a token into source code. If the callback URL is missing or invalid, the ERP resale still completes and writes a safe error to the ERP PHP error log.

## Behavior and limits

- Callback runs only after the ERP resale transaction commits and the new seller-side `dealer_log` is available.
- The callback identifies the old portal listing by ERP source, property ID, old booking ID and the saved ownership proof, including co-owner submissions.
- Exact agency ID and dealer ledger ID match → prior SMP listing becomes `sold` / `sold`.
- Any difference or missing new representative IDs → prior listing becomes `unlisted`, with `resale_outcome=superseded`.
- SMP stores the timestamp, source, old/new booking IDs, selected agency/dealer IDs and a lifecycle event. Repeated callbacks are idempotent.
- A callback failure never rolls back or blocks the ERP resale. There is no automatic retry/outbox in this stage; the planned cron/retry stage is still required for reliable recovery after a callback outage.
- No SQL or migration is required. No live upload was performed by this change.
