> Latest combined API packages include Authorized Agencies plus all customer/floor/catalog fixes. See [AUTHORIZED-AGENCIES-IMPLEMENTATION.md](AUTHORIZED-AGENCIES-IMPLEMENTATION.md) for the new agency endpoint, migration and mapping workflow.

# Customer identity and current-ownership API contracts

> 6 November 2026 policy update: exact `booking_in_process='yes'` now permits resale through the existing owner-specific OTP, preview and fresh final booking verification. `'no'` remains allowed; NULL/unexpected values remain blocked. Both ERP verification API folders are updated to build `2026-11-06.booking.10`. This supersedes the earlier historical pending-document rejection checks described below. See [IN-PROCESS-RESALE.md](IN-PROCESS-RESALE.md) for files and upload instructions. ERP booking approvals are never modified.

Date: 5 November 2026. Implemented locally; live deployment must be checked separately.

## Current state

Both downloaded API folders now implement bearer-authenticated, read-only customer endpoints:

- `D:\xampp\htdocs\sgcacc\smp-api`
- `D:\xampp\htdocs\sgcacc\zalmi-smp-api`
- `POST /smp-api/v1/customer-identity.php`
- `POST /smp-api/v1/customer-owned-properties.php`

The current-booking API includes ERP in-process/document-approval rows. The latest resale policy also permits these rows through property-specific OTP and fresh ownership verification; document approval remains unchanged. The private local portal configuration points both `SGC` and `ZALMI` source codes to these paths. Upload the new complete API folders to their corresponding live hosts before retrying customer verification. Local implementation/tests do not prove that a live host has received the update. Health build for this upload is `2026-11-05.agencies.7`.

Upload packages: `.qa/erp-api-upload/sgc-smp-api-agencies.zip` and `.qa/erp-api-upload/zalmi-smp-api-agencies.zip`, each with a root `smp-api/` directory. They preserve the existing per-host private database configuration and bearer hash. Keep them private and upload hidden `.htaccess` files too. No Apache/SSH changes or ERP business-code/schema changes are required.

`Verification is temporarily unavailable` was expected while the endpoint paths were empty. A delimiter error in the portal client's path validation was also corrected. Missing routes, invalid credentials, database failures and inconclusive ownership still produce a safe temporary-service response; no fake customer or SMS bypass is added.

## Shared transport

- Use the existing registered source's `base_url`, source ID and server-side bearer credential reference.
- HTTPS from the CI4 backend, verified certificates, approved public hosts, no redirects.
- `POST`, `Content-Type: application/json`, `Accept: application/json`, `Authorization: Bearer <server-side token>`.
- Do not include CNIC/mobile in URLs or access-log query strings.
- Responses: `Cache-Control: no-store`; never return SQL, database credentials or stack traces.
- A lookup failure/timeout/401/404/500 is an unavailable service, not a genuine negative identity match.
- Existing health/catalog endpoints and their visibility synchronization are unchanged.

## 1. Customer identity lookup

Implemented filename: `/customer-identity.php`, appended to the registered API base URL.

Request:

```json
{"cnic":"1111111111111","mobile":"923001234567"}
```

The CNIC is exactly 13 ASCII digits. Mobile is canonical Pakistani international format without `+`: `923XXXXXXXXX`.

A successful match (HTTP 200):

```json
{
  "api_version":"v1",
  "matched":true,
  "customers":[
    {"customer_id":"101","display_name":"Verified owner","registered_mobile":"923001234567"}
  ]
}
```

A genuine negative match (HTTP 200):

```json
{"api_version":"v1","matched":false,"customers":[]}
```

Rules:

1. Locate the identity using the actual ERP CNIC field and verify the supplied mobile against a registered customer mobile. A browser-supplied ledger/customer ID is never proof.
2. Return only identities matching BOTH normalized values. Do not return another ERP identity whose CNIC matches but whose registered mobile differs.
3. `customer_id` must be the real, stable source-specific customer identifier, represented by a positive integer/decimal string of up to 18 digits. Up to 10 verified records per source are supported; no source identity may be guessed from a name.
4. `display_name` is a string up to 120 characters. The registered mobile must normalize to the exact submitted mobile. Do not echo full CNIC or unrelated customer records.
5. If the ERP stores phone aliases/multiple numbers, use only authoritative registered mobile fields and normalize them. Do not silently treat arbitrary contact/reference phone fields as verified mobiles.
6. Both ERP endpoints require the existing server-side bearer. Public OTP request/resend/verification limits are enforced in the portal; the API is not a public identity directory.

