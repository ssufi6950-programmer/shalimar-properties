# Property Explorer Layer 2

Implemented locally. No deployment, live ERP verification, migration, production schema change or ERP write was performed.

## Existing flow preserved

The homepage `html/index.php` includes `html/includes/home/hero-b.php`. Its project links remain `project.php?project=<slug>`; the homepage links were verified byte-identical to the pre-existing Layer 1 upload package.

`html/project.php` remains the existing standalone endpoint/view inside the CI4 repository. Layer 2 was added there, using the shared public header/footer and cards. No replacement project page or new Layer 1 was created. `html/projects.php` and the static Explorer prototype are untouched.

## Inspected schema and source identity

Local read-only inspection verified:

- `portal_projects`: `slug`, `name`, `published`, `payload`.
- `erp_project_mappings`: `id`, `project_slug`, `erp_source_id`, `erp_head_id`, `erp_subhead_id`, `is_active`.
- `erp_sources`, `erp_heads`, `erp_subheads`: existing source/Head/Subhead composite relationships, presence and original `smp_active` strings.
- `portal_listings`: exact project/mapping/source/property relationships, JSON payload, `listing_purpose`, `review_state`, `status`, `deal`, approval version/history and ownership-proof columns.
- `portal_owned_properties`: existing source-bound ownership snapshots; these are not bulk-imported or rewritten by this feature.
- Both downloaded API folders' `src/CustomerController.php`: `sector` is copied from `pro_inventory.sector`; booking Head/Subhead identities join the actual Head/Subhead catalog.

The local mappings observed were Gulberg → SGC Head 48 (Subheads 533, 534, 536, 549, 550), Smart City → SGC Head 49/Subhead 555, and Zalmi → ZALMI Head 1/Subhead 1. These observed IDs are **not hardcoded** in the implementation. Runtime identity uses existing mapping rows and source codes. Project-specific dimension selection uses the three existing slugs requested by the user.

Gulberg and Zalmi use exact mapped Subhead IDs. Smart City uses a distinct source/Head/sector dimension and never changes its Head/Subhead mapping. Sector keys preserve exact UTF-8 bytes, including numeric/string `0` and trailing spaces. NULL, missing, non-scalar, and whitespace-only values do not become icons. Legacy text such as `Sector I` is not assumed equivalent to `1`.

Settings and public filter URLs use a stable SHA-256 key over project slug, source ID, Head ID, dimension and exact source value. Display names never determine identity. Source, project and purpose are revalidated on every request.

## Public behavior

- Opens immediately with the first paginated page of all eligible project listings for the selected purpose. Buy is the default when the existing homepage link has no purpose; `purpose=rent` selects Rent.
- All applies no Layer 2 restriction. Missing metadata and hidden icons do not hide otherwise public listings from All.
- Badges are 36px with compact labels and mobile wrapping. Only Layer 2 is shown, with Buy/Rent and Back to `index.php#hero`. No Type/Size steps were added.
- Existing `PublicListingSearch`, `ListingPolicy` and `ErpVisibility` gates remain authoritative for approval, current publication, project/source/mapping availability, exact Head/Subhead `yes`, retained records and Sale/Rent separation.
- Existing `render_card`, image handling, listing URLs, price rendering and `PublicListingContact` privacy rules are reused. No additional private identity/contact fields are selected for public output.
- Existing 12-row pagination bounds listings and contact resolution. Metadata options use one grouped local query, not one ERP request per property.
- Only public options with eligible listings for the current purpose become public badges. Admin can also manage synchronized mapped Subheads with no current listings. Smart City Admin sectors are discovered from source-bound local listing submissions, including historical/pending submissions; those private-only values never become public badges until qualifying public listings exist.
- If metadata lookup fails, All remains usable when the existing public listing/catalog query is available. Unknown, hidden, foreign-project or wrong-purpose option URLs return HTTP 400 with a safe empty state and an All link. Unknown/unavailable projects retain HTTP 404.
- Standard GET links work without JavaScript. Progressive enhancement fetches this same local page, swaps only Explorer content, maintains URL/history and uses 200–220ms transitions. Previous requests are cancelled; modified clicks retain normal browser behavior. Reduced-motion preference is respected. No ERP API is called by this page.

