# Stage C1 — schema-aware fields and Admin child Filter Builder

Implemented **locally only** on 13 November 2026. No live upload, real database SQL execution, migration, ERP business write, inventory import, listing snapshot mutation or public child-filter activation was performed. Existing Stage A roots and Stage B public selection remain in place.

## Architecture and source verification

The existing Admin module `/public/admin/property-explorer` now accepts an optional saved `parent` ID. The original root controller branches and `ExplorerFilterBuilder` service remain the root implementation. Child-specific context, metadata validation and saving live in `ExplorerChildBuilder`; the existing view renders the child panel inside the same Admin layout. No second Admin module or route was added.

Both downloaded API sources gained an authenticated `POST /smp-api/v1/filter-tree.php`. The existing Layer 2 `GET /filter-options.php` was preserved. A reusable `InventoryFieldDiscovery` inspects the actual source's `pro_inventory` columns through `CustomerDatabase::columns()` and its prepared INFORMATION_SCHEMA read. The server validates schema/type/policy again for every catalog or value request. There is no table/column expression supplied by the browser that can directly enter SQL.

Source verification used the existing SGC schema export at `D:/downloads/u830963356_sgcacc_db.sql.gz`, SGC Core PHP inventory relationships, both API folders, and existing portal SQL/source definitions. The 67 inventory column definitions were read from the export **as text**, not executed/imported. No SGC/Zalmi database or live API was queried during C1, in accordance with the no-SQL/no-deployment instruction. A separate actual Zalmi schema export is unavailable; its production discovery catalog remains unverified and will be derived from its own database, never copied from SGC.

## Approved tables and generic policy

- **Ordinary scalar discovery:** `pro_inventory` only.
- **Explicit existing adapter:** Sub Location ID through configured `item_sublocation.id`, validated parent `location_id`, and `pro_inventory.sub_head`. Head existence is checked against the configured `item_location` table. These configured catalog identifiers remain server-controlled.
- **Not approved:** arbitrary tables, customer/ledger joins, `pro_inventory_floor`, maintenance joins, free-form SQL or expression input. Inventory `floor_no` is deliberately separate from booked-floor records.

Generic scalar types: `ENUM`, `VARCHAR`, `CHAR`, `TINYINT`, `SMALLINT`, `MEDIUMINT`, `INT`, `BIGINT`. Floating-point/decimal quantities, text, binary, BLOB and JSON are unavailable. Newly added suitable categorical columns are discovered without adding a per-column adapter.

The policy rejects identifier-like/property-number columns, credentials, PII/customer/owner names, contact/address fields, finance, tokens, notes/prose, URLs/files/media, timestamps and internal workflow/access fields. It recognizes common underscore, camel-case and concatenated sensitive names. Size quantity/unit, Phase and Street have explicit exclusions. Every candidate also undergoes a branch-scoped distinct-value profile before appearing: blank-only columns are unavailable; more than 128 distinct values, overlong/invalid identities, token/contact-like data and prose are rejected. Unsafe raw values are never returned, including in limitation messages. User-friendly labels are generated from names; the original schema key remains authoritative.

Automatic policy is category-data screening for an explicitly approved inventory table, not a claim that arbitrary future databases/joins are approved. A new field must still pass the live type/name/value/cardinality policy in its own source and selected branch. No per-field developer action is needed for an ordinary safe field that passes those checks.

## Fields and capabilities

The following are **policy-eligible in the inspected SGC schema**, not claims that every field is populated in each project:

| Field | Original type | Current public metadata readiness |
| --- | --- | --- |
| `item_sublocation.id` | INT adapter | Existing source/Head/Subhead mapping identity |
| `pro_inventory.sector` | VARCHAR | Exact `payload.sector` where source-bound and recorded |
| `pro_inventory.block_no` | VARCHAR | Exact `payload.block` where recorded |
| `pro_inventory.floor_no` | ENUM | Missing original identity in reliable approved listing snapshots; C2 needed |
| `pro_inventory.tower` | ENUM | Missing original identity; C2 needed |
| `pro_inventory.category` | ENUM | Raw category differs from transformed listing/display categories; C2 needed |
| `pro_inventory.nature` | ENUM | C2 needed |
| `pro_inventory.aprt_catagory` | ENUM | C2 needed; never inferred as Bedrooms |
| `pro_inventory.apartment_type` | ENUM | C2 needed |
| `pro_inventory.no_of_floor` | INT | C2 needed |
| `pro_inventory.sale_type` | ENUM | Inventory complete/floor-wise attribute, distinct from booking Sale/Resale; C2 needed |
| `pro_inventory.dimension`, `dimension_type`, `road_size` | VARCHAR / ENUM | Eligible only if branch data pass categorical screening; C2 needed |
| `pro_inventory.block_new`, `sector_new` | VARCHAR | Separate original fields, never silently aliased to Block/Sector; C2 needed |

