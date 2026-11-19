# Customer booking verification API

Read-only authenticated endpoint for an ERP to confirm that a customer, property and current booking belong together, then retrieve the source-specific representative saved on that customer's Portal listing.

## Endpoint

`POST https://shalimarproperties.com/public/api/v1/customer-booking/verify`

Deployment note (19 November 2026): the controller and route are deployed to live. The route was confirmed by a POST returning HTTP 401 when called without a bearer token. Set `customerBookingApi.token` in the live `.env` before sending authenticated requests; successful ERP-backed verification has not yet been performed.

The local XAMPP URL is `http://localhost:8081/shalimar-properties/public/api/v1/customer-booking/verify` (adjust if the CI4 public directory is mapped differently).

Configure `customerBookingApi.token` in the Portal server's `.env` as a unique random secret of at least 32 characters. Send it in `Authorization: Bearer <secret>`. Do not put the token in browser JavaScript, source control, or an ERP URL. The endpoint does not require a browser session or CSRF token.

## Request

```http
POST /public/api/v1/customer-booking/verify
Authorization: Bearer YOUR_SERVER_TO_SERVER_SECRET
Content-Type: application/json
```

```json
{
  "customer_id": "5756623",
  "property_id": "3634",
  "booking_id": "7113",
  "source": "SGC"
}
```

- `customer_id`: customer ID in the calling ERP. The Portal resolves it through the verified, source-specific customer link; callers do not need to know the Portal's internal customer ID.
- `property_id` and `booking_id`: IDs from the selected ERP source.
- `source`: exactly `SGC` or `ZALMI`.

The Portal checks that the customer has a verified identity for that source and a matching active listing, then asks that same ERP source's configured current-booking endpoint to confirm the customer/property/booking tuple. Configure `customerPortal.bookingPaths` in `.env` and the corresponding approved ERP API credentials before use.

## Success examples

Shalimar agent listing:

```json
{
  "success": true,
  "verified": true,
  "source": "SGC",
  "customer_id": "5756623",
  "portal_customer_id": "11",
  "property_id": "3634",
  "booking_id": "7113",
  "selling_method": "shalimar_agent",
  "representative": {
    "type": "shalimar_agent",
    "portal_agent_id": "7",
    "erp_agent_link_id": "18",
    "erp_ledger_id": "284",
    "erp_dealer_id": "91",
    "erp_agency_id": "12"
  }
}
```

Here `erp_ledger_id` is the Shalimar agent's ledger ID in the requested ERP. Values above are illustrative only.

Authorized agency listing:

```json
{
  "success": true,
  "verified": true,
  "source": "ZALMI",
  "customer_id": "9291",
  "portal_customer_id": "11",
  "property_id": "925",
  "booking_id": "2084",
  "selling_method": "authorized_agency",
  "representative": {
    "type": "agency",
    "portal_agency_id": "4",
    "erp_agency_link_id": "22",
    "erp_agency_id": "35",
    "portal_dealer_id": "16",
    "erp_dealer_ledger_id": "611"
  }
}
```

The dealer is the current source-specific dealer assigned to this listing. IDs above are illustrative only. A self-sell listing returns `representative: {"type":"self_sell"}`.

## Error responses

Errors use `{ "success": false, "verified": false, "error": { "code": "...", "message": "..." } }`. Possible codes include `unauthorized` (401), `invalid_request` (400/415), `unsupported_source` (400), `booking_mismatch` (404), `representative_mapping_missing` (409), `erp_verification_unavailable` (503), and `service_unavailable` (503). No IDs are returned on failure.

## Deployment notes

Deploy the controller, route, and environment configuration with the existing CI4 application. Set a new secret independently on each environment. Make sure that environment's ERP source and current-booking API configuration are active and reachable. Responses use `Cache-Control: no-store`; the API does not expose ERP credentials or the customer's registered mobile. No production migration is required for this endpoint.
