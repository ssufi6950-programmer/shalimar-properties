# Agent assignment WhatsApp notification

Local deployment handoff only. Nothing in this package has been uploaded or run against live systems.

## Behavior

When Admin approves a listing assigned to a Shalimar Agent, the portal queues a WhatsApp template notification and sends it after the approval transaction commits. SMS is queued only when `sms.enabled=true`; WhatsApp uses its own `whatsapp.enabled` setting and is independent of SMS. Both use the existing SMS test-recipient override when `sms.testMode=true`.

WhatsApp template: `shalimar_p_dealer_message` (language `en`). Body variables are agent name, property number plus category, and `Shalimar Group`. The existing OTP template configuration is untouched. “Accepted” means Meta accepted the send request; it does not confirm delivery.

## Manual database step — required before source upload

Select the SMP Portal database in phpMyAdmin and run:

`D:\xampp\htdocs\shalimar-properties\deploy\agent-assignment-whatsapp\database\sql\create-agent-assignment-whatsapp.sql`

This creates only `listing_assignment_whatsapp`; no existing rows/tables or ERP data are changed. Do not upload the app code until this table exists, because approval creates the notification record in the same transaction as the listing approval.

## FTP file map

Upload each package file to the matching live path:

| Package file | Live destination |
| --- | --- |
| `app/Config/Routes.php` | `/home/propertys_user11/www/app/Config/Routes.php` |
| `app/Config/Whatsapp.php` | `/home/propertys_user11/www/app/Config/Whatsapp.php` |
| `app/Controllers/Admin/ListingReview.php` | `/home/propertys_user11/www/app/Controllers/Admin/ListingReview.php` |
| `app/Services/AdminListingReview.php` | `/home/propertys_user11/www/app/Services/AdminListingReview.php` |
| `app/Services/AdminListingReviewReadModel.php` | `/home/propertys_user11/www/app/Services/AdminListingReviewReadModel.php` |
| `app/Services/AgentAssignmentWhatsapp.php` | `/home/propertys_user11/www/app/Services/AgentAssignmentWhatsapp.php` |
| `app/Services/JazzCustomerSms.php` | `/home/propertys_user11/www/app/Services/JazzCustomerSms.php` |
| `app/Views/admin/listing_review/assignment_sms.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/assignment_sms.php` |
| `app/Views/admin/listing_review/detail.php` | `/home/propertys_user11/www/app/Views/admin/listing_review/detail.php` |
| `public/assets/admin/js/listing-review.js` | `/home/propertys_user11/www/public/assets/admin/js/listing-review.js` |
| `html/public/assets/admin/js/listing-review.js` | `/home/propertys_user11/www/html/public/assets/admin/js/listing-review.js` |

The SQL file is for phpMyAdmin; do not FTP it as an application file.

## Configuration

The sender reuses the existing WhatsApp Cloud API token, phone-number ID, API version, enabled flag, and the SMS test-routing configuration. The assignment template defaults to the name/language above. Optional overrides are `whatsapp.agentAssignmentTemplateName` and `whatsapp.agentAssignmentLanguageCode`. Do not change `whatsapp.templateName`, which remains for customer OTPs.

## Admin check

After deployment, approve a Shalimar Agent listing and check the review page’s **Agent assignment WhatsApp** status. It displays accepted separately from delivered. A retry button appears only for a known failed request; uncertain requests are not blindly retried.
