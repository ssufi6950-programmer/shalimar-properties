# Database-backed ERP Sources

> Head/Subhead snapshots, project mappings and public visibility are now implemented separately. See [ERP-VISIBILITY.md](ERP-VISIBILITY.md) for deployment, authentication and synchronization instructions.

## Open the module

The existing URL is preserved:

http://localhost:8081/shalimar-properties/html/admin.php?tab=sources

It redirects to the CI4 route at:

http://localhost:8081/shalimar-properties/public/admin/erp-sources

Sign in with a database-backed administrator account. First-time setup: `php spark admin:create-superuser`, then change the generated temporary password at login. The old configured demo credentials no longer authorize access. See [ADMIN-SECURITY.md](ADMIN-SECURITY.md) for the current authentication, granular permissions and protected legacy routing. The source records and connection configuration are preserved.

## Database and initialization

The local XAMPP MySQL-compatible server is configured through the private, ignored `.env`. The portal uses the new database **`shalimar_resale_portal`**, through CI4's **MySQLi** driver. Existing ERP and other application databases were not changed.

Executed successfully:

```text
php spark migrate
php spark db:seed ErpSourceSeeder
php spark migrate:status
```

The migration creates `erp_sources` with all requested fields, an unsigned integer primary key, a unique source-code index, timestamps and an InnoDB table. The seeder adds only missing `SGC` / **SGC ERP** and `ZALMI` / **Zalmi ERP** records. It never overwrites existing source configuration.

Both sources start active, with `Not Configured` connection state. Base URLs, credential references, last-checked and last-sync timestamps are empty. No health endpoint, API URL or real credentials have been invented. This module never changes `last_sync_at`.

## Routes and access

| Method | Route | Purpose |
| --- | --- | --- |
| GET / POST | `admin/login` | Administrator sign-in using existing configured credentials |
| POST | `admin/logout` | End the administrator session |
| GET | `admin/erp-sources` | Source cards and forms |
| GET | `admin/erp-sources/data` | Current records, totals and fresh CSRF token |
| GET | `admin/erp-sources/options` | Database-driven active `{id, name, code}` options for future modules |
| POST | `admin/erp-sources` | Create a source |
| GET | `admin/erp-sources/{id}` | Current source details and reference information |
| POST | `admin/erp-sources/{id}` | Edit configuration |
| POST | `admin/erp-sources/{id}/status` | Activate / deactivate |
| POST | `admin/erp-sources/{id}/test` | Test a configured, approved health endpoint |

All source routes require a current active database-backed administrator session and the corresponding granular `erp_sources.*` action permission. Credential changes additionally require `erp_sources.manage_credentials`. Customer demo state and browser storage cannot authorize ERP access. Role/account/password changes revoke affected sessions. Defaults are a 30-minute idle timeout and eight-hour absolute lifetime; see ADMIN-SECURITY.md for configuration.

CSRF uses CI4 session protection. AJAX writes send `X-CSRF-TOKEN`; JSON responses include the refreshed token. The ordinary logout form is updated when an AJAX request rotates it. Unauthorized API calls return 401, missing/invalid CSRF returns 403, invalid configuration returns 422 and referenced-code conflicts return 409. Responses are not cached. Configure secure cookies and HTTPS when deploying beyond the local XAMPP environment.

## Data and reference rules

- Source codes are normalized to uppercase and validated on the server. MySQL also enforces uniqueness.
- Names, URLs, auth type, credential reference and active state are the only client-editable configuration. Client-supplied connection state, IDs and timestamps cannot set health results or sync times.
- Referenced codes are locked in the editor and enforced server-side under a row lock. The model independently rejects referenced code changes and code changes without an explicit ID.
- Reference detection checks actual foreign keys and existing `erp_source_id` columns in the portal database. No projects, properties, customers, agents, agencies or synchronization tables are created here.
- The API exposes no DELETE route. The model refuses hard deletion. Deactivation preserves the source and references and excludes it from active options.
- Every future ERP-related table must store **`erp_source_id` referencing `erp_sources.id`**, with a matching unsigned integer type and `ON DELETE RESTRICT ON UPDATE RESTRICT`. Do not store source names/codes as relational identifiers. Reuse `ErpSourceModel::activeOptions()` or the authenticated options route for source dropdowns.

