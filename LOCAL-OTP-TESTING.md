# Local OTP preview

For local testing, `customerPortal.showLocalOtp = true` is enabled in this machine's private `.env`. The application default is false.

Request a **new** code after enabling this feature. Existing database OTP hashes cannot be recovered for display.

Login/ERP identity verification shows the actual generated code above the six input boxes. Property/resale verification shows its own code above the property OTP input. Resend updates the code. Verification, expiry, attempt limits, fresh booking checks and the existing SMS delivery/test-recipient behavior are preserved.

The preview requires all three: an explicit config opt-in, a localhost/loopback configured portal URL and a loopback request with a local Host header. Forwarded IP headers cannot enable it. It remains unavailable for a live domain or a remote request even if the flag is accidentally carried over.

`LocalOtpPreview` keeps only the current challenge's short-lived code in its own session for each purpose. No plaintext OTP column or OTP logging was added. Codes are removed on successful verification/invalidation and customer session reset. Customer responses retain their existing private/no-store policy. No ERP or database migration is needed.

To turn off the display, set `customerPortal.showLocalOtp = false` in `.env`.

Verified with `.qa/local_otp_guard_checks.php` (11 gate cases), `.qa/verify_local_otp.py` (10 actual HTTP checks), `.qa/customer_auth_checks.php` and `.qa/resale_ownership_checks.php`. Tests use disposable portal schemas and fake ERP/SMS transports; no real SMS was sent. PHP and JavaScript syntax checks passed.
