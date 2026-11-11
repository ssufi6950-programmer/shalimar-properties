# Admin real data integration

Implemented locally on 5 November 2026. Customers, Properties, Resale Listings, Dashboard and Portfolio Analytics now read saved portal database records. Existing admin layout, sidebar, header, theme, authentication and permissions remain in use.

## Modules and routes

| Module | Routes | Behavior |
| --- | --- | --- |
| Customers | `GET /admin/customers`, `/admin/customers/{id}` | One portal customer per row; verified ERP links, registered mobile, account status, registration date and workflow listing count. |
| Properties | `GET /admin/properties`, `/admin/properties/{id}` | Only locally saved properties associated with customer resale workflow records. Shows source, project, Head/Subhead, unit, floor, size/type, customer, booking reference and saved publication verification. |
| Resale Listings | `GET /admin/resale`, `/admin/resale/{id}` | Saved customer listings with price, selling method, historical representative, public contact, status, publication date and relationship links. |
| Unpublish | `POST /admin/resale/{id}/unpublish` | Existing `unlisted` status; available active listings only, with RBAC, CSRF and atomic audit logging. |
| Dashboard | Existing `/admin/dashboard` | Actual module counts, recent listings, twelve months of listing creation activity, active listings by source and project totals. |
| Analytics | Existing `/admin/workspace/analytics` | Actual local portfolio aggregates, subject to module permissions. |

The old `/admin/workspace/customers`, `/properties` and `/resale` routes redirect to the real modules while preserving filters. Old detail routes require a real numeric `id`; they no longer display a default sample record.

## Database relationships and scope

- `portal_customers`: customer account, encrypted mobile, keyed mobile search hash, status and registration date.
- `customer_erp_links` and `erp_sources`: multiple verified ERP identities for one portal customer. Source filtering uses an existence query so SGC/Zalmi links do not duplicate customers.
- `portal_owned_properties`: locally saved property records. Joined to resale workflow records through **both** `erp_source_id` and `erp_property_id`; identical ERP property IDs in different sources remain separate.
- `portal_listings`: saved resale records and their existing payload, status, deal state, customer/property identifiers and publication proof. Customer workflow scope requires customer, source and ERP property references.
- `portal_projects`, `erp_heads`, `erp_subheads`: saved project and source-specific catalog labels.
- `shalimar_agents`, `authorized_agencies`: representative references. Saved snapshots/public poster details take precedence over subsequently renamed directory records.
- `erp_project_mappings` and the existing `ErpVisibility` rules: determine whether a public listing link may be offered. ERP visibility status is read locally.
- Existing admin users, roles, permissions and audit tables continue through the existing admin services.

Admin browsing does not call ERP property APIs, send SMS or import ERP properties. Live My Properties results do not populate Admin Properties. No ERP database changes or API uploads are part of this task.

## Search, filters and performance

- All three collections use server-side pagination: 10, 25, 50 or 100 rows, default 25.
- Customers: name/ID or exact full-mobile search, account status and ERP source.
- Properties: saved property/reference/customer search, ERP source, project and related customer.
- Resale: listing/property/customer/representative search, ERP source, project, stored status, selling method, related customer and local property.
- Query builder parameters, bounded input validation and escaped output protect search and presentation.
- ERP identities and listing counts are fetched in batches per customer page. Public visibility is fetched once per listing page. Table permission checks are evaluated once per permission, rather than per row.
- Empty results show **No records found.** Database collection/dashboard failures display an unavailable response instead of fabricated zero totals.
- Dashboard aggregates and linked navigation respect existing module permissions.

## Status and data integrity

No approval workflow was introduced. Existing OTP verification, preview, final ownership verification and direct publishing code remains unchanged.

Unpublish uses the existing `unlisted` status and preserves property/customer IDs, ownership records, representative assignments and publication history. Sold, cancelled and other terminal records cannot be changed through this action. Repeated Unpublish requests do not create duplicate audit events. A failed audit rolls back the listing change. Malformed listing JSON is rejected before mutation.

The public link uses the same local mapping/project/Head/Subhead/status/deal predicates as the existing public catalog. An active listing count describes active available saved listings; public eligibility can additionally depend on project and ERP visibility.

## Truthful unavailable fields

- CNIC is stored as a keyed hash, so its original or last digits cannot be recovered. The screen says **Protected — hash only** and exposes neither full CNIC nor its hash.
- The current customer schema does not record last login. The screen says **Not recorded**; login/OTP code was not changed to manufacture a value.
- The local property table has no creation timestamp. The screen explicitly labels the earliest associated listing creation date **First resale record**.
- Ownership evidence is labeled **Verified at publication**, when saved proof exists. It does not claim a new live ownership check.

