# Agency Performance — Live Upload Guide

This guide deploys the public Agency Performance dashboard and its read-only SGC/Zalmi ERP endpoints. No database migration is required.

## Minimum files required for live data

For each ERP, the data endpoint requires these four files to be uploaded together:

1. `/smp-api/index.php`
2. `/smp-api/src/routes.php`
3. `/smp-api/src/AgencyPerformanceController.php`
4. `/smp-api/v1/agency-performance.php`

Also upload `/smp-api/src/HealthController.php` so build `2026-11-21.agency-performance.4` can be confirmed. On the Portal, all seven runtime files listed in section 3 are required for the page, route, navigation, mapped-agency lookup and secure ERP request.

## Upload order

1. Upload the SGC ERP API files.
2. Upload the Zalmi ERP API files.
3. Confirm both API health builds.
4. Upload the Shalimar Properties portal files.
5. Add the portal `.env` path setting and open the public page.

Deploying the ERP endpoints first prevents the new public page from requesting an endpoint that is not present yet.

## 1. SGC ERP live files

SGC local source root:

`F:\xampp\htdocs\sgcacc\html\smp-api`

SGC live API root is the `smp-api` folder in the `sgcacc.com` document root. Upload these runtime files:

| Local source | Live destination relative to the `sgcacc.com` document root |
| --- | --- |
| `F:\xampp\htdocs\sgcacc\html\smp-api\index.php` | `/smp-api/index.php` |
| `F:\xampp\htdocs\sgcacc\html\smp-api\src\routes.php` | `/smp-api/src/routes.php` |
| `F:\xampp\htdocs\sgcacc\html\smp-api\src\AgencyPerformanceController.php` | `/smp-api/src/AgencyPerformanceController.php` |
| `F:\xampp\htdocs\sgcacc\html\smp-api\src\HealthController.php` | `/smp-api/src/HealthController.php` |
| `F:\xampp\htdocs\sgcacc\html\smp-api\v1\agency-performance.php` | `/smp-api/v1/agency-performance.php` |

Do not overwrite or remove `/smp-api/src/catalog.local.php`. It contains the host-specific private database and bearer configuration. Do not create `/smp-api/smp-api/` nesting.

After upload:

- Health: `https://sgcacc.com/smp-api/v1/health.php`
- Expected build: `2026-11-21.agency-performance.4`
- Performance endpoint: `https://sgcacc.com/smp-api/v1/agency-performance.php`
- Opening the performance endpoint without parameters should return HTTP `200` with public aggregate-API usage. No bearer token is required. Individual booking/dealer detail is not exposed.

## 2. Zalmi ERP live files

Zalmi local source root:

`F:\xampp\htdocs\sgcacc\html\zalmi-smp-api`

The local folder is named `zalmi-smp-api`, but its live destination must still be the existing `smp-api` folder in the `zalmidevelopments.com` document root:

| Local source | Live destination relative to the `zalmidevelopments.com` document root |
| --- | --- |
| `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\index.php` | `/smp-api/index.php` |
| `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\src\routes.php` | `/smp-api/src/routes.php` |
| `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\src\AgencyPerformanceController.php` | `/smp-api/src/AgencyPerformanceController.php` |
| `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\src\HealthController.php` | `/smp-api/src/HealthController.php` |
| `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\v1\agency-performance.php` | `/smp-api/v1/agency-performance.php` |

Do not upload the local folder itself as `/zalmi-smp-api`. Upload its listed contents into Zalmi's existing `/smp-api/` folder. Preserve Zalmi's own `/smp-api/src/catalog.local.php`.

After upload:

- Health: `https://zalmidevelopments.com/smp-api/v1/health.php`
- Expected build: `2026-11-21.agency-performance.4`
- Performance endpoint: `https://zalmidevelopments.com/smp-api/v1/agency-performance.php`
- An unauthenticated performance request without parameters should return HTTP `200` with usage information.

## 3. Shalimar Properties portal files

