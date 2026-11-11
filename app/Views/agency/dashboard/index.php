<?= $this->extend('agency/layouts/main') ?><?= $this->section('content') ?>
<main id="agency-main" class="agency-main">
<section class="agency-welcome"><span class="agency-kicker">SHALIMAR PROPERTIES · AGENCY PORTAL</span><h1>Welcome, <?= esc($agency['agency_name']) ?>.</h1><p>View resale listings published through your agency.</p></section>
<section class="agency-stat-grid" aria-label="Agency listing summary"><a class="agency-stat" href="<?= site_url('agency/listings') ?>"><span class="agency-stat-icon"><i class="bi bi-buildings"></i></span><span><small>Total Active Listings</small><strong><?= number_format($activeListings) ?></strong><span>Published through your agency</span></span><i class="bi bi-arrow-up-right"></i></a></section>
<section class="agency-content-panel"><div><span class="agency-kicker">YOUR PORTFOLIO</span><h2>My Active Listings</h2><p>Read-only details for active agency resale listings from your authorized ERP sources.</p></div><a class="btn btn-primary" href="<?= site_url('agency/listings') ?>">View Listings <i class="bi bi-arrow-right"></i></a></section>
</main><?= $this->endSection() ?>
