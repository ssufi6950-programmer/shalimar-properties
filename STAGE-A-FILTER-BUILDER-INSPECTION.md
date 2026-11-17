> Implementation update: the user confirmed manual schema creation. Stage A is now implemented locally; see [STAGE-A-FILTER-BUILDER.md](STAGE-A-FILTER-BUILDER.md). The inspection/checkpoint below is historical. No live deployment was performed.

# Stage A — Filter Builder inspection and database checkpoint

Status: inspection and proposed manual SQL only. The user's database STOP condition applies. No Stage A application/API implementation, migration, SQL execution or deployment has occurred.

## Final user decisions — authoritative scope

- Smart City Sector discovery reads ALL distinct valid `pro_inventory.sector` values under the Head dynamically resolved from its existing project/source mapping (currently local Head 49). It must span ALL Subheads under that Head; it must not be restricted to mapped Subhead 555. No application constant for Head 49 is permitted.
- Gulberg and Zalmi retain their verified project-specific Sub Location/Subhead mapping scope.
- Exact source values remain unchanged. The user will correct the trailing-space `Business District ` duplicate in ERP; a later successful explicit fetch/refresh reads corrected current values. No silent normalization/merge or deletion of previously saved filter rows occurs.
- NULL and whitespace-only values are excluded; actual sector `0` is retained.
- Fetches return filter metadata only. No inventory import/publication, existing mapping change, public eligibility change, Layer 1/public-page change or legacy Explorer JSON rewrite is authorized in Stage A.
- Final SQL review uses `STORED` generated index keys and ordinary-column InnoDB foreign-key indexes. The source hash explicitly hashes binary source bytes. CHECK expressions now reject whitespace-only values, including tabs/newlines, instead of relying only on SQL TRIM of spaces. No depth limit is introduced.
- Dependencies remain the inspected InnoDB parent definitions and collation. No SQL execution has been performed; review is against the MariaDB 10.4.32 target, not a successful server DDL test.

## Scope distinction

The existing `PropertyExplorer` implementation stores Layer 2 display overrides in `portal_projects.payload._property_explorer` and discovers public options from listings. It does not implement explicit ERP option fetching, selection or a persistent relational filter tree. That earlier JSON solution remains unchanged.

The new request adds an explicitly selected filter directory with parent references, stable duplicate prevention and future parent-child integrity. One dedicated portal table is proposed for these requirements. This is a new requirement, not a claim that the earlier JSON implementation requires a table. Neither the old JSON nor public pages will be automatically read from or migrated into the new table during Stage A.

## Read-only findings

### Actual Sub Location relationship

SGC `admin/property/property-booking.php` labels the control **Sub Location** and obtains its values using `item_sublocation WHERE location_id=<selected location>`. Its option values are `item_sublocation.id`. Filtering bookings uses `pro_payment_booking.head` and `pro_payment_booking.sub_head`. Thus, in this inspected source, Sub Location is the same catalog ID exposed as Subhead by SMP, not an assumed label equivalence.

Both downloaded `sgcacc/smp-api/src/CatalogController.php` and `sgcacc/zalmi-smp-api/src/CatalogController.php` default to `item_location`, `item_sublocation`, and parent `location_id`. Both customer controllers join the same catalog using booking Head/Subhead IDs. Full Zalmi Core PHP/database is not present locally; its API implementation was inspected, and the user's earlier statement that Zalmi is a copy of SGC remains context. No live Zalmi schema query was performed. Later fetch code must validate the actual table/column schema on the API server and fail clearly on a mismatch.

### Actual Sector

Local SGC schema confirms `pro_inventory.head`, `sub_head`, `company_id` and `sector`; `sector` is VARCHAR. Exact binary grouping for Head 49 returned:

- `'0'`, `'1'`, `'2'`, `'3'`;
- `'Business District'` and `'Business District '` as separate byte values;
- `'Organic Market'`.

Do not convert blanks into `0`: the old ERP screen contains such a presentation conversion, but the new metadata reader must exclude NULL/whitespace-only values while retaining actual `0`. Exact source identity preserves whitespace/case; cleaned display labels are independent.

### Current local portal mappings and connections

| Portal project | Local source record | Head | Mapped Subhead IDs |
| --- | --- | --- | --- |
| `gulberg-city` | SGC, local ID 1 | 48 | 533, 534, 536, 549, 550 |
| `shalimar-smart-city` | SGC, local ID 1 | 49 | 555 |
| `zalmi-x` | ZALMI, local ID 2 | 1 | 1 |

Observed IDs must not be copied into implementation constants. Runtime requests must resolve source and Head from enabled project mappings; ambiguous/shared Head scope must fail for review rather than mix projects.

Configured local base URLs are `https://sgcacc.com/smp-api/v1` and `https://zalmidevelopments.com/smp-api/v1`, with server-side bearer credential references `sgc_catalog` and `zalmi_catalog`. No tokens/passwords were printed or recorded here.