SGC root discovery stays unchanged: Smart City sectors are Head-wide across all Subheads. Child inventory conditions add all saved ancestors to that Head. Gulberg Sub Locations are constrained to mapped IDs. Zalmi uses its own project/source/Head and runtime schema. The tests simulate Zalmi with a missing Tower column and verify that SGC's capability is not assumed for it.

**Floor/Tower:** supported for Admin discovery and child configuration when present/populated and safe in that source. Neither is advertised as publicly matchable from current approved listings. The UI distinguishes ERP discovery, Admin fetch availability, existing metadata readiness and disabled public child navigation.

**Explicitly excluded:** `pro_inventory.phase`, `street_no`, `size`, `size_code`; no Size+Unit composite adapter; no booked-floor ID join or bedroom inference.

## Admin workflow

1. Open an existing saved root/child and select **Add Child Filter / Manage Children**.
2. See project, ERP source, dynamically resolved Head and full ancestor breadcrumb.
3. A CSRF-protected POST discovers the branch's current safe catalog automatically on first opening, or via **Discover / Refresh Safe ERP Fields**.
4. Choose one field for that parent's child row and review its public metadata warning.
5. **Fetch / Refresh Child Values** rediscovers/revalidates the catalog, then obtains actual distinct values under all ancestors. No automatic filter writes occur.
6. Select values (new options start unchecked/hidden), customize display name/icon/visibility/order, and save.
7. Open any saved child and repeat. Different parents can select different fields.

Once siblings exist, their field is pinned. Changing another parent's field does not affect them. Saved display settings and disappeared values remain editable/preserved after refresh or ERP failure. Unchecking never deletes a saved row. No reparent or delete operation was introduced.

## Scope, tree identity and transaction safety

The portal resolves all project mappings using the unchanged root service, reconstructs the full ancestor path from stored rows, validates the exact source/Head/project, checks sibling fields at each selected ancestor, and sends a flat context to the ERP API. The browser posts only project, parent and a chosen catalog field, not trusted ancestor SQL or values.

The API checks source/project compatibility, Head existence, complete parent linkage, repeated node IDs/cycles, exact ancestor values, contradictory conditions and the final parent. All conditions apply to the **same `pro_inventory i` row**. It rejects a branch with no matching property. Catalog and values are scoped to that same branch. Smart City child discovery is not restricted to mapped Subhead 555; Gulberg/Zalmi Sub Location IDs remain mapping-restricted.

Root hashes are unchanged. Child hashes include project, source, Head, **parent ID**, field and exact value, so repeated values under different parents do not collide. Existing `source_field` VARCHAR(64) and `source_value` VARCHAR(255) limits are checked without truncating identities. Values retain case, trailing spaces and actual `0`; labels do not become keys.

Saves lock the project, current mappings and project filter rows. The draft fingerprint includes ancestor/sibling identities and versions; each edited child also requires `record_version`. Transaction failure or audit failure rolls back all selected changes. Parent IDs are immutable, preventing creation of a cycle by reparenting. Source/project/Head checks supplement the existing composite foreign key.

Traversal is iterative with a visited-ID set, not recursive layer slots. There is no fixed business depth. Operational budgets produce explicit errors: 10000 project nodes per context, 128 KiB flat metadata request, 64 candidate fields, 128 distinct values, 2 MiB response, approximately 8-second engine budget and 3/10-second transport timeouts. No path or identity is silently truncated. A 121-ancestor context plus a child save at that depth passed memory QA.

