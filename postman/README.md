# Customer sign-in Postman setup

Import the two collection JSON files and their matching environment JSON files into Postman. The ERP collection contains separate SGC and Zalmi folders; the Shalimar collection contains portal routes only.

## Keep credentials private

The exported environment templates intentionally contain **blank secret values**. In Postman, select the matching environment and enter tokens, CNIC, mobile and OTP only in the **Current Value** field. Do not put secrets in Initial Value, export the filled environment, or sync/share it. Use the raw bearer token already configured for the relevant ERP source; the SHA-256 hash stored by an ERP API is not a bearer token. Never send the SGC token to Zalmi or vice versa.

## ERP collection order

1. Run SGC or Zalmi **Customer identity match** with the corresponding ERP token.
2. Copy a returned `customer_id` into that source's environment variable.
3. Run that source's **Current owned properties** request. Change `page` to inspect later pages.

The configured default API bases are `https://sgcacc.com/smp-api/v1` and `https://zalmidevelopments.com/smp-api/v1`; change them only if that ERP's active API base differs.

## Shalimar portal collection order

1. Run **Start session and get CSRF** first. Postman's cookie jar retains the portal session, and the collection script captures the CSRF values from the login page or JSON responses.
2. Fill in CNIC/mobile, then run **Request sign-in OTP**. This can send a real SMS/WhatsApp and consume the portal's OTP rate limit.
3. Enter the received OTP in the environment and run **Verify sign-in OTP**. The session cookie is retained for the following requests.
4. Run **Customer dashboard** or **Load one ERP source page through portal**. `source_link_id` is the portal's customer-to-ERP link ID, not an ERP customer ID. Use one link at a time; change `page` for pagination.

All requests preserve the separation: ERP calls use their source bearer tokens, while portal requests use the portal session cookie and CSRF token. Do not send ERP bearer tokens to the portal collection.