## 2. Current owned properties

Implemented filename: `/customer-owned-properties.php`, appended to the registered API base URL.

Request:

```json
{"customer_id":"101","verified_mobile":"923001234567","page":1,"page_size":50}
```

These values originate from a verified server-side challenge or persisted verified link, not browser input. The ERP endpoint should validate the identity/mobile and use its authoritative current-ownership rules.

Response (HTTP 200):

```json
{
  "api_version":"v1",
  "customer_id":"101",
  "page":1,
  "has_more":false,
  "properties":[
    {
      "property_id":"1001",
      "booking_id":"500",
      "current_owner_customer_id":"101",
      "ownership_status":"current",
      "booking_status":"active",
      "booking_in_process":"yes",
      "erp_booking_status":"complete",
      "sale_type":"sale",
      "head_id":"48",
      "head_name":"Gulberg City",
      "subhead_id":"536",
      "subhead_name":"Gulberg City Plots",
      "head_smp_active":"yes",
      "subhead_smp_active":"no",
      "unit_number":"Plot A1",
      "floor_title":"SECOND FLOOR",
      "floor_category":"studio",
      "floor":"SECOND FLOOR (Studio)",
      "block":"A",
      "sector":"I",
      "property_type":"Residential Plot",
      "size":"5 Marla",
      "location":"Sargodha",
      "original_agent_agency":"Registered agency",
      "updated_at":"2026-11-05T01:00:00+05:00"
    }
  ]
}
```

For co-ownership, `current_owner_customer_ids:["101","102"]` may replace the singular current-owner field, but it must contain verified current owners, not historical booking parties or nominees.

An authoritative empty result:

```json
{"api_version":"v1","customer_id":"101","page":1,"has_more":false,"properties":[]}
```

Rules:

1. `property_id` must identify the actual stable unit/inventory record. Do not use a changeable booking number as the permanent property key. Positive numeric IDs up to 18 digits are currently supported by the portal schema.
2. `head_id`/`subhead_id` must be the actual ERP catalog identifiers; `0` is retained as an unassigned classification and cannot resolve an eligible mapping.
3. Return current ownership, accounting for transfers, cancellations, sale returns, co-ownership and the current valid booking. The original booking customer is not automatically the current owner.
4. The portal only accepts an ownership row when its owner ID list contains the verified ERP customer, `ownership_status` is exactly `current`, and `booking_status` is exactly normalized `active`. Translate the ERP's actual business statuses into this explicit contract.
5. Include properties whose Head/Subhead `smp_active` is `no`, NULL or unexpected strings. These affect public resale eligibility, not private ownership visibility.
6. Preserve raw `head_smp_active`/`subhead_smp_active` strings or NULL. Never convert them to numeric/boolean flags.
7. Use a deterministic current-property order; at most 50 rows per page. `page` is an integer, `has_more` a boolean. No more than 100 pages are accepted per identity; an incomplete/failed later page must not masquerade as an authoritative empty portfolio.
8. Property descriptions may be missing/empty; identity/current-owner/classification/status fields must follow the contract. Do not return other customers' contact details or CNICs.
9. A transferred-out or cancelled property should be absent. The portal defensively excludes rows that prove ownership belongs elsewhere or have non-current/non-active statuses.
10. Do not filter solely by Head/Subhead or use name matching to determine ownership/project mapping.

## Inspected schema and current-ownership authority

The owner confirmed that Zalmi is a copy of SGC. The Sale report in `admin/property/property-booking.php` filters `sale_type='sale'`, `locked='no'` and `cancel='no'` without excluding in-process bookings. The implementation uses the actual downloaded SGC schema and business write paths:

