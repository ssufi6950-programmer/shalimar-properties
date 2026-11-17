# Customer Login WhatsApp OTP — Flow Report

## Scope

This report documents the customer sign-in OTP path in the current local Shalimar Properties CI4 source. It does not verify the live server's current `.env`, Meta account configuration, or phone delivery.

## Request-to-message flow

1. The customer submits CNIC and ERP-registered mobile on the customer sign-in page.
2. The customer controller calls `CustomerOtpService::request()`.
3. The service validates the CNIC/mobile format, applies login request limits, checks the matching ERP identity and requires a current owned property. If ERP sources are unavailable, no OTP send is attempted.
4. It creates a `customer_otp_challenges` record with an OTP hash and encrypted request context. The OTP itself is not stored as plaintext in this table.
5. Unless login preview-only mode is active, the service applies the shared outbound OTP rate limit and calls `JazzCustomerSms::sendCode()`.
6. `JazzCustomerSms` resolves the destination. If `sms.testMode=true`, it routes to the configured test recipient (subject to the approved-host/network checks); otherwise it uses the verified ERP mobile.
7. SMS and WhatsApp are attempted independently according to their enabled settings. If either channel succeeds, the combined sender treats the send as successful. If both fail or are disabled, the request fails and the challenge is marked failed.

## WhatsApp request

`MetaWhatsappOtp::sendCode()` sends an HTTPS POST to:

`https://graph.facebook.com/{whatsapp.apiVersion}/{whatsapp.phoneNumberId}/messages`

It authenticates with the configured Meta bearer token and submits a WhatsApp template message containing:

- `messaging_product`: `whatsapp`
- `to`: resolved destination number
- `type`: `template`
- Template name and language from WhatsApp configuration
- Body parameter 1: `Your Shalimar Properties login verification code is {OTP}. This code expires in 5 minutes.`
- Optional body parameter 2: the configured `whatsapp.secondBodyText`

The access token is sent in the HTTPS Authorization header. It is not written to the application logs. The request payload itself, including OTP and destination, is not logged.

## Response and delivery status

A successful Meta response must contain a WhatsApp message ID (`wamid`). That indicates the API accepted the message request; it does not prove delivery to the handset.

When configured response logging is enabled, the CI4 application log records the normalized destination number and HTTP status. The provider response body still redacts the configured token, phone number and OTP. WhatsApp rejection and transport-error lines also include the normalized destination number. A separate delivery log records the `wamid` and initial `accepted` state. Later WhatsApp webhook callbacks can update the state to `sent`, `delivered`, `read` or `failed`.

A 200/accepted response should therefore be correlated by its `wamid` with the later delivery-status webhook log to determine whether delivery occurred.

## SMS fallback / parallel channel

If `sms.enabled=true`, `JazzCustomerSms` also sends the login text to the Jaageer SMS endpoint over HTTPS. The SMS text is:

`Your Shalimar Properties login verification code is {OTP}. This code expires in 5 minutes. Do not share it.`

The gateway expects the exact success body `Message Sent Successfully!`. Successful SMS request bodies and responses are not currently logged. Transport failures log cURL error number, HTTP status and timing; non-200 responses log the status; rejected requests omit the response body.

## Log locations

- CI4 application log: `writable/logs/log-YYYY-MM-DD.php`
  - WhatsApp response, if `whatsapp.logResponses=true`
  - WhatsApp transport/rejection errors
  - SMS transport/status/rejection errors
  - Combined customer OTP delivery failure
- WhatsApp delivery-status log: `writable/logs/whatsapp-delivery-YYYY-MM-DD.log`
  - Message ID (`wamid`), status, provider timestamp and numeric error codes
- OTP database state: `customer_otp_challenges`
  - Challenge metadata and delivery state; OTP is hashed and request context is encrypted, then cleared/consumed according to the verification lifecycle

The delivery-status log does not contain the phone number or OTP. The CI4 application response/error logs now include the WhatsApp destination phone number to help correlate send attempts; protect those logs and do not share them publicly.

## Current local configuration observation

At report time, the local `.env` has `whatsapp.enabled=true`, `whatsapp.logResponses=true`, and `sms.enabled=false`. These are local settings only and do not establish the live server's configuration.

## Relevant implementation files

- `app/Controllers/Customer/Auth.php` — customer OTP request route/controller entry point
- `app/Services/CustomerOtpService.php` — ERP identity/property validation, OTP challenge storage, rate limit and delivery orchestration
- `app/Services/JazzCustomerSms.php` — destination routing and SMS/WhatsApp channel orchestration
- `app/Services/MetaWhatsappOtp.php` — Meta WhatsApp request, response validation and optional redacted response log
- `app/Services/WhatsappDeliveryLog.php` — WhatsApp message-ID and delivery-status log
- `app/Controllers/WhatsappWebhook.php` — receives delivery-status callbacks
- `app/Config/Whatsapp.php` — WhatsApp defaults and response logging setting
- `app/Config/Sms.php` — SMS and test-routing settings

## Verification limits

No message was sent while preparing this report. No live configuration, Meta delivery dashboard, server log, webhook receipt, or handset delivery was checked.
