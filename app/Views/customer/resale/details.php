<?= $this->extend('customer/layouts/main') ?>
<?= $this->section('content') ?>
<main class="portal-main portal-container" id="main-content"><a class="back-link" href="<?= url_to('customer.resale') ?>"><i class="bi bi-arrow-left"></i> Back to my resale listings</a><div id="customerListingDetail" data-listing-id="<?= esc($listingId,'attr') ?>"></div></main>
<?= $this->endSection() ?>
