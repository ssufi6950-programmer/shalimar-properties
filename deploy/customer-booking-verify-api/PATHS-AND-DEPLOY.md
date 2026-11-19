# Exact local and live paths

Deployment root on live: `/home/propertys_user11/www/`.

The controller and route are deployed. A POST without a bearer token reached the handler and returned the expected HTTP 401. Live token configuration and end-to-end ERP verification remain pending.

| Item | Local path | Live path / operation |
| --- | --- | --- |
| API controller (package copy) | `F:\xampp\htdocs\shalimar-properties\deploy\customer-booking-verify-api\app\Controllers\CustomerBookingVerificationApi.php` | Upload to `/home/propertys_user11/www/app/Controllers/CustomerBookingVerificationApi.php` |
| API controller (working source) | `F:\xampp\htdocs\shalimar-properties\app\Controllers\CustomerBookingVerificationApi.php` | Same live destination above |
| Route snippet (reference) | `F:\xampp\htdocs\shalimar-properties\deploy\customer-booking-verify-api\app\Config\Routes.add.txt` | Its route line is already present in `/home/propertys_user11/www/app/Config/Routes.php` |
| Local route file | `F:\xampp\htdocs\shalimar-properties\app\Config\Routes.php` | Route line is already present; do not add it a second time |
| Live environment config | `F:\xampp\htdocs\shalimar-properties\.env` | `/home/propertys_user11/www/.env`; add the private `customerBookingApi.token` entry without replacing other settings |
| Token template | `F:\xampp\htdocs\shalimar-properties\.env.example` | Do not upload this file as the live `.env` |
| Main API documentation | `F:\xampp\htdocs\shalimar-properties\CUSTOMER-BOOKING-VERIFY-API.md` | Documentation only; not needed by PHP runtime |

Live route backup: `/home/propertys_user11/www/app/Config/Routes.php.backup-20261119`.

Local endpoint: `http://localhost:8081/shalimar-properties/public/api/v1/customer-booking/verify`.

Live endpoint: `https://shalimarproperties.com/public/api/v1/customer-booking/verify`.

The exact live app and `.env` locations above follow the currently supplied hosting path. If hosting moved the CI app, confirm the account document root before uploading.