## Admin and persistence

Screen: `/public/admin/property-explorer` under **Property Explorer → Layer 2 Settings** (adjust the base path for local/deployed hosting).

GET requires `projects.view`; POST additionally requires `projects.edit`. Existing Admin authentication, route permission filter, CSRF middleware, layout and audit service are reused. Editor, viewer, denied and guest paths were checked through the real HTTP middleware.

Each option supports Show/Hide, Website Display Name, an allowlisted icon and numeric order. Display edits change only `portal_projects.payload._property_explorer`; they do not alter listing payloads, approval history, original source values, mappings or publication. Version checks prevent stale overwrites. A project-row lock and one transaction preserve unrelated project editor JSON and commit the audit entry with the settings change. Unavailable options are not deleted from saved settings.

## SQL

**None required. Do not run `database/sql/property-explorer-layer2-settings.sql`.** That earlier table proposal is superseded by the reviewed JSON-storage approach. No application code references that proposed table. No backfill is required.

## Application files

New:

- `app/Services/PropertyExplorer.php`
- `app/Controllers/Admin/PropertyExplorer.php`
- `app/Views/admin/projects/explorer.php`

Updated:

- `html/project.php`
- `app/Services/PublicListingSearch.php`
- `app/Libraries/PortalCatalogStore.php`
- `app/Config/Routes.php`
- `app/Filters/AdminPermission.php`
- `app/Config/AdminSecurity.php`
- `app/Helpers/admin_helper.php`
- `app/Services/AdminAuditService.php`
- `html/includes/admin/navigation.php`

## Focused verification

**63 focused checks passed:**

- `.qa/explorer_checks.php`: 31 service/database checks for project and Buy/Rent isolation, approved/Sold restrictions, exact Subhead and binary sector filtering, numeric/string Sector 0, NULL/blank exclusion, trailing-space identity, hidden option versus All, rename identity, validation, stale versions, JSON preservation, audit field retention, unsupported dimensions, empty rentals and bounded pagination without duplicates.
- `.qa/verify_explorer.py`: 32 HTTP/browser checks covering the existing project page, initial All, individual filtering, Buy/Rent, Back, 360/390px public/Admin layouts, badge dimensions, foreign/hidden option rejection, unknown project, actual Admin save/rename/icon/hide, real CSRF rejection, viewer POST rejection, no-view role denial, guest authentication and JavaScript errors.
- Syntax passed for all 12 affected PHP application files and the inline JavaScript.
- Read-only localhost smoke checks returned HTTP 200 with Explorer rendered for all three actual project slugs.
- Additional fixture checks confirmed the original editor JSON sentinel remained intact and both settings changes retained all five expected audit fields.

Tests used a guarded disposable MariaDB schema with empty structures cloned from the local portal, fictional rows, isolated sessions and disabled SMS/WhatsApp. No real portal/ERP rows were copied or modified, and no CI4 migrations ran. QA support: `.qa/explorer_fixture.php`, `.qa/explorer_router.php`, `.qa/explorer_checks.php`, `.qa/verify_explorer.py`.

## Remaining verification and rollout

Live deployment, live ERP/source availability, production data coverage and current live database consistency were not verified. Public icons reflect trustworthy metadata currently stored locally; they do not claim every ERP sector/property has been imported. There is no scheduled metadata refresh or inventory sync added here.

Review package paths in `deploy/layer2-property-explorer/README.md`. The package is local only; no files were uploaded. No ERP API folder upload is needed. Existing homepage/mobile CSS changes remain separate from this Layer 2 package.
