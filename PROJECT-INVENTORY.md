# Project Inventory Dashboard

## Feature status

The project inventory feature is add-only and disabled by default. It reads current ERP inventory through authenticated, read-only API requests. It does not use portal listings as a proxy for inventory, does not write ERP data, and requires no SQL migration or manual SQL.

Enable it in the SMP `.env` only after the endpoint files are installed on both ERP sources:

```ini
projectInventory.enabled = true
```

The feature stays disabled when this setting is absent or false.

## Local paths

### SMP Portal

Repository root: `F:\xampp\htdocs\shalimar-properties`

- `app\Config\ErpConnections.php`
- `app\Config\Routes.php`
- `app\Controllers\ProjectInventory.php`
- `app\Services\ProjectInventoryService.php`
- `app\Services\ProjectInventoryTransport.php`
- `app\Views\public\project_inventory.php`
- `html\projects.php`
- `html\public\assets\projects\projects-json-preview.js`
- `html\public\assets\inventory\project-inventory.js`

### SGC ERP API

- `F:\xampp\htdocs\sgcacc\html\smp-api\index.php`
- `F:\xampp\htdocs\sgcacc\html\smp-api\src\routes.php`
- `F:\xampp\htdocs\sgcacc\html\smp-api\src\ProjectInventoryController.php`

### Zalmi ERP API

- `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\index.php`
- `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\src\routes.php`
- `F:\xampp\htdocs\sgcacc\html\zalmi-smp-api\src\ProjectInventoryController.php`

The endpoint URL on each source is its configured ERP API base URL plus `/project-inventory.php` (for example, `.../smp-api/v1/project-inventory.php`). It uses the already configured source-specific bearer credential and company profile; no browser token or new secret is needed.

## Live paths

Upload each package file to the same relative path under the live SMP application root and the matching SGC/Zalmi API directory. The deploy destination depends on the live directory layout; this repository does not contain the hosting account’s absolute filesystem path. Do not upload one ERP API controller into the other ERP folder.

After upload, keep the feature flag off until both endpoints are installed and the ERP source records have active HTTPS base URLs, approved hostnames, and server-side bearer credentials. Then set `projectInventory.enabled = true` in SMP’s server `.env` and clear the application/page cache if the host uses one.

## Data and status rules

- Only projects visible through active ERP source, Head, Subhead, and published project mappings appear.
- SGC and Zalmi are fetched and counted separately, with company identity included in each source section. ERP IDs are never merged across sources.
- The ERP API accepts only the exact mapped Head/Subhead scope supplied by SMP and resolves the source company from that ERP’s configured market profile.
- A unit is **Available** only when `pro_inventory.status` is exactly `available` and it has no unlocked booking candidates.
- A unit is **Booked** only when `pro_inventory.status` is exactly `booked` and it has exactly one unlocked booking accepted by the existing `CurrentBookingRules` (`cancel=no`, sale type `sale` or `resale`, and property status `booked`, `resale`, `ownership`, or `complete`).
- Status/booking conflicts, ambiguous bookings, and all other inventory statuses are **Other / unknown**. No reserved/hold meaning is inferred; those units remain grey until that status is verified in ERP business rules.
- Property numbers use `property_no`, then `plot_no`, then the file series and file number. Size labels use ERP unit metadata when it is available.
- Inventory map tiles are schematic and have no geographic or site-plan coordinates. Customer names, contact details, and booking details are never returned to the portal.

## API request shape

The ERP endpoint is authenticated and accepts JSON by POST. SMP supplies only server-verified mappings, such as:

```json
{
  "scopes": [{"head_id": "49", "subhead_ids": ["555"]}],
  "after_id": "0",
  "page_size": 500
}
```

The API derives `company_id` from its own ERP configuration. It returns at most 500 inventory rows per page, without owner identity or booking identifiers. Do not call the ERP API directly from browser JavaScript.

## Pages

- Projects page: each rendered card gets **Available Units** only when the feature flag is enabled.
- Overview: `project-inventory/{project-slug}`
- Schematic map: `project-inventory/{project-slug}/map`
- Read-only JSON projection: `api/project-inventory/{project-slug}`

The map supports subhead, sector, type, size, status highlighting, property-number search, page controls, zoom, fullscreen, and a details modal. The data API is fetched on page load and by the manual Refresh button; there is no polling.

## Verification performed

- PHP syntax checks passed for the new SMP PHP files, route/config changes, and both ERP controllers and API entrypoints.
- SQL, migrations, live API requests, and deployment were not run.
- JavaScript syntax/browser checks were unavailable in this environment because Node.js and a browser test runner are not installed.
- No database-backed inventory counts or screenshots are claimed as verified locally.
