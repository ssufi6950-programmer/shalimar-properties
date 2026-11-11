# Admin security implementation

## Inspection findings, before structural changes

Inspected 2026-11-04: PHP 8.2.12, CodeIgniter 4.7.6, MySQLi persistence in the existing portal. The database initially contains `erp_sources` and `migrations`. Existing ERP source records and outbound connection configuration must be preserved.

`html/admin.php?tab=sources` is a legacy redirect to protected CI4 ERP Sources. Other tabs are frontend prototypes rendered directly by PHP without authentication. `html/admin-classic.php` has a separate configured-password login and file-backed business workflows. Copied sample admin entry points and `html/includes/home/admin.php` also need closing. No admin password, session role or permission should remain trusted merely because it exists in legacy PHP or browser storage.

CI4 already provides login/logout, a server-side file session, session CSRF with rotating AJAX tokens, and an admin filter. These will be extended rather than adding another admin identity system. Auto-routing is already disabled. Customer routes use a separate demo-only filter and are not admin routes. The ERP connection service retains its existing host approval, public-address checks, TLS and timeout policy.

Implementation will add database-backed identities, role/permission assignments, revocation versions, audit records and login throttling. Existing prototype screens will remain clearly identified as prototypes behind CI4 permissions. Classic operational workflows will be routed through the same CI4 session and authorization. Existing frontend styling and ERP source data will remain intact. No SGC or Zalmi ERP project is in scope.

## Open and initialize

Admin sign-in: <http://localhost:8081/shalimar-properties/public/admin/login>

The original `html/admin.php?tab=sources` still redirects to the CI4 ERP Sources page. All original prototype tab links now resolve to protected CI4 screens. Classic admin is available at `public/admin/classic` with the same database identity, account status and permissions. Customer routes remain under their separate existing demo policy.

Executed locally:

```text
php spark migrate
php spark db:seed AdminSecuritySeeder
```

Migration `2026-11-04-000002_CreateAdminSecurity` adds `admin_users`, `admin_roles`, `admin_permissions`, `admin_role_permissions`, `admin_user_roles`, `admin_audit_logs` and `admin_login_attempts`. It does not change ERP Sources or existing file-backed business records. Usernames/emails use database case-insensitive unique constraints and are normalized to lowercase. Email is optional; a supplied email must be valid and unique. Role/permission and user/role pairs are unique and protected by foreign keys.

No permanent administrator is seeded and no old demo password/hash is imported. Old configured admin credentials no longer authorize admin requests. From this portal directory, create your first real account:

```text
php spark admin:create-superuser
```

The command prompts for name, username and optional email, then generates and displays a cryptographically random temporary password once. It stores only a secure hash, assigns Super Admin and requires a password change at first login. Keep the console private; do not capture that output in shared logs. Windows users can invoke `F:\xampp\php\php.exe spark admin:create-superuser` if PHP is not on PATH. The portal requires PHP 8.2+, independently of the Core PHP SGC ERP's PHP version.

After any administrator exists, bootstrap refuses new accounts. Further users are created through authorized Users management. Server operators can recover an existing Super Admin through:

```text
php spark admin:create-superuser --recover
```

Enter that existing Super Admin username and confirm `RECOVER`. Recovery reactivates it, replaces its password with a strong temporary password and revokes its old sessions. No public registration, security questions or email-reset delivery is provided. No email delivery system is configured; password recovery is administrator-assisted or CLI-only.

## Identity and session security

