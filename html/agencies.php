<?php
$active='agencies';
$pageTitle='Authorized Agencies - Shalimar Properties';
require_once __DIR__.'/includes/bootstrap.php';
$directory=\App\Libraries\PortalCatalogStore::connect()->publicAgencyDirectory();
require __DIR__.'/includes/header.php';
?>
<main id="main">
<?php require __DIR__.'/includes/agencies-directory.php'; ?>
<section class="sec-tight"><div class="wrap soft-band rv two-col">
  <div><span class="kicker">For realtors</span><h2>Work with Shalimar Properties</h2><p class="muted">Contact our office for agency authorization and portal access.</p></div>
  <div class="acts"><a href="tel:<?= e(SITE_TEL) ?>" class="btn btn-solid"><?= e(SITE_PHONE) ?></a><a href="mailto:<?= e(SITE_EMAIL) ?>" class="btn btn-ghost"><?= e(SITE_EMAIL) ?></a></div>
</div></section>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
</body></html>
