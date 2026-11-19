# Customer Booking Verify API — deployment package

This package adds the read-only endpoint to the existing CodeIgniter 4 application. It does not include `.env`, credentials, database data, or a migration.

## Deployment status — 19 November 2026

The controller and route have been uploaded to live and their remote file checksums verified. A POST without an Authorization header reached the live handler and returned HTTP 401 `unauthorized`, as expected. The live `.env` does not yet have `customerBookingApi.token`, so authenticated requests remain unauthorized until the site owner configures the server-to-server secret. Successful ERP booking verification has not yet been tested.

The live `Routes.php` backup is `/home/propertys_user11/www/app/Config/Routes.php.backup-20261119`.

## Files and exact destinations

| Package file / local source | Exact local path | Exact live destination / action |
| --- | --- | --- |
| `app/Controllers/CustomerBookingVerificationApi.php` | `F:\xampp\htdocs\shalimar-properties\app\Controllers\CustomerBookingVerificationApi.php` | `/home/propertys_user11/www/app/Controllers/CustomerBookingVerificationApi.php` |
| `app/Config/Routes.add.txt` | `F:\xampp\htdocs\shalimar-properties\deploy\customer-booking-verify-api\app\Config\Routes.add.txt` | Add its one route line to `/home/propertys_user11/www/app/Config/Routes.php` and local `F:\xampp\htdocs\shalimar-properties\app\Config\Routes.php` |
| `PATHS-AND-DEPLOY.md` | `F:\xampp\htdocs\shalimar-properties\deploy\customer-booking-verify-api\PATHS-AND-DEPLOY.md` | Reference only; do not upload as PHP |

The matching project guide is `F:\xampp\htdocs\shalimar-properties\CUSTOMER-BOOKING-VERIFY-API.md` locally. It is not required by the live application.

## Install

1. The controller and route are already on live; do not upload duplicate routes.
2. In the live `.env` at `/home/propertys_user11/www/.env`, add `customerBookingApi.token = 'YOUR_RANDOM_SECRET_AT_LEAST_32_CHARACTERS'`. Use a private random value, and configure the same value only in the trusted ERP server caller. Never upload `.env.example` in place of `.env` and never put the token in browser JavaScript.
3. Confirm the Portal's existing ERP source records and `customerPortal.bookingPaths` point to reachable current-booking endpoints for SGC and Zalmi. The API intentionally returns a verification error when the selected ERP cannot confirm the current booking.

No database migration is needed. The endpoint route is live under the existing `/public` base URL; configure the token before authenticated use.

## Request

`POST https://shalimarproperties.com/public/api/v1/customer-booking/verify`

```json
{
  "customer_id": "ERP_CUSTOMER_ID",
  "property_id": "ERP_PROPERTY_ID",
  "booking_id": "CURRENT_ERP_BOOKING_ID",
  "source": "SGC"
}
```

Headers: `Authorization: Bearer <server-to-server-token>` and `Content-Type: application/json`. `customer_id`, `property_id`, and `booking_id` are IDs from the ERP named by `source`. Refer to `CUSTOMER-BOOKING-VERIFY-API.md` for response fields and errors.

## Local URL

`POST http://localhost:8081/shalimar-properties/public/api/v1/customer-booking/verify`

If XAMPP maps the app's `public` directory differently, use that configured local base path. This is POST-only; opening the URL in a browser address bar does not perform a valid API request.

## Rollback

Restore `/home/propertys_user11/www/app/Config/Routes.php.backup-20261119` to `app/Config/Routes.php` and delete `app/Controllers/CustomerBookingVerificationApi.php` from the live app. Remove the API token from the live `.env` if configured. No listing data or schema is changed.