Local `item_sublocation` under Gulberg Head 48 matches the five mapped IDs above. Smart City Head 49 also contains other actual Sub Locations, including IDs 535, 606, 660, 661 and 707; these are not automatically new portal mappings. Stage A Sector discovery is a metadata read scoped to the verified project Head; it must never create/change Head/Subhead mappings. Existing listing eligibility remains unaffected.

### Existing schema and security

Read-only local MariaDB 10.4.32 checks confirmed `portal_projects`, `erp_sources`, `erp_heads`, `erp_subheads`, `erp_project_mappings` and the existing listing metadata structures. No Explorer tables exist. Gulberg currently has existing `_property_explorer` JSON settings; those must remain intact.

Existing Admin flow:

- Routes: `app/Config/Routes.php`, existing `admin/property-explorer` GET/POST.
- Authentication and RBAC: `app/Filters/AdminAuth.php`, `AdminPermission.php`; `projects.view` and `projects.edit` are existing permissions.
- CSRF: `app/Config/Filters.php` applies it to `admin/*`.
- Layout/nav: `app/Views/admin/layouts/main.php`, `html/includes/admin/navigation.php`.
- Existing Explorer: `app/Controllers/Admin/PropertyExplorer.php`, `app/Views/admin/projects/explorer.php`, `app/Services/PropertyExplorer.php`.
- Audit: `app/Services/AdminAuditService.php`.

Existing secure ERP patterns:

- `app/Services/ErpConnectionService.php`: source/host validation, approved hosts, public destination validation/pinning, bearer references, verified HTTPS, bounded responses/timeouts, no redirects/proxy.
- `app/Services/ErpCatalogSync.php`: validated complete catalog snapshots, preserves catalog history. Stage A fetch must not call synchronization that mutates project mappings.
- `sgcacc/{smp-api,zalmi-smp-api}/src/CustomerDatabase.php`: prepared SELECT-only queries and column checks, without loading ERP business code.
- Existing catalog endpoints export Head/Subhead metadata but no complete Sector directory. A read-only, authenticated filter-options endpoint is therefore needed later; it does not exist yet and was not added in this checkpoint.

## Proposed SQL

`database/sql/property-explorer-stage-a.sql` creates **one empty table**, `portal_explorer_filters`, in the portal database. No existing columns, records, mappings, projects, listing approval payloads, ERP schemas, credentials or permissions are modified.

Stores:

- existing project slug primary key, source ID and Head ID;
- original whitelisted source field, exact original value/ID, source label;
- selected display name/icon/visibility/order;
- nullable `parent_filter_id`, version and timestamps.

The root is the project (`parent_filter_id=NULL`). Stage A writes roots only. The same table supports future parent-child records without a maximum-depth schema column. The self-reference prevents a parent from another project/source; future application logic must also reject cycles and incompatible parent scopes. No children or child-value fetching are implemented now.

The unique identity includes project, source, Head, field, an exact-byte SHA-256 value hash and parent context. A generated root sentinel prevents SQL NULL semantics from permitting duplicate root rows. Full raw value comparison must accompany hash-based resolution in later application code. Valid source `0` survives; no original names become display-based keys.

Visibility defaults to **off**. Only admin-selected values are persisted on Save; newly fetched options stay in a bounded server-side draft until confirmed. Refresh must preserve customization/visibility/order and retain previously selected rows even if absent from a subsequent successful response. A failed fetch must make no changes. `last_seen_at` may update only from a successful scoped fetch; it is not an automatic visibility/deletion switch.

The project/source/head columns match inspected parent types. `portal_projects.slug` must use `utf8mb4_general_ci` and all parent tables must be InnoDB. Check the live definitions if they differ from this inspected local schema before manual execution. `IF NOT EXISTS` preserves data on rerun, but does not reconcile an incompatible pre-existing table. Generated hashes and CHECK constraints target the inspected MariaDB version; SQL has not been executed to verify it on the deployment server.

The earlier `property-explorer-layer2-settings.sql` is a superseded proposal. Do not run it for Stage A. Only the new Stage A file is proposed here.

## Next work after explicit SQL-applied confirmation

1. Add the read-only scoped metadata API and reuse approved server-side ERP connection patterns. Never accept arbitrary SQL identifiers from requests.
2. Implement Admin **Property Explorer → Filter Builder**, fetch/refresh checkboxes and selected-root settings against the new table, with stale-version handling, transactions, CSRF, project-scoped validation and audits.
3. Keep all public website files/routes/listing queries and existing ERP mappings unchanged. Stage B integration requires a separate command. Existing public Explorer behavior is not removed by this Stage A task.
4. Run focused authorization, project/source isolation, fetch/save/edit/rename/hide/show/order, duplicate, Sector 0, NULL/blank, refresh/failure and CSRF checks using disposable data/fake transport.

## Current verification and limitations

Completed: read-only local portal schema/mapping inspection, local SGC schema/value inspection, source/controller/security review and static SQL design review.

Not performed: SQL execution, new table creation, functional Stage A QA, endpoint availability check, ERP network request, live ERP/database verification, implementation or deployment. No production claims are made. No existing application, API or public page was modified.

Changed files for this checkpoint: this report and `database/sql/property-explorer-stage-a.sql` only.