## Existing seed records and current local counts

Read-only inspection found **37 legacy listing payloads matching `html/data/listings.json.php`**, without customer/source/property workflow linkage. They remain in the database and are reported separately on Resale Listings; they are excluded from customer workflow lists and dashboard totals. The existing classic module remains available under its existing permissions.

Final local smoke check:

| Measure | Count |
| --- | ---: |
| Portal customers | 1 |
| Verified customer ERP links | 2 |
| Locally saved resale properties | 2 |
| Customer workflow listings | 2 |
| Active available listings | 1 |
| Unpublished listings | 0 |
| Sold listings | 0 |
| Retained legacy listings | 37 |

The other workflow listing has its existing `pending` status. This task did not approve or change it, and added no approval UI. Counts will change with actual portal activity.

## Dummy sources bypassed

These modules and dashboard no longer render prototype arrays, sample JSON rows, fake metrics or AdminDemo table behavior. Shared prototype resources under `html/includes/admin/` remain for unrelated workspace modules; deleting shared resources would affect modules outside this task. No tables were truncated and no existing customer/property/listing records were deleted.

## Database migration

`app/Database/Migrations/2026-11-05-000006_AddAdminRecordIndexes.php` was applied successfully to the local portal database. It adds only nonunique read indexes:

- `portal_listings (erp_source_id, erp_property_id, id)`
- `portal_listings (portal_customer_id, id)`
- `portal_listings (project_slug, status, id)`
- `portal_customers (status, id)`

When deploying the changed portal files to another installation, run its normal CodeIgniter migration process (`php spark migrate`). No ERP migration is needed.

## Application files

New:

- `app/Services/AdminPortalData.php`
- `app/Controllers/Admin/PortalRecords.php`
- `app/Helpers/admin_records_helper.php`
- `app/Views/admin/records/index.php`
- `app/Views/admin/records/table.php`
- `app/Views/admin/records/details.php`
- `app/Views/admin/records/dashboard.php`
- `app/Views/admin/records/error.php`
- `public/assets/admin/js/portal-records-dashboard.js`
- `app/Database/Migrations/2026-11-05-000006_AddAdminRecordIndexes.php`

Updated:

- `app/Config/Routes.php`
- `app/Controllers/Admin/Workspace.php`
- `app/Filters/AdminPermission.php`
- `app/Helpers/admin_helper.php`

## Verification

All listed checks passed. Mutation and browser tests use disposable local test schemas, isolated sessions and synthetic records.

| Check | Result |
| --- | --- |
| `.qa/admin_portal_data_checks.php` | Passed: joins/source identity, deduplication, filters, pagination, private fields, bounded query count, historical assignments, local visibility, real aggregates, audit rollback, idempotent Unpublish and terminal-state protection. |
| `.qa/verify_admin_portal_http.py` | Passed: 51 HTTP/browser/security checks, including empty states, direct detail permissions, CSRF, escaped input, filters, real counts, desktop/mobile layout and dark theme. No uncaught browser errors. |
| `.qa/verify_shalimar_agents_http.py` | Passed: existing login OTP, current-booking OTP, preview, final verification and direct publishing; both published properties/listings appear in the real admin screens with correct counts. No live ERP/SMS requests. |
| `.qa/run_visibility_http.py catalog` | Passed: real public URLs hide/restore under saved visibility flags; sold listings remain hidden; mapping UI remains usable. |
| `.qa/run_visibility_http.py` | Passed: existing authentication/RBAC, admin modules, ERP Sources/classic workflows, audit export, session expiration, logout and login throttling. |
| `.qa/check_admin_portal_data.php` | Passed: actual local read-only counts and detail lookup; protected customer/OTP/preview/publication file hashes unchanged. |
| PHP lint and JavaScript syntax check | Passed for changed admin PHP files and dashboard JavaScript. |

Test support files include `.qa/admin_portal_fixtures.php`, `.qa/admin_portal_http_fixture.php`, the checks above and the protected workflow hash baseline. Existing publication regression assertions were extended to check the real admin records. The catalog test launcher had its redundant `intl` extension override removed because the local PHP installation already loads it.

Logs: `.qa/admin-records-http-checks.log`, `.qa/admin-records-publication-regression.log`, `.qa/admin-records-public-catalog-regression.log`, `.qa/admin-records-rbac-regression.log`.

Desktop/mobile screenshots: `.qa/admin-real-{dashboard,customers,properties,resale}-{desktop,mobile}.png`.

No blocking issues were found in the verified local implementation. The unavailable metadata and retained legacy records described above are intentional, visible limitations. Deployment to a live portal has not been performed.
