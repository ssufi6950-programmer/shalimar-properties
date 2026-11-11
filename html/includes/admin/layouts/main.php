<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Shalimar Properties resale workspace. Interactive frontend demonstration with sample data."><title><?= ad_e($pageTitle) ?> · Shalimar Properties</title>
<link rel="icon" href="assets/images/logos/sp-logo-stacked.svg" type="image/svg+xml"><link href="assets/admin/vendor/inter.css" rel="stylesheet">
<link href="assets/admin/vendor/bootstrap.min.css" rel="stylesheet"><link href="assets/admin/vendor/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/admin/vendor/dataTables.bootstrap5.min.css" rel="stylesheet"><link href="assets/admin/vendor/select2.min.css" rel="stylesheet">
<link href="assets/admin/css/admin.css" rel="stylesheet"><link href="assets/admin/css/components.css" rel="stylesheet"><link href="assets/admin/css/responsive.css" rel="stylesheet">
<script>try{if(localStorage.getItem('shalimar-admin-theme')==='dark')document.documentElement.dataset.theme='dark';if(localStorage.getItem('shalimar-admin-sidebar')==='collapsed')document.documentElement.classList.add('sidebar-collapsed')}catch(e){}</script>
</head><body data-page="<?= ad_e($tab) ?>"><a class="skip-link" href="#main-content">Skip to main content</a>
<?php require __DIR__.'/sidebar.php'; ?><div class="sidebar-backdrop" id="sidebarBackdrop"></div><div class="workspace">
<?php require __DIR__.'/header.php'; ?><main id="main-content" class="main-content" tabindex="-1">
<div class="demo-banner"><span><i class="bi bi-stars"></i> <strong>Your workspace, at a glance.</strong> Explore resale management with sample data.</span><span class="demo-tag"><span></span> DEMO WORKSPACE</span></div>
<?php
if(in_array($tab,['dashboard','analytics'])) require __DIR__.'/../views/dashboard.php';
elseif($tab==='project-form') require __DIR__.'/../views/project-form.php';
elseif(in_array($tab,['property-details','resale-details'])) require __DIR__.'/../views/details.php';
elseif(in_array($tab,['sources','synchronization'])) require __DIR__.'/../views/erp.php';
elseif($tab==='settings') require __DIR__.'/../views/settings.php';
elseif($tab==='reports') require __DIR__.'/../views/reports.php';
elseif($tab==='components') require __DIR__.'/../views/components.php';
else require __DIR__.'/../views/collection.php';
?></main><?php require __DIR__.'/footer.php'; ?></div>
<div id="toastRegion" class="toast-region" aria-live="polite" aria-atomic="true"></div>
<div class="modal fade" id="demoModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><span class="eyebrow">DEMO WORKSPACE</span><h2 class="modal-title" id="modalTitle">Workspace action</h2></div><button class="btn-icon" data-bs-dismiss="modal" aria-label="Close dialog"><i class="bi bi-x-lg"></i></button></div><div class="modal-body" id="modalBody"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="modalConfirm">Confirm</button></div></div></div></div>
<script>window.AdminDemo=<?= json_encode($demoData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/admin/vendor/jquery.min.js"></script><script src="assets/admin/vendor/bootstrap.bundle.min.js"></script><script src="assets/admin/vendor/apexcharts.min.js"></script><script src="assets/admin/vendor/dataTables.min.js"></script><script src="assets/admin/vendor/dataTables.bootstrap5.min.js"></script><script src="assets/admin/vendor/select2.min.js"></script><script src="assets/admin/js/admin.js"></script><script src="assets/admin/js/tables.js"></script><script src="assets/admin/js/dashboard.js"></script>
</body></html>
