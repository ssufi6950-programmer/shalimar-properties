<?php if(can('agencies.view')&&($agencyPending=service('agencyDirectory')->unmappedCount())>0): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4" role="status" data-unmapped-agency-alert>
    <span><i class="bi bi-exclamation-triangle me-2"></i><strong><?= $agencyPending ?> <?= $agencyPending===1?'Unmapped Agency Requires':'Unmapped Agencies Require' ?> Attention</strong></span>
    <a class="btn btn-sm btn-outline-dark" href="<?= site_url('admin/agencies?tab=unmapped') ?>">Review Unmapped Agencies</a>
</div>
<?php endif ?>
