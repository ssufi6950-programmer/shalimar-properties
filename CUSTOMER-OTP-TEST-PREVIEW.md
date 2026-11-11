# Customer OTP testing preview

Updated locally on 7 November 2026.

The requesting customer's browser can display its generated OTP above the input fields when temporary testing preview is enabled. This now supports localhost, LAN and live hosts.

## Setting

Set this in the portal's environment file:

    customerPortal.showOtpPreview = true

Enabled in the current local environment. Add the same setting to the live portal's own environment file when uploading these changes. No live deployment was performed.

To hide every temporary preview before actual customer use:

    customerPortal.showOtpPreview = false

The earlier loopback-only setting remains supported for compatibility. It is set to false locally:

    customerPortal.showLocalOtp = false

Do not copy local database credentials or the entire local environment file to live just to change this setting.

## Screens covered

- Sign-in, signup and ERP identity-link verification share the same OTP input screen.
- New resale, Edit and Republish share the current-booking owner OTP panel.
- Resends replace the displayed code with the new challenge code.
- Expired codes are hidden; verification, invalidation and logout remove the associated session preview.

The label is **Testing OTP**. This intentionally exposes the verification code in the requesting browser while the test setting is enabled.

Codes remain bound to the existing session and exact challenge. Another session cannot retrieve them. With the explicit all-host `showOtpPreview` testing flag, unmatched identity challenges also display their generated testing code, with a clear note that the CNIC/mobile did not match, no SMS was sent and the code cannot sign the user in. The legacy local-only flag keeps its previous matched-identity-only behavior. This testing explanation is not exposed with preview disabled. Code expiry, attempts, ownership checks, final publication checks and SMS delivery behavior remain enforced. OTPs are not added to local storage, listing records or logs.

## Files

Application:

- app/Config/CustomerPortal.php
- app/Services/LocalOtpPreview.php
- app/Services/CustomerOtpService.php (matched and unmatched testing preview)
- app/Views/customer/auth/verify_otp.php
- public/assets/customer/js/customer-live.js
- public/assets/customer/js/resale-live.js
- .env (only the two preview settings)

QA:

- .qa/customer_test_env.php
- .qa/verify_local_otp.py
- .qa/local_otp_guard_checks.php

No database migration or ERP API upload is needed.

## Verification

The guard checks cover explicit live/LAN enable, disabled preview and compatibility with the old local-only behavior. The HTTP tests use disposable portal schemas and fake ERP/SMS dependencies to check generated login OTP, placement above inputs, session isolation, remote-host preview, resend and property OTP verification. No actual SMS is sent by these tests.

Results: **10 HTTP checks passed in all-host mode**, **10 in legacy local mode**, plus **15 guard cases** and PHP/JavaScript syntax checks.

Logs: .qa/all-host-otp-preview.log and .qa/legacy-local-otp-preview.log.

## Live follow-up: missing preview

The unmatched identity branch previously withheld the preview even when the all-host testing setting was enabled. It now stores that challenge's generated code and explanation only while the explicit testing setting is on. The displayed unmatched code remains unable to authenticate, create an account or send SMS. Resend replaces both the displayed code and explanation. Expiry/terminal states clear both.

Upload the six prepared files from `deploy/live-otp-preview-fix/` to their matching paths under `/home/propertys_user11/www/`. The two JavaScript copies belong in `public/assets/customer/js/` and `html/public/assets/customer/js/`. Keep the existing live environment, including `customerPortal.showOtpPreview = true`; do not overwrite it with the local database settings. Start a fresh sign-in after upload: earlier challenge hashes cannot be converted back into OTPs by refreshing an already open page.

Follow-up verification uses disposable schemas and fake ERP/SMS only. The HTTP preview checks cover unmatched display/resend, rejection of its exact displayed code, no SMS, legacy behavior, valid login, session isolation and property OTP. Authentication/ownership checks and the 15 preview guards also passed. Latest logs: `.qa/all-host-otp-preview-followup.log`, `.qa/legacy-otp-preview-followup.log`, `.qa/otp-preview-auth-followup.log`.
