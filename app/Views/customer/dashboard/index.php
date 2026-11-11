<?= $this->extend('customer/layouts/main') ?><?= $this->section('content') ?>
<main id="main-content" class="portal-main portal-container app-dashboard" data-live-portfolio>
    <div class="app-home-greeting"><div><span class="app-kicker">YOUR SHALIMAR PROPERTY ACCOUNT</span><h1 class="app-dashboard-title">Welcome back, <?= esc(explode(' ',$customer['name'])[0]) ?>.</h1></div><span class="app-secure-note"><i class="bi bi-shield-check"></i> Secure owner access</span></div>
    <section class="app-stats" aria-label="Your portfolio summary">
        <a href="<?= url_to('customer.properties') ?>" class="app-stat"><span class="app-icon-tile"><i class="bi bi-buildings"></i></span><span><small>My Properties</small><strong data-stat="owned">&mdash;</strong><span>Current property ownership</span></span><i class="bi bi-arrow-up-right"></i></a>
        <?php foreach([['Active Listings','active','arrow-left-right','resale','Your active listings'],['Listing History','history','clock-history','history','Your inactive listings'],['Sold Listings','sold','check2-circle','history','Preserved in your history']]as[$label,$key,$icon,$route,$hint]): ?>
        <a href="<?= url_to('customer.'.$route) ?><?= $key==='sold'?'?status=sold':'' ?>" class="app-stat"><span class="app-icon-tile"><i class="bi bi-<?= $icon ?>"></i></span><span><small><?= $label ?></small><strong data-listing-stat="<?= $key ?>"><?= number_format($listingCounts[$key]) ?></strong><span><?= $hint ?></span></span><i class="bi bi-arrow-up-right"></i></a><?php endforeach ?>
    </section>
<p class="form-error app-dashboard-error" data-dashboard-load-status role="status" hidden></p>
</main><?= $this->endSection() ?>