- The existing CI4 `Auth`, `AdminAuth` filter, server-side sessions and CSRF flow have been extended. `AdminIdentity` was removed; no parallel credential login remains.
- Passwords use Argon2id when supported, otherwise bcrypt cost 12. Successful login rehashes outdated hashes. New/reset passwords must be 12–72 bytes with uppercase, lowercase, number and symbol; user-provided hashes and privilege flags are ignored.
- Login accepts username or email. Inactive/suspended accounts and incorrect credentials get the same message. Database-backed failure limits apply to account and IP over ten minutes. Username/email aliases share the same account bucket; successful login clears that account's failures, preserving failures for other accounts on the IP.
- CI4 file sessions reside in the protected `writable/session` directory. Session IDs regenerate on authentication, password changes and CI4's normal rotation. Cookies are HttpOnly and SameSite Lax; HTTPS base URLs enforce Secure cookies. Local HTTP is supported for XAMPP only. No identity or token is stored in localStorage.
- Every protected request checks the database account status and `auth_version`, plus idle and maximum lifetime. Role assignments, role edits, account edits/status changes and assisted password resets revoke affected sessions. A self password change preserves only the current regenerated session.
- Defaults are 30-minute idle expiry and eight-hour maximum lifetime. Override through CI4 configuration, for example `adminsecurity.idleTimeout`, `adminsecurity.maximumLifetime`, `adminsecurity.loginWindow`, `adminsecurity.ipAttempts` and `adminsecurity.accountAttempts`. Keep sane positive values.
- All state-changing admin requests use the existing CI4 session CSRF policy. Forms contain CSRF fields; AJAX sends the rotating header and updates the hidden logout token. Failed requests do not automatically replay writes.
- Admin responses are no-store. Login redirects are fixed server-side destinations, so client `next`/redirect payloads cannot create an open redirect.

## Authorization and protected route inventory

`AdminAuth` is registered against `admin` and `admin/*`, so an admin route outside the existing group is still authenticated. `AdminPermission` uses a central endpoint map and denies unregistered endpoints by default. Auto-routing remains disabled. Adding an admin action requires registering its permission before it can be reached. Controllers and the account/security services independently check sensitive writes. ERP connection testing also checks permission at service level for web requests; CLI maintenance uses its separate server-operator trust boundary.

Guests receive 401 JSON for AJAX/JSON calls or a sign-in redirect for browser pages. Signed-in users without access receive 403 JSON with `access_denied`, or a branded Access Denied page. Temporary-password users are restricted to change-password/logout until completion. Profile requests cannot change roles, status or permissions.

| Methods | CI4 route | Required permission / policy |
| --- | --- | --- |
| GET, POST | `/admin/login` | Public sign-in; CSRF and failure throttling |
| POST | `/admin/logout` | Authenticated; CSRF |
| GET | `/admin`, `/admin/dashboard` | `dashboard.view` |
| GET | `/admin/workspace/{tab}` | Exact tab registry; unknown tabs denied |
| GET, POST | `/admin/classic` | Exact legacy tab/action map; CSRF on writes |
| GET | `/admin/listing-preview?id={id}` | `resale_listings.view`; protected non-public listing preview |
| GET, POST | `/admin/profile` | Current active account only; role fields ignored |
| GET, POST | `/admin/change-password` | Current active account; current password verified |
| GET | `/admin/users`, `/admin/users/data`, `/admin/users/{id}` | `users.view` |
| POST | `/admin/users` | `users.create`; role assignment also needs `users.assign_roles` |
| POST | `/admin/users/{id}` | `users.edit`; status and role changes need their respective permissions |
| POST | `/admin/users/{id}/status` | `users.deactivate` |
| POST | `/admin/users/{id}/password` | `users.reset_password` |
| GET | `/admin/roles`, `/admin/roles/data`, `/admin/roles/{id}` | `roles.view` |
| POST | `/admin/roles` | `roles.create`; supplied permissions also need `roles.manage_permissions` |
| POST | `/admin/roles/{id}` | `roles.edit`; supplied permissions also need `roles.manage_permissions` |
| POST | `/admin/roles/{id}/delete` | `roles.delete`; unused custom roles only |
| GET | `/admin/audit-logs` | `audit_logs.view` |
| GET | `/admin/audit-logs/export` | `audit_logs.export`; escaped CSV, at most 10,000 filtered rows |
| GET | `/admin/erp-sources`, `/data`, `/options`, `/{id}` | `erp_sources.view` |
| POST | `/admin/erp-sources` | `erp_sources.create` |
| POST | `/admin/erp-sources/{id}` | `erp_sources.edit` |
| POST | `/admin/erp-sources/{id}/status` | `erp_sources.deactivate` |
| POST | `/admin/erp-sources/{id}/test` | `erp_sources.test_connection` |

