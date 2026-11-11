<div class="app-page-heading">
    <div>
        <?php if(($activeNav??'')!=='dashboard'): ?><a class="app-back <?= empty($backUrl)&&empty($backLabel)?'app-back-home':'' ?>" href="<?= $backUrl??url_to('customer.dashboard') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc($backLabel??'Back to Home') ?></a><?php endif ?>
        <span class="app-kicker"><?= esc($eyebrow??'YOUR PROPERTY, YOUR POSSIBILITIES') ?></span>
        <h1><?= esc($title??$pageTitle) ?></h1>
        <?php if(!empty($subtitle)): ?><p><?= esc($subtitle) ?></p><?php endif ?>
    </div>
    <?php if(!empty($headingRefresh)): ?><button class="btn btn-outline" type="button" data-refresh-properties aria-label="Refresh Properties"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span class="app-heading-label-wide">Refresh Properties</span><span class="app-heading-label-phone">Refresh</span></button><?php endif ?>
    <?php if(!empty($headingAction)): ?><a class="btn btn-primary" href="<?= esc($headingAction['url'],'attr') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i><?php if(!empty($headingAction['mobileLabel'])): ?><span class="app-heading-label-wide"><?= esc($headingAction['label']) ?></span><span class="app-heading-label-phone"><?= esc($headingAction['mobileLabel']) ?></span><?php else: ?><?= esc($headingAction['label']) ?><?php endif ?></a><?php endif ?>
</div>