- `ledger`: `id`, `id_card`, `mobile`, `name`, `active_customer`, `locked`.
- `pro_payment_booking`: `id`, `property_id`, `company_id`, `customer_ledger`, `co_owners`, `head`, `sub_head`, `property_status`, `sale_type`, `locked`, `cancel`, `in_process`.
- `pro_inventory`: stable `id`, `company_id`, `status`, unit/file/plot/type/size fields. Its `customer_id` can remain stale after a resale and is not accepted as ownership proof.
- `item_location` / `item_sublocation.location_id`: labels and raw visibility flags, left-joined so an inactive/missing classification never removes a privately owned property.
- Optional `property_unit` and `pro_location` provide size conversion and location labels when present.

`admin/property/property-resale.php` locks previous bookings for the same property/company and unlocks the new booking. `return-booking-approval.php` removes a returned booking and may unlock its predecessor. The API therefore does not choose the greatest booking ID or reuse an original seller's booking.

A current property requires matching inventory/booking company, exactly one unlocked booking, `cancel='no'`, sale type `sale` or `resale`, and both booking/inventory status in `booked`, `resale`, `ownership`, `complete`. Cancelled, returned, held/token, transferred-out and locked historical rows do not qualify. `in_process` tracks the ERP document/approval workflow, not whether the current booking belongs to the customer. Current in-process bookings remain privately visible, with `booking_in_process` returned unchanged. The portal accepts exact string `yes` or `no` for resale eligibility, subject to every existing ownership, visibility, mapping and duplicate-listing rule. `yes` retains its Pending ERP approval label; NULL and unexpected values remain blocked and labeled Unconfirmed. Property-specific OTP and a fresh final current-booking check are required for publication in both cases. No ERP approval flag is modified. Older API versions omitted the field after filtering to `no`; the portal preserves that compatibility. Multiple unlocked bookings fail closed with `ownership_ambiguous` rather than guessing. The final property SELECT rechecks unlocked-row uniqueness.

Primary owner and strict JSON-list/legacy CSV co-owner IDs supply the owner proof. Malformed matching co-owner data is inconclusive, never permission. Identity and ownership requests both revalidate an unlocked active customer ledger's registered mobile. No customer list, full CNIC, SQL errors or database credentials are returned.

All database operations are prepared SELECT statements. ERP bootstraps, sessions, transfers, bookings, schemas and business logic are not modified. A live schema mismatch returns a safe service error for office verification.

## Portal paths and deployment verification

The local private portal environment is now configured as follows:

```dotenv
customerPortal.identityPaths = '{"SGC":"/customer-identity.php","ZALMI":"/customer-identity.php"}'
customerPortal.propertiesPaths = '{"SGC":"/customer-owned-properties.php","ZALMI":"/customer-owned-properties.php"}'
```

The default configuration class stays empty for an unconfigured installation; the local private environment selects the implemented paths. API credentials continue to use the existing ERP Sources records and their credential references; no raw secret belongs in browser code or this document.


## Verification and upload

1. Upload the contents of each host's matching ZIP over its live `smp-api/` folder, including `src/`, `v1/` and hidden `.htaccess` files. Preserve the ZIP's host-specific `src/catalog.local.php`.
2. Health should report build `2026-11-05.agencies.7`. A POST of `{}` to each new endpoint with the existing bearer should return 400 `invalid_request` once the database/schema is accessible; 404 means files are missing, 401 means a bearer mismatch, 503 means configuration/schema/ownership needs checking.
3. Run `php .qa/check_customer_api_deployment.php` locally for safe status/build checks. It sends no real identity, OTP or SMS request.
4. Retry CNIC + the customer's registered mobile in the local portal. OTP delivery stays redirected to **03427056415**, while the verified identity remains the original customer's mobile.

`php .qa/verify_customer_apis.php` exercises actual HTTP endpoints against a disposable MySQL schema in both API folders, with mysqli/PDO, authentication, wrong mobile, transfer/co-owner/cancellation/pending/return rules, ambiguous bookings, pagination, all sixteen visibility flag combinations and read-only assertions. `php .qa/customer_api_path_checks.php` exercises the real portal path-validation boundary. Other existing OTP/SMS/catalog regression tests remain applicable. No real SMS is sent by these tests.


## 33 versus 15 reconciliation (5 November 2026)

For the most recently verified customer, the live previous API returned 14 Smart City + 1 Gulberg records. The user compared the Smart City Sale screen at Head 49 / Subhead 555. Its source SELECT does not filter in_process. A read-only comparison against the downloaded local ERP snapshot established 33 current Sale rows in that scope: 11 cleared + 22 in-process. Three current Smart City Resale bookings and one Gulberg Sale booking account for a full private total of 37.