Changing ERP authentication/credential references additionally requires `erp_sources.manage_credentials`. Changing a credential-bearing source's base URL requires it too. The edit endpoint independently enforces status permission, preventing modified AJAX payloads from bypassing the status endpoint. Names, URLs, source IDs, code-reference locks, persistence and outbound safeguards retain their existing behavior.

Legacy tab mapping covers dashboard, analytics, projects, project form, properties/details, resale/details, customers, sources, mapping, heads, synchronization, agents, agencies, inquiries, reports, users, roles, settings, activity and components. Classic tab/action mappings additionally protect operational approvals, image review, transaction entry, agency publishing rights, upload rules, project updates/uploads and record viewing. Destructive legacy `reset_demo` is deliberately denied. Classic workflow uploads and file storage retain their existing validation and are accessible only behind the mapped CI4 action permission and CSRF filter.

All copied `html/samples/**/admin.php` entries redirect to the same protected application and refuse direct POST mutations. `html/includes/**` (including the copied home admin), copied sample include trees and `html/bootstrap.php` are denied by the root `.htaccess`. Public listing pages no longer trust an old `is_admin` session flag. Classic previews go through the dedicated protected CI4 route. The legacy classic file cannot render directly without a server-defined authorization guard.

## Permission registry and roles

The complete matrix is in `Config\AdminSecurity::MODULES` and is seeded into `admin_permissions`. The Roles screen renders it from the database, grouped by module, including each module-specific action. Select-all applies only to that module. Unknown permission keys, browser-supplied system flags and grants outside the actor's own permissions are rejected.

| Module | Registered actions |
| --- | --- |
| Dashboard | view |
| ERP Sources | view, create, edit, deactivate, test_connection, manage_credentials |
| ERP Mapping | view, create, edit, delete |
| ERP Synchronization | view, run, view_logs, manage_settings |
| Projects | view, create, edit, delete, publish |
| Properties | view, create, edit, delete, export |
| Resale Listings | view, edit, approve, reject, deactivate, export |
| Customers | view, edit, export |
| Agents / Agencies | view, create, edit, deactivate |
| Leads | view, assign, edit, export |
| Reports | view, export |
| Users | view, create, edit, deactivate, assign_roles, reset_password |
| Roles | view, create, edit, delete, manage_permissions |
| Settings | view, edit |
| Audit Logs | view, export |
| Classic Transactions | view, create |

Default roles:

- **Super Admin:** all registered permissions. The role is always active and its slug/permission set cannot be weakened through the UI. Re-running the seeder attaches newly registered permissions to it.
- **Admin:** operational permissions; excludes user/role management, settings/security and ERP credential management.
- **Manager:** selected property/listing/customer/lead/report operations.
- **Operator:** limited property/listing/customer reads and lead tasks.
- **Auditor:** dashboard, reports and audit-log reads.

Other system roles can be edited by Super Admin, preserving their identity; normal administrators cannot modify them. Custom roles are dynamically manageable. Effective access is the union of active assigned roles, subject to account status. The seed is idempotent and preserves existing role edits.

Account/role mutations lock the protected Super Admin role in a transaction before checking invariants, so competing requests cannot remove the final active Super Admin. Lower administrators cannot edit/reset/reactivate Super Admin accounts, including inactive or suspended ones. Non-Super Admins cannot change their own assigned roles or grant roles/permissions outside their own access. Users are deactivated rather than hard-deleted; the model rejects hard deletion. Referenced roles and protected system roles cannot be deleted.

## Audit logging and retention

`AdminAuditService` records login success/failure/throttling, logout, user/profile/password changes, role assignments and permission changes, authorization denials, ERP source writes/status/credential-reference changes and connection checks, audit exports and CLI bootstrap/recovery. Future unrelated modules are not implemented; their sensitive actions must call this service when they are built.