The editor may change an unreferenced code. Once a source is referenced, the current references prevent code changes. Future modules must use real foreign keys; this also prevents concurrent referenced-source deletion at database level.

## Direct PHP API URL format

ERP endpoint URLs now end in `.php`. The current health paths in the portal's private `.env` are:

```dotenv
erp.healthPaths = '{"SGC":"/health.php","ZALMI":"/health.php"}'
```

The source base URLs stay `https://sgcacc.com/smp-api/v1` and `https://zalmidevelopments.com/smp-api/v1`. The resulting URLs are `/smp-api/v1/health.php` on each host. Upload the direct PHP endpoint update to both ERPs before testing these new URLs. Previous connection results have been reset to Not Checked because they tested the old paths. The new SGC PHP file has passed local HTTP tests; no new-format live deployment/test is claimed. The portal's admin UI/CRUD routes are unchanged.

## Approving a real health endpoint

Connection testing deliberately remains disabled until actual integration information is supplied. A base URL entered in the editor does not by itself authorize outbound requests.

Server configuration is read by `app/Config/ErpConnections.php` from these private environment entries:

```dotenv
erp.allowedHosts = '[]'
erp.healthPaths = '{}'
erp.credentialRefs = '{}'
```

`allowedHosts` is a JSON array of exact, approved DNS hostnames. `healthPaths` is a JSON object mapping source codes to the actual provided relative health path, beginning with `/`. The path is appended to the configured base URL. No default health path is guessed.

`credentialRefs` maps an opaque reference ID to an authentication type and **environment-variable names**, not secret values:

| Type | Reference definition keys |
| --- | --- |
| `bearer` | `type`, `tokenEnv` |
| `basic` | `type`, `usernameEnv`, `passwordEnv` |
| `api_key` | `type`, `tokenEnv`, `header` |

Define the referenced secret values in the server environment or a deployment secret store. Only reference IDs/types appear in the editor; secret values and environment-variable names are never serialized to the frontend. No secrets are stored in `erp_sources`.

The reusable `ErpConnectionService`:

- Loads the selected source from MySQL and rejects inactive or incomplete configuration.
- Accepts HTTPS DNS URLs on port 443 only. It rejects embedded credentials, query strings, fragments, IP literals, local hostnames and ambiguous URL paths.
- Checks exact host approval, resolves DNS, rejects private/reserved/transition addresses and mixed safe/unsafe answers, then pins a validated public address into cURL while preserving TLS hostname verification.
- Disables proxies and redirects, verifies TLS certificates, applies connect/request timeouts and caps/discards response bodies. Response bodies and credential headers are never returned to JavaScript.
- Records actual test success/failure and the check timestamp, without touching sync timestamps. A concurrent configuration change prevents an outdated test result from overwriting the changed source.

Private/LAN ERP endpoints are not permitted by the default network policy. A vetted integration path would need to be designed explicitly if the real ERP endpoints require internal network access.

### Public address override for split DNS

If a local/office DNS server maps an approved public ERP hostname to a private address, a deployment administrator can configure a verified **public** destination in the portal's private `.env`:

```dotenv
erp.publicAddressOverrides = '{"sgcacc.com":["115.186.161.20"]}'
```

The SGC address above was independently checked against Google and Cloudflare public DNS on 2026-11-04; local DNS returned `10.100.100.4`. The override applies only to that exact approved hostname. It is not a localhost/private-network exception and is not editable from ERP forms or AJAX input. The source base URL stays `https://sgcacc.com/smp-api/v1`; SGC's HTTP hostname, TLS SNI and certificate verification remain `sgcacc.com`.

Only non-empty lists of valid public addresses are accepted. Private, reserved, mixed or malformed override values fail closed, without DNS fallback or an HTTP request. Host approval, configured health paths, credentials, disabled redirects, response limits and timeouts still apply. Other hosts continue through the existing DNS checks. Overrides default to empty; remove the entry when DNS is corrected. If hosting changes, independently verify and update the public address; never guess it or disable TLS verification to accommodate a stale address.

