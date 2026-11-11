# Shalimar Properties customer portal

The existing customer design now uses server-side CNIC/mobile OTP authentication, protected customer sessions, verified ERP identity links and on-demand ownership adapters. The old browser demo sign-in and fixed OTP are not used by the live routes.

Open locally: http://localhost/shalimar-properties/public/customer/login

## Live API upload required

Both downloaded SGC/Zalmi APIs now implement bearer-authenticated customer identity/current-ownership endpoints. Their paths are configured locally. Upload the matching `sgc-smp-api-agencies.zip` / `zalmi-smp-api-agencies.zip` from `.qa/erp-api-upload/` to each live host before retrying verification. Health build: `2026-11-05.agencies.7`. Missing live endpoints still cause temporary unavailability. No portal-to-remote-database connection or mock ownership fallback is used.

Read [CUSTOMER-ERP-API-CONTRACT.md](CUSTOMER-ERP-API-CONTRACT.md) for the exact endpoint contracts and actual inspected schema references.

Read [CUSTOMER-ACTIVATION.md](CUSTOMER-ACTIVATION.md) for implementation files, migration, OTP routes, SMS integration, configuration, verification and remaining work.

The migration has run locally. No bulk ERP property records are saved by login, dashboard refresh or resale preparation. The live resale route rechecks a selected property's ownership/eligibility and provides a review integration point; actual listing submission remains unavailable until that workflow is implemented.

## Verification

```text
php .qa/verify_customer_apis.php
php .qa/customer_api_path_checks.php
php .qa/customer_auth_checks.php
python -u .qa/verify_customer_live.py
```

These run against scoped test databases and test-only injected ERP/SMS dependencies. They do not send real SMS and are not a production OTP bypass.

Current in-process bookings now remain in My Properties with their ERP approval status visible. They cannot be prepared for resale until their ERP processing flag is exactly `no`. This corrects the previous API omission of the 22 Smart City document/approval-pending bookings. See the count reconciliation in CUSTOMER-ERP-API-CONTRACT.md.

Floor metadata now appears on property cards, details and the verified resale review, and is searchable. The ERP floor relationship uses pro_inventory_floor.sub_property_id, not the tower parent ID. Deploy the floor-fix API package before expecting live floor values.

Authorized agency selection is now integrated with the verified resale review. It uses confirmed local mappings and the property's own ERP source; cross-source, unmapped, revoked and portal-disabled choices are rejected. See AUTHORIZED-AGENCIES-IMPLEMENTATION.md.