Portal local root:

`F:\xampp\htdocs\shalimar-properties`

Known live application root:

`/home/propertys_user11/www`

Upload these files:

| Local source | Exact live destination |
| --- | --- |
| `F:\xampp\htdocs\shalimar-properties\html\agency-performance.php` | `/home/propertys_user11/www/html/agency-performance.php` |
| `F:\xampp\htdocs\shalimar-properties\html\.htaccess` | `/home/propertys_user11/www/html/.htaccess` |
| `F:\xampp\htdocs\shalimar-properties\html\includes\header.php` | `/home/propertys_user11/www/html/includes/header.php` |
| `F:\xampp\htdocs\shalimar-properties\app\Services\ErpAgencyPerformanceApi.php` | `/home/propertys_user11/www/app/Services/ErpAgencyPerformanceApi.php` |
| `F:\xampp\htdocs\shalimar-properties\app\Libraries\PortalCatalogStore.php` | `/home/propertys_user11/www/app/Libraries/PortalCatalogStore.php` |
| `F:\xampp\htdocs\shalimar-properties\app\Libraries\PublicWebsiteUrl.php` | `/home/propertys_user11/www/app/Libraries/PublicWebsiteUrl.php` |
| `F:\xampp\htdocs\shalimar-properties\app\Config\ErpConnections.php` | `/home/propertys_user11/www/app/Config/ErpConnections.php` |

The existing local changes in `html/insights.php` and the untracked file `a` are unrelated to this deployment and must not be uploaded for Agency Performance.

## 4. Portal `.env`

In the live portal `.env`, add this line if it is not already present:

```ini
erp.agencyPerformancePaths = '{"SGC":"/agency-performance.php","ZALMI":"/agency-performance.php"}'
```

Keep the existing values for `erp.allowedHosts`, `erp.credentialRefs`, bearer-token environment variables and `erp.publicAddressOverrides`; other ERP endpoints still use them. Agency Performance itself is public aggregate-only and does not send a bearer token.

Agency Performance does not depend on `SGC_CATALOG_TOKEN` or `ZALMI_CATALOG_TOKEN`; keep those values for the other protected ERP endpoints. On the current LAN, `sgcacc.com` resolves to a private split-DNS address, so the local portal still needs this verified public pin:

```ini
erp.publicAddressOverrides = '{"sgcacc.com":["115.186.161.20"]}'
```

The ERP Source base URLs remain:

- SGC: `https://sgcacc.com/smp-api/v1`
- Zalmi: `https://zalmidevelopments.com/smp-api/v1`

Do not append `agency-performance.php` to the ERP Source base URL. The configured relative path is added server-side.

## 5. Final checks

Open:

`https://shalimarproperties.com/agency-performance`

Verify:

1. Agencies → Agency Performance opens from the desktop and mobile menus.
2. All companies are selected on first load.
3. The company multi-select opens above surrounding content and is searchable.
4. Top 5, Top 10 and Top 15 appear as radio buttons; Top 10 is selected initially.
5. Reporting period defaults to This Month; quick presets and the custom From/To picker update the report.
6. Sales/Rentals filter updates the KPIs, charts and ranking table.
7. SGC and Zalmi show verified data tags when both public aggregate API calls succeed.
8. The browser receives only the rendered report; individual customer/booking records are not published.

If the page says an ERP source is unavailable, open its public performance endpoint directly and check the returned JSON, then verify the health build, endpoint upload, approved hostname and public DNS/address override. The dashboard deliberately pauses rankings when a selected ERP source fails, so it does not publish incomplete or misleading comparisons.

## Optional documentation files

These are documentation only and are not required for runtime:

- `F:\xampp\htdocs\sgcacc\html\smp-api\AGENCY-PERFORMANCE-API.md`
- `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\AGENCY-PERFORMANCE-API.md`
- `F:\xampp\htdocs\sgcacc\html\smp-api\README.md`
- `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\README.md`