`php .qa/reconcile_customer_snapshot.php` runs the patched actual API on a loopback-only temporary server against the local ERP snapshot, verifies the Sale-screen scope contains the same 33 property IDs, passes all returned records through the actual portal normalizer (37 retained), and checks that all 22 in-process rows remain resale-ineligible. No ERP/portal row or SMS is written. This is a snapshot verification; live totals require uploading build 2026-11-05.agencies.7 and refreshing My Properties. A live booking change may alter the count.

The full disposable API/SQL suite and customer OTP/portfolio/HTTP/browser suites also pass, including private pending-document display, rejected resale preparation, transferred/cancelled exclusion and unknown processing flags. Existing ownership proof, identity validation, pagination and strict public Head/Subhead visibility are retained.


## Floor metadata (5 November 2026)

Both APIs now return optional `floor_title`, `floor_category` and display-ready `floor` strings. These describe the actual child inventory unit, not the whole tower/parent property. The inspected ERP function `getFloorTitle()` in `admin/property/property-booking.php` reads `pro_inventory_floor` where `sub_property_id = pro_inventory.id`, uses `title` and adds a capitalized nonempty `category` in parentheses.

The API follows that relationship through an optional LEFT JOIN. It chooses the lowest matching floor row ID to prevent legacy duplicate links from multiplying properties or breaking pagination. It does not link the floor table's parent `property_id` to the owned unit. Missing table/title or no matching row yields empty floor strings; a missing/NULL/empty category does not produce empty parentheses or remove ownership rows. No floor/booking/property table is modified.

The actual local records for bookings 5921 and 5997 were verified through the patched API: BBT-03 is `FIRST FLOOR`; BBT-04 is `SECOND FLOOR (Studio)`. The read-only snapshot reconciliation still returns 33 Smart City Sale + 3 Smart City Resale + 1 Gulberg properties, with all in-process resale restrictions retained. Live deployment needs the new floor-fix ZIPs; a local snapshot check does not establish live upload completion.

The portal retains all three floor fields, displays the floor on Dashboard/My Properties cards and property details, includes it on the verified resale review, and searches by floor. Old API responses without these optional fields remain compatible. Automated API tests cover the child link, studio/NULL category labels, duplicate floor rows, parent-only nonmatches, optional category/table absence, pagination and read-only assertions on both mysqli/PDO implementations. Actual HTTP/browser tests cover detail/card display, floor search and desktop/mobile layout. Health build: `2026-11-05.agencies.7`.


## Phase 1 property classification extension (11 November 2026)

Both authenticated POST endpoints `/v1/customer-owned-properties.php` and `/v1/verify-current-booking.php` add `inventory_property_type`, nullable raw `maintenance_status`, `classification_status`, and the effective `property_type`. This is additive; identity, ownership, pagination and string Head/Subhead flags remain unchanged.

The inspected SGC Core PHP schema uses `m_property_maintain.property_id = pro_inventory.id` plus matching `company_id`. Its authoritative field is `status`, with exact values `plot`, `constructed`, `under_constructed`. A single current `active='yes' AND exclude='no'` row with `status='constructed'` yields `property_type='House'`. The optional `property_type` column on maintenance describes building configuration and is not used as this status.

The same implementation is included in the downloaded Zalmi API folder; the user confirmed identical live schemas. A separate Zalmi Core PHP/database dump was not available for independent inspection. Missing schema returns `classification_status='unavailable'`; no row gives `not_recorded`; duplicate current rows give `ambiguous`; unexpected raw values give `unknown`. These cases preserve inventory classification, or generic `Property` if none exists. They never fabricate a House/Plot or grant ownership. The properties endpoint performs one batch classification query per existing page.

API files in each folder: `index.php`, `src/PropertyClassification.php`, `src/CustomerController.php`, `src/CurrentBookingVerification.php`. Upload all four together into the existing API folder on the appropriate server. No ERP database migration/business write is required. These changes have been tested locally with authenticated HTTP requests against isolated actual SQL fixtures; live deployment has not been performed or verified.
