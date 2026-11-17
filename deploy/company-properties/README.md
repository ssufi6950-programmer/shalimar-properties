# Company Properties ? local implementation and FTP handoff

Admin URL: `/public/admin/company-properties` (Admin > Property management > Company Properties).
Local default URL: `http://localhost/shalimar-properties/public/admin/company-properties` (use the actual Apache port).

This is a read-only remote inventory directory, not a listing import or publication action. Both source sections show at most 50 records per request with separate Next links. API queries use the server-configured company identity and **BINARY smp_active = BINARY 'yes'**. Portal validates every response row and rejects a page containing an unselected row. No customer/owner/financial fields are returned. Permission: properties.view. Existing bearer credentials, approved HTTPS hosts, DNS pinning and transport limits are reused. Each source can fail independently.

## Full FTP mappings

Back up every destination first. Merge folders; replace only the listed files. Upload ERP files first and then Portal files. Retain private API configuration. SGC root follows the recorded SGC ownership deployment path; confirm it in the current FTP account before uploading. Zalmi local folder `zalmi-smp-api` is served live as `smp-api`.

| Full local package path | Full live destination |
| --- | --- |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Config/Routes.php` | `/home/propertys_user11/www/app/Config/Routes.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Config/AdminSecurity.php` | `/home/propertys_user11/www/app/Config/AdminSecurity.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Filters/AdminPermission.php` | `/home/propertys_user11/www/app/Filters/AdminPermission.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Helpers/admin_helper.php` | `/home/propertys_user11/www/app/Helpers/admin_helper.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Services/ExplorerErpClient.php` | `/home/propertys_user11/www/app/Services/ExplorerErpClient.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Controllers/Admin/CompanyProperties.php` | `/home/propertys_user11/www/app/Controllers/Admin/CompanyProperties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/app/Views/admin/company_properties.php` | `/home/propertys_user11/www/app/Views/admin/company_properties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/portal/html/includes/admin/navigation.php` | `/home/propertys_user11/www/html/includes/admin/navigation.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/smp-api/index.php` | `/home/sgc/www/html/smp-api/index.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/smp-api/src/routes.php` | `/home/sgc/www/html/smp-api/src/routes.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/smp-api/src/CompanyPropertiesController.php` | `/home/sgc/www/html/smp-api/src/CompanyPropertiesController.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/smp-api/v1/company-properties.php` | `/home/sgc/www/html/smp-api/v1/company-properties.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/zalmi-smp-api/index.php` | `/home/zalmipk_user/www/html/smp-api/index.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/zalmi-smp-api/src/routes.php` | `/home/zalmipk_user/www/html/smp-api/src/routes.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/zalmi-smp-api/src/CompanyPropertiesController.php` | `/home/zalmipk_user/www/html/smp-api/src/CompanyPropertiesController.php` |
| `D:/xampp/htdocs/shalimar-properties/deploy/company-properties/zalmi-smp-api/v1/company-properties.php` | `/home/zalmipk_user/www/html/smp-api/v1/company-properties.php` |

## Requirements and verification

- Both ERP pro_inventory tables must already have a string/enum smp_active column supporting yes/no. If absent, the endpoint fails closed; no SQL has been executed and no schema is changed by this package.
- Existing API company configuration must resolve exactly one companies.name; existing bearer tokens remain private and unchanged.
- GET `{configured ERP API base URL}/company-properties.php?after_id=0`. Cursor: next_after_id; fixed 50-record pages. Only read-only SELECTs.
- All 16 source PHP files linted successfully. Mock database strict-selection and 50-record keyset pagination checks passed; static Admin permission check passed. The two endpoint implementations are identical. Real authenticated ERP fetch, real database contents and browser UI are unverified.
- No live upload or SQL execution. No automatic listing creation. Refresh starts a new live read; no inventory cache or local inventory table is created.


Combined-table follow-up: current package copies include the summary update. For the four-file update paths, owner rules and focused checks see `D:/xampp/htdocs/shalimar-properties/deploy/company-properties-summary/README.md`.