Audit writes participate in critical write transactions; a failed audit write prevents the associated account, role or ERP mutation from committing. Login audit/storage failures prevent authentication. Safe before/after data is allowlisted; passwords, hashes, raw request bodies, API secrets, tokens, OTPs, session IDs and CNIC fields are excluded. CNIC-shaped identifiers are redacted from approved text fields, URL user information/query/fragment is omitted, and untrusted user-agent text is stored as a SHA-256 digest. IP, actor, action, target, timestamp and a server-generated request ID are included. Normal models/UI cannot edit/delete audit records. Audit CSV fields are protected against spreadsheet formula injection.

Schedule this CLI command daily to remove old login-attempt rows:

```text
php spark admin:prune-security
```

Audit records remain until explicitly archived by a server operator. Default eligibility is 365 days (`adminsecurity.auditRetentionDays`, minimum 30). Archive into a new file outside the application's parent directory/web root, for example a private backup directory under `F:\backups` rather than `F:\xampp\htdocs`:

```text
php spark admin:prune-security --archive F:\backups\admin-audit-archive.jsonl
```

The archive directory must already exist, be private and writable by the operator. Existing files are refused. Matching audit records are removed only after the archive is written and flushed. Apply appropriate filesystem ACLs and backup access controls. No ordinary admin UI has this archival/delete capability.

## Verification and practical limits

```text
python -u .qa/verify_admin_security.py
python -u .qa/verify_admin_security_browser.py
python -u .qa/verify_admin_cli.py
php .qa/erp_mysql_checks.php
```

The HTTP suite exercises real CI4 requests/MySQL: authenticated login, HttpOnly/SameSite cookies, session regeneration, inactive/suspended blocking, CSRF, guest 401, granular 403, multiple-role union, last Super Admin protection, protected suspended accounts, escalation payloads, user/role writes, SQL-search binding, stored XSS escaping, temporary-password change, assisted reset, revocation after reset/status/role changes, idle/absolute expiry, throttling and logout. It covers all existing workspace/classic pages and ERP create/edit/status/unconfigured test behavior; configured Zalmi testing also succeeds through the real RBAC/audit path. Temporary test identities/roles/sources are removed; audit history is retained.

The browser suite checks show/hide password, the existing layout/sidebar/theme, DataTables, module permission selection, real role persistence, user create/edit/deactivation/reset, XSS-safe table rendering, existing ERP cards/details, desktop/mobile behavior and JavaScript exceptions. Screenshots are under `.qa/rbac-*.png`. It found and verified a fix for a modal timing race that could send an edit as a create.

The CLI suite verifies first-account bootstrap, duplicate refusal and explicit recovery with hashed credentials/session revocation. Its test-only Spark wrapper pins a disposable MySQL schema before configuration initialization and verifies the actual schema before mutation. The initial CLI test did not isolate its temporary account correctly; that exact owned QA account was removed, isolation was corrected, and the suite then passed with zero leftover portal accounts. ERP MySQL/service tests use a separate disposable schema and deterministic transport for DNS/SSRF, credentials, timeouts, references and delete restrictions.

The following limits are intentional: no email-delivery reset flow, no MFA, no implementation of unrelated prototype modules, no automated production deployment, and no claim that SGC's existing live HTTP 500 has been fixed by admin authentication work. Existing public customer UI remains a separate sample-data frontend. Future modules still require their own resource/tenant ownership checks in addition to these module permissions. The archive command and HTTPS production deployment require server-operator setup.

## Deployment