The new metadata connection enables a **connection-local** query time budget: MariaDB `max_statement_time=2`, or MySQL `max_execution_time=2000`. This setting runs only when the new metadata endpoint constructs `CustomerDatabase(true)`; existing API consumers retain their constructor behavior. It is not a global setting or ERP data mutation. Unsupported settings/permissions fail closed. The setting is implemented but **not executed against a real database during this task**. Syntax/semantics were checked against the official [MariaDB timeout documentation](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/aborting-statements) and [MySQL SELECT timeout documentation](https://dev.mysql.com/doc/refman/8.0/en/optimizer-hints.html).

Existing server-only bearer credentials, exact host approval, public-address validation, DNS pinning, HTTPS/TLS checks, no proxy/redirect, RBAC, CSRF and audit conventions remain in use. ERP error messages are mapped to safe portal explanations rather than displaying arbitrary remote response text.

## Database and Stage C2 proposal

**No schema changes, SQL file or migration is required for C1.** The existing `portal_explorer_filters` parent-scoped uniqueness and composite parent FK are reused. No SQL was executed, including test-schema creation, fixture inserts or SELECTs against a real portal/ERP connection. Memory adapters evaluate fixture records without connecting to a database.

C1 does not implement future listing snapshot capture. Smallest C2 proposal:

1. Add a bounded, server-generated typed metadata envelope to the authenticated ownership response, reusing the same schema/policy engine. Bind capture version, source code/local source mapping, property ID, Head/Subhead and source schema fingerprint. Never accept it from a customer form.
2. Capture selected category-filter fields before submission/approval into a reserved versioned object in the **existing listing payload JSON**. Suggested contract:

```json
{
  "_explorer_metadata": {
    "version": 1,
    "source_id": "1",
    "property_id": "example-property-id",
    "head_id": "example-head-id",
    "subhead_id": "example-subhead-id",
    "schema_fingerprint": "server-generated",
    "captured_at": "server-timestamp",
    "fields": {
      "pro_inventory.floor_no": {"type": "string", "db_type": "enum", "value": "ground"},
      "pro_inventory.tower": {"type": "string", "db_type": "enum", "value": "0"}
    }
  }
}
```

The values above illustrate a contract only; they are not assertions about actual ERP rows. Integer identities should use an explicit integer type with lossless decimal text, avoiding JavaScript/float precision loss. Missing fields must remain missing, never defaulted to zero. Exact strings remain untrimmed. Sub Location adapter identity must be captured separately from display labels.

3. Revalidate capture/source/property before approval, then let the existing immutable approval record cover that payload version. No custom per-field capture code is needed for ordinary schema-discovered scalar fields.
4. Do not silently backfill approved payloads: approval integrity compares the payload/version. Legacy records remain in All/root-supported filters and only match a child if its exact metadata is actually available. A later authorized refresh/edit follows existing review, rather than rewriting history.
5. Public tree activation is a separately authorized stage. It must apply all ancestor predicates and saved visibility to the existing eligibility query, with no public ERP calls. It is not part of C1.

This proposed JSON envelope needs no new database column initially. A later measured indexing requirement would require a separate SQL proposal/approval.

## Exact application files

Portal new:

- `app/Services/ExplorerChildBuilder.php`
- `app/Views/admin/projects/explorer_children_context.php`

Portal updated:

- `app/Services/ExplorerErpClient.php`
- `app/Controllers/Admin/PropertyExplorer.php`
- `app/Views/admin/projects/explorer.php`

In **each** local API folder `D:/xampp/htdocs/sgcacc/smp-api/` and `D:/xampp/htdocs/sgcacc/zalmi-smp-api/`:

- New `src/InventoryFieldDiscovery.php`
- New `src/FilterTreeController.php`
- New `v1/filter-tree.php`
- Updated `src/CustomerDatabase.php`
- Updated `index.php`
- Updated `src/routes.php`

Local deployment package: `deploy/stage-c1-filter-tree/` with `portal/`, `smp-api/`, `zalmi-smp-api/`. No credentials/private config are included. **Not uploaded.**

## Verification and limitations

**168 checks passed**, with no real SQL or ERP/SMS requests:

| Check | Result |
| --- | --- |
| `.qa/c1_api_checks.php smp-api` | 37 memory-only SGC checks: safe/new fields, Floor/Tower, sensitive/Phase/Street/Size exclusion, all-Subhead Smart City, full-path same-property predicates, exact 0/trailing spaces, bad identifiers, cycles, source/Head isolation, schema failure, cardinality/value length/time budgets |
| `.qa/c1_api_checks.php zalmi-smp-api` | 31 memory-only Zalmi checks, including its own missing-column capability instead of assuming SGC schema |
| `.qa/c1_portal_checks.php` | 39 memory-only service checks: child save/edit/hide/show/order, independent parent fields, parent-key uniqueness, refresh preservation, context/version conflicts, transaction/audit rollback, duplicates, cycles/cross-source/project, ancestor sibling consistency, 121-node path and deep child save, root hashes/root editing regression, server-owned public readiness |
| `.qa/verify_c1_admin.py` | 25 isolated actual-controller/view browser checks: open/discover/fetch/save/nest, breadcrumb, snapshot warnings, unchecked values, field pinning, refresh/failure preservation, 360/390px no overflow, viewer/denied/guest behavior, child RBAC, root Fetch/Save, malformed parent handling and no JS errors. Storage/ERP and minimal layout shell are QA doubles; this is not a real database-backed full Admin-shell test. |
| `.qa/c1_transport_checks.php` | 20 fake-cURL child transport checks: fixed POST endpoint, JSON/bearer, pinned public host, TLS, no proxy/redirect, limits, safe errors, disabled/wrong-source/unapproved/private destinations |
| `.qa/builder_transport_checks.php` | 10 existing root transport regression checks, fake cURL |
| Real CI4 middleware at `.qa/c1_csrf_router.php` | 6 actual CSRF checks with file sessions and an unusable SQL driver: token form, missing/bad rejection, valid acceptance, rotation, foreign-session rejection |

All **17 production PHP files** and the modified view's inline JS pass syntax checks. Twelve protected root/public/customer/routes/RBAC files remain SHA-256 identical, including `ExplorerFilterBuilder`, `PropertyExplorer`, the public project/homepage, Stage B read services, customer submission, customer portfolio, listing policy, routes and permission mapping. Only the existing Admin controller/view and shared metadata client were extended.

Tests and evidence: `.qa/c1_memory_portal.php`, `.qa/c1_portal_checks.php`, `.qa/c1_api_checks.php`, `.qa/c1_transport_checks.php`, `.qa/c1_admin_router.php`, `.qa/verify_c1_admin.py`, `.qa/c1_csrf_router.php`, `.qa/c1-preserved-baseline.json`, `.qa/c1-sgc-inventory-schema.json`, `.qa/c1-portal-checks.log`, `.qa/c1-sgc-api-checks.log`, `.qa/c1-zalmi-api-checks.log`, `.qa/c1-child-transport.log`, `.qa/c1-admin-browser-results.json`, `.qa/c1-real-csrf-results.json`, `.qa/c1-admin-child-390.png`. QA endpoints bind to localhost only and are not deployment files.

**Unverified by instruction:** real MariaDB SQL execution/locking/timeout behavior, actual schema permissions, real full Admin-shell/database integration, actual populated catalog values from either ERP, and live endpoint availability. No real Zalmi schema verification is claimed. A later authorized deployment must compare its API source/config and run authenticated read-only smoke checks. C1's memory tests validate query construction/logic; they do not substitute for executing the generated SQL on the deployment database.

**Status:** Stage C1 complete locally; no further implementation, C2/public activation or deployment performed. Phase/Street excluded; existing Stage A root and Stage B public behavior preserved.


## Authorized portal-only deployment ? 13 November 2026

The user subsequently authorized the five portal files in this package for live upload. They were compared with the previous deployed versions, backed up, server-linted, uploaded and SHA-256 verified. Backup: `.qa/live-backups/stage-c1-filter-tree-20261113-232107`; manifest: `.qa/stage-c1-filter-tree-live-deployment.json`. Five live HTTP checks passed: homepage, existing public project page, customer login, protected Admin root and protected Admin child URL (guest requests redirected to Admin login). No SQL, migrations, ERP API upload or ERP data changes were performed. Authenticated child Fetch/Save was not tested live; it requires the matching `filter-tree.php` API implementation to be available on the source. Earlier local-only statements describe the implementation phase; this later authorization deploys only the five portal files. C2 and public child activation remain unimplemented.


PHP 7.4 compatibility correction: both ERP servers reported a parse error in InventoryFieldDiscovery.php line 14 due to PHP 8 constructor property promotion. Removed promotion and PHP 8-only string/list helpers. Both local API copies and deploy package copies were corrected; 68 memory API checks passed and all 12 packaged API files passed actual PHP 7.4 lint through server stdin. No live file writes, SQL, or business execution were performed in this correction. Replace only src/InventoryFieldDiscovery.php in each live API root to apply this correction.
