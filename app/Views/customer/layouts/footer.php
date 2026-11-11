<footer class="app-footer portal-container"><span>&copy; <?= date('Y') ?> Shalimar Properties</span><a href="<?= url_to('customer.support') ?>"><i class="bi bi-headset" aria-hidden="true"></i> Here when you need us</a><a href="<?= customer_public_url() ?>">Visit website <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></footer>
<?php $mobileMoreActive=in_array($activeNav,['profile','history','support'],true); ?>
<nav class="app-bottom-nav" aria-label="Mobile customer navigation">
<?php foreach(['dashboard'=>['Home','grid-1x2'],'properties'=>['Properties','buildings'],'resale'=>['Listings','arrow-left-right']]as$key=>[$label,$icon]): ?>
 <?php if($key==='resale'): ?><a class="app-bottom-create <?= $activeNav==='list'?'active':'' ?>" href="<?= url_to('customer.properties') ?>" role="button" data-bs-toggle="offcanvas" data-bs-target="#customerListProperty" aria-controls="customerListProperty" aria-label="List My Property for resale or rent"><span class="app-bottom-create-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span><span>List</span></a><?php endif ?>
 <a href="<?= url_to('customer.'.$key) ?>" class="<?= $activeNav===$key?'active':'' ?>" <?= $activeNav===$key?'aria-current="page"':'' ?>><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span></a>
<?php endforeach ?>
 <a class="<?= $mobileMoreActive?'active':'' ?>" href="<?= url_to('customer.profile') ?>" role="button" data-bs-toggle="offcanvas" data-bs-target="#customerMoreMenu" aria-controls="customerMoreMenu" aria-label="More customer sections"><i class="bi bi-three-dots" aria-hidden="true"></i><span>More</span></a>
</nav>
<div class="offcanvas offcanvas-bottom app-list-property-sheet" tabindex="-1" id="customerListProperty" aria-labelledby="customerListPropertyTitle">
 <div class="offcanvas-header"><div><h2 id="customerListPropertyTitle">List your property</h2><p>Choose how you want to list it.</p></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close property listing options"></button></div>
 <div class="offcanvas-body">
  <a class="app-list-purpose" href="<?= url_to('customer.properties.choose') ?>?purpose=sale"><span class="app-icon-tile"><i class="bi bi-house" aria-hidden="true"></i></span><span><strong>List for Resale</strong><small>Choose a property you want to sell.</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
  <a class="app-list-purpose" href="<?= url_to('customer.properties.choose') ?>?purpose=rent"><span class="app-icon-tile"><i class="bi bi-key" aria-hidden="true"></i></span><span><strong>List for Rent</strong><small>Choose a property you want to rent out.</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
 </div>
</div>
<div class="offcanvas offcanvas-bottom app-list-property-sheet app-more-sheet" tabindex="-1" id="customerMoreMenu" aria-labelledby="customerMoreTitle">
 <div class="offcanvas-header"><h2 id="customerMoreTitle">Your account</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close account menu"></button></div>
 <div class="offcanvas-body">
 <?php foreach(['history'=>['Listing History','clock-history'],'profile'=>['My Profile','person-circle'],'support'=>['Help & Support','headset']]as$key=>[$label,$icon]): ?>
  <a class="app-more-link <?= $activeNav===$key?'active':'' ?>" href="<?= url_to('customer.'.$key) ?>" <?= $activeNav===$key?'aria-current="page"':'' ?>><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><span><?= esc($label) ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
 <?php endforeach ?>
 </div>
</div>