1. Back up the portal code, database, private `.env` and legacy file-backed data. Never upload local credentials or session/cache/log files. Do not modify the separate SGC/Zalmi ERP projects.
2. Deploy the portal changes together, including its root `.htaccess` access restrictions. Install locked Composer dependencies with a compatible PHP 8.2+ runtime. The public document root should be `public/`; retain the explicitly deployed legacy asset/public-site arrangement where required.
3. Set the actual HTTPS `app.baseURL` and trusted proxy policy. Secure cookies follow that base URL. Keep production error display off, private logs/sessions outside public access and filesystem permissions restricted. File sessions support one server or a properly shared/locked filesystem; configure CI4 Redis/database sessions explicitly if scaling to multiple independent web servers.
4. Run `php spark migrate` and `php spark db:seed AdminSecuritySeeder`. The seeder preserves existing ERP data and existing role edits. Verify migrations before reopening admin access.
5. Run `php spark admin:create-superuser` privately if there is no account. Sign in with its generated password and change it immediately. Create additional administrators through Users management. Do not reuse the old demo password.
6. Verify guest redirects/401, restricted-account 403, CSRF, logout, the old Sources URL, users/roles/audit screens, classic workflows, public pages and customer routes. Recheck configured ERP health endpoints with authorized accounts.
7. Schedule login-attempt cleanup and secure audit backups; document the private CLI recovery procedure for trusted operators. Provision database permissions so normal web credentials cannot alter schema or grant privileges. Application audit immutability does not protect against a database administrator.

## Rollback

Prefer restoring a known secure code/database backup together during maintenance. Keep admin entry points blocked until a compatible authentication system is restored; reverting only the new filters would recreate the original public prototype and legacy-password bypasses. Preserve audit archives and records. The auth migration's `down()` drops only its seven auth/security tables in dependency order, so it is destructive to admin accounts/audit history and must be used only after a verified backup and an intentional rollback decision. It does not drop `erp_sources`; never run a blanket migration reset against this portal. ERP connection settings and legacy file-backed business data should be retained.

## File inventory

Created:

- `ADMIN-SECURITY.md`
- `app/Config/AdminSecurity.php`
- `app/Database/Migrations/2026-11-04-000002_CreateAdminSecurity.php`
- `app/Database/Seeds/AdminSecuritySeeder.php`
- `app/Models/AdminUserModel.php`, `AdminRoleModel.php`, `AdminPermissionModel.php`, `AdminAuditLogModel.php`
- `app/Services/AdminAuthService.php`, `AdminPermissionService.php`, `AdminAccountService.php`, `AdminAuditService.php`
- `app/Filters/AdminPermission.php`, `app/Exceptions/AdminAuthorizationException.php`
- `app/Controllers/Admin/Users.php`, `Roles.php`, `Profile.php`, `AuditLogs.php`, `Workspace.php`
- `app/Commands/AdminSuperuser.php`, `AdminSecurityPrune.php`
- `app/Views/admin/layouts/sidebar.php`, `app/Views/admin/errors/403.php`
- `app/Views/admin/security/manage.php`, `profile.php`, `audit.php`, `workspace.php`
- `public/assets/admin/js/security.js`
- `html/includes/admin-entry.php`
- `.qa/rbac_fixture.php`, `admin_cli_entry.php`, `verify_admin_security.py`, `verify_admin_security_browser.py`, `verify_admin_cli.py`

Modified:

- `app/Config/Routes.php`, `Filters.php`, `Services.php`, `Session.php`, `Cookie.php`
- `app/Controllers/Admin/Auth.php`, `ErpSources.php`
- `app/Filters/AdminAuth.php`, `app/Helpers/admin_helper.php`
- `app/Services/ErpConnectionService.php` (authorization/audit integration; existing transport policy preserved)
- `app/Views/admin/auth/login.php`, `app/Views/admin/layouts/main.php`, `header.php`, `app/Views/admin/erp/sources.php`
- `public/assets/admin/js/erp-sources.js`
- `html/admin.php`, `html/admin-classic.php`, `html/includes/bootstrap.php`, `html/includes/admin/data.php`, `navigation.php`, `html/includes/home/admin.php`
- All eight copied `html/samples/**/admin.php` entry points (redirect guard only)
- Root `.htaccess`, `.gitignore`, and `ERP-SOURCES.md` (authentication documentation)

Removed: `app/Libraries/AdminIdentity.php` (the old configured-password identity provider).

The existing `.env` ERP host/path/public-address configuration is preserved. Generated files are private CI4 sessions/cache/logs and ignored Edge test profiles/logs, plus the `.qa/rbac-*.png` screenshots. No permanent test accounts or passwords are left behind.