This resolves only the DNS destination problem. The connection result still reflects the actual live health response: HTTP errors, including 500, remain Failed.

After applying this setting, an authenticated portal Test Connection on 2026-11-04 at 15:09 Asia/Karachi reached the live SGC endpoint and reported **HTTP 500**, replacing the private-DNS rejection. SGC remains Failed; no successful health check or synchronization is claimed. The isolated MySQL/service checks pass, including public override selection and rejection of private/malformed overrides. The live API's Apache/PHP error log is required to diagnose its remaining server error.

## Verification performed

Current regression commands after database authentication/RBAC:

```text
python -u .qa/verify_admin_security.py
python -u .qa/verify_admin_security_browser.py
php .qa/erp_mysql_checks.php
```

The original `verify_erp_http.py` / `verify_erp_browser.py` are historical pre-RBAC checks that assume the old configured login and initially empty source configuration; use the current suites above. Their reusable HTTP/CDP helpers remain available to the new suites.

The current HTTP suite creates strong temporary database administrator fixtures, checks real sign-in and granular authorization, exercises MySQL ERP CRUD and removes only its own QA records. The service suite continues to test DNS/URL restrictions, credential isolation, reference locks and delete protection. No old demo login is used by the current suites.

The MySQL/service suite creates an isolated, randomly named test database, runs the migration, verifies reference locks and foreign-key restrictions, tests private/mapped/mixed DNS, redirects, timeouts, and secret handling, and drops its own isolated test database afterward. Health response tests use a deterministic transport: **no real ERP connection has been tested because no endpoint is configured**.

The browser suite exercises the real AJAX forms, source details, create/edit, activate/deactivate confirmations, reload persistence, source-name escaping, desktop/mobile layout, navigation/theme and CSRF-protected logout. It removes only its own unreferenced test source. Screenshots remain under `.qa/erp-*.png`.

## Created files

- `app/Config/ErpConnections.php`
- `app/Controllers/Admin/Auth.php`
- `app/Controllers/Admin/ErpSources.php`
- `app/Database/Migrations/2026-11-04-000001_CreateErpSources.php`
- `app/Database/Seeds/ErpSourceSeeder.php`
- `app/Filters/AdminAuth.php`
- `app/Helpers/admin_helper.php`
- `app/Libraries/AdminIdentity.php`
- `app/Models/ErpSourceModel.php`
- `app/Services/ErpConnectionService.php`
- `app/Views/admin/auth/login.php`
- `app/Views/admin/erp/sources.php`
- `app/Views/admin/layouts/main.php`
- `app/Views/admin/layouts/header.php`
- `html/includes/admin/navigation.php`
- `public/assets/admin/css/erp-sources.css`
- `public/assets/admin/js/shell.js`
- `public/assets/admin/js/erp-sources.js`
- `.qa/verify_erp_http.py`
- `.qa/erp_mysql_checks.php`
- `.qa/verify_erp_browser.py`
- `ERP-SOURCES.md`

## Modified files

- `.env`: local MySQL connection, server-side session/CSRF settings and empty ERP approvals.
- `.gitignore`: ignore private environment configuration and ERP browser-test profiles/logs.
- `app/Config/Routes.php`: explicit protected ERP Sources and administrator sign-in routes.
- `html/admin.php`: route only the existing `tab=sources` entry to CI4.
- `html/includes/admin/data.php`: extract shared navigation metadata without changing other prototype records.
- `html/assets/admin/README.md`: note the production-backed Sources exception to the prototype.

Generated verification artifacts are `.qa/erp-sources-desktop.png`, `.qa/erp-sources-mobile.png`, the ignored `.qa/erp-edge.log` / `.qa/erp-edge-*/` browser profiles, and private CI4 session/cache/log files under `writable/`. Test sources and randomly named test databases are removed by the verification scripts; the portal database retains only the two initial ERP sources and its migration record.

No ERP customer APIs, property synchronization, project mappings or other ERP modules are implemented in this phase.

Authentication and user/role/audit file changes made after the original ERP Sources phase are inventoried in ADMIN-SECURITY.md. The file inventory below records the original ERP Sources implementation.
