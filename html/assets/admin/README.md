# Shalimar resale workspace

ERP Sources is now a real CI4/MySQL module: `admin.php?tab=sources` redirects to the protected `/public/admin/erp-sources` page. See the project-root `ERP-SOURCES.md` for configuration, verification and the complete file inventory. The remaining screens below are frontend demonstrations.

Open `html/admin.php` through the existing PHP web server. This is a frontend demonstration with static sample records. It never loads the website bootstrap or reads or writes its data stores. Actions only change JavaScript objects for the current page; refreshing resets them. Only theme and desktop sidebar preferences use browser local storage.

The original operational admin is preserved at `html/admin-classic.php`, including its authentication and existing workflows. Its internal links point to that preserved page. The demo profile menu includes a Classic admin link. `html/includes/config.php` is unchanged.

- Reusable layouts, views, components and sample data: `html/includes/admin/`.
- Design tokens and layout: `css/admin.css`.
- Shared components and forms: `css/components.css`.
- Tablet/mobile overrides: `css/responsive.css`.
- Workspace interactions: `js/admin.js`.
- Table schemas, DataTables integration, filters and demo editing: `js/tables.js`.
- ApexCharts visualizations with SVG fallback: `js/dashboard.js`.

Every sidebar destination has a demo screen. CSV exports use the current filtered sample records, while dashboard exports contain clearly marked illustrative aggregates. Report date filtering applies to dated records. Images are existing project assets; media form controls preview browser-local files without uploading.

The vendor directory contains locally served, pinned Bootstrap 5.3.3, Bootstrap Icons 1.11.3, jQuery 3.7.1, ApexCharts 3.49.1, DataTables 2.1.8, Select2 4.0.13 and Inter. Bootstrap, Bootstrap Icons, jQuery, ApexCharts 3.49.1, DataTables and Select2 use MIT licensing; Inter uses the SIL Open Font License. Upstream copyright notices are retained in distributed files. `download-vendor.py` restores those assets from their upstream distribution URLs using Python 3.

Local browser verification uses `.qa/verify_admin.py`, the installed Microsoft Edge in headless mode, and PHP serving `html/` at port 8173. No browser automation package is required. Verification screenshots are written under `.qa/`.
