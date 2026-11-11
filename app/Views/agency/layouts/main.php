<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="description" content="Secure Shalimar Properties Authorized Agency Portal">
<title><?= esc($pageTitle) ?> · Shalimar Properties</title>
<link rel="icon" href="<?= base_url('assets/customer/images/sp-mark.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= base_url('assets/customer/vendor/inter.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/customer/vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/customer/vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/customer/css/customer.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/customer/css/customer-app.css') ?>?v=<?= filemtime(FCPATH.'assets/customer/css/customer-app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/agency/css/agency-portal.css') ?>?v=<?= filemtime(FCPATH.'assets/agency/css/agency-portal.css') ?>">
<?= $this->renderSection('head') ?>
</head>
<?php $activeNav=$activeNav??''; ?>
<body class="customer-app portal-body agency-app <?= !empty($authPage)?'agency-auth-page':'' ?>">
<a class="skip-link" href="#agency-main">Skip to content</a>
<?php if(empty($authPage)): ?>
<header class="agency-header"><div class="agency-header-inner">
<a class="agency-brand" href="<?= site_url('agency/dashboard') ?>"><img src="<?= base_url('assets/customer/images/sp-logo.svg') ?>" alt="Shalimar Properties"><span>AUTHORIZED AGENCY PORTAL</span></a>
<nav class="agency-desktop-nav" aria-label="Agency navigation"><a href="<?= site_url('agency/dashboard') ?>" class="<?= $activeNav==='dashboard'?'active':'' ?>">Dashboard</a><a href="<?= site_url('agency/listings') ?>" class="<?= $activeNav==='listings'?'active':'' ?>">My Active Listings</a></nav>
<div class="agency-account"><span class="agency-name"><?= esc($agency['agency_name']) ?></span><a href="<?= site_url('agency/change-password') ?>">Change Password</a><form method="post" action="<?= site_url('agency/logout') ?>"><?= csrf_field() ?><button type="submit">Log out</button></form></div>
</div></header>
<?php endif ?>
<?= $this->renderSection('content') ?>
<?php if(empty($authPage)): ?><nav class="agency-bottom-nav" aria-label="Mobile agency navigation"><a class="<?= $activeNav==='dashboard'?'active':'' ?>" href="<?= site_url('agency/dashboard') ?>"><i class="bi bi-grid-1x2"></i><span>Home</span></a><a class="<?= $activeNav==='listings'?'active':'' ?>" href="<?= site_url('agency/listings') ?>"><i class="bi bi-list-ul"></i><span>Listings</span></a><a href="<?= site_url('agency/change-password') ?>"><i class="bi bi-person-gear"></i><span>Account</span></a></nav><footer class="agency-footer">© <?= date('Y') ?> Shalimar Properties · Agency Portal</footer><?php endif ?>
<script src="<?= base_url('assets/customer/vendor/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body></html>
