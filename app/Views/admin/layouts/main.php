<?php require ROOTPATH.'html/includes/admin/navigation.php'; ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= esc($pageTitle) ?> · Shalimar Properties</title>
<base href="<?= esc(admin_legacy_url(),'attr') ?>">
<link rel="icon" href="assets/images/logos/sp-logo-stacked.svg" type="image/svg+xml">
<link href="assets/admin/vendor/inter.css" rel="stylesheet"><link href="assets/admin/vendor/bootstrap.min.css" rel="stylesheet"><link href="assets/admin/vendor/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/admin/vendor/dataTables.bootstrap5.min.css" rel="stylesheet"><link href="assets/admin/css/admin.css" rel="stylesheet"><link href="assets/admin/css/components.css" rel="stylesheet"><link href="assets/admin/css/responsive.css" rel="stylesheet">
<link href="<?= base_url('assets/admin/css/erp-sources.css') ?>" rel="stylesheet">
<script>try{if(localStorage.getItem('shalimar-admin-theme')==='dark')document.documentElement.dataset.theme='dark';if(localStorage.getItem('shalimar-admin-sidebar')==='collapsed')document.documentElement.classList.add('sidebar-collapsed')}catch(e){}</script>
</head><body data-page="<?= esc($tab??'','attr') ?>"><a class="skip-link" href="<?= url_to('admin.erp.sources') ?>#main-content">Skip to main content</a>
<?= $this->include('admin/layouts/sidebar') ?>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div><div class="workspace">
<?= $this->include('admin/layouts/header') ?>
<main id="main-content" class="main-content" tabindex="-1"><div class="demo-banner"><span><i class="bi bi-database-check"></i><strong>Admin workspace.</strong> Access is controlled by your assigned permissions.</span><span class="demo-tag"><span></span> DATABASE BACKED</span></div><?= $this->include('admin/agencies/alert') ?><?= $this->include('admin/agents/alert') ?><?= $this->renderSection('content') ?></main>
<footer class="workspace-footer"><span>© <?= date('Y') ?> Shalimar Properties. All rights reserved.</span><span>Admin Portal <span class="footer-separator">·</span> <a href="admin.php">Workspace</a><span class="footer-separator">·</span><a href="index.php">Visit website <i class="bi bi-arrow-up-right"></i></a></span></footer>
</div>
<script src="assets/admin/vendor/jquery.min.js"></script><script src="assets/admin/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/customer/vendor/sweetalert2.all.min.js') ?>"></script>
<script src="<?= base_url('assets/admin/js/shell.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body></html>
