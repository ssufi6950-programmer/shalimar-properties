<?php $appNav=['dashboard'=>['Home','grid-1x2'],'properties'=>['Properties','buildings'],'resale'=>['Listings','arrow-left-right'],'history'=>['History','clock-history']]; ?>
<header class="app-header"><div class="app-header-inner portal-container">
    <a class="app-brand" href="<?= url_to('customer.dashboard') ?>" aria-label="Shalimar Properties Home"><img src="<?= customer_asset('images/sp-logo.svg') ?>" alt="Shalimar Properties" width="181" height="44"><span>OWNER'S APP</span></a>
    <nav class="app-desktop-nav" aria-label="Customer navigation">
        <?php foreach($appNav as$key=>[$label,$icon]): ?><a href="<?= url_to('customer.'.$key) ?>" class="<?= $activeNav===$key?'active':'' ?>" <?= $activeNav===$key?'aria-current="page"':'' ?>><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><?= $label ?></a><?php endforeach ?>
    </nav>
    <div class="app-header-actions">
        <a class="app-help-link" href="<?= url_to('customer.support') ?>" aria-label="Help and Support"><i class="bi bi-question-circle" aria-hidden="true"></i></a>
        <div class="dropdown"><button type="button" class="app-profile-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open account menu"><span class="customer-avatar"><?= esc($customer['initials']) ?></span><span class="app-profile-name"><strong><?= esc($customer['name']) ?></strong><small>Your property account</small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
            <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?= url_to('customer.properties') ?>" role="button" data-bs-toggle="offcanvas" data-bs-target="#customerListProperty" aria-controls="customerListProperty"><i class="bi bi-plus-circle" aria-hidden="true"></i>List My Property</a><a class="dropdown-item" href="<?= url_to('customer.profile') ?>"><i class="bi bi-person-circle"></i>My Profile</a><a class="dropdown-item" href="<?= url_to('customer.support') ?>"><i class="bi bi-headset"></i>Help &amp; Support</a><a class="dropdown-item" href="<?= url_to('customer.history') ?>"><i class="bi bi-clock-history"></i>Listing History</a><hr class="dropdown-divider"><form action="<?= url_to('customer.logout') ?>" method="post" data-customer-logout><?= csrf_field() ?><button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i>Sign out</button></form></div>
        </div>
        <form class="app-desktop-logout" action="<?= url_to('customer.logout') ?>" method="post" data-customer-logout><?= csrf_field() ?><button type="submit" class="app-help-link" aria-label="Sign out"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button></form>
    </div>
</div></header>
