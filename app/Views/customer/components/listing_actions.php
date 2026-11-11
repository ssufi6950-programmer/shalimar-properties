<div class="app-row-actions">
    <?php if(empty($detailMode)): ?><a class="btn btn-outline btn-sm" href="<?= url_to('customer.resale.details',(string)$record['id']) ?>" aria-label="View details for listing SP-<?= (int)$record['id'] ?>"><?= ($record['review_state']??'')==='under_review'?'View Status':'View Details' ?> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><?php endif ?>
    <?php if(empty($historyMode)&&\App\Services\ListingLifecycle::canCorrect($record)): ?><a class="btn btn-soft btn-sm" href="<?= site_url('customer/resale/'.$record['id'].'/edit') ?>">Make Changes &amp; Verify</a>
    <?php elseif(empty($historyMode)&&\App\Services\ListingLifecycle::active($record)): ?>
        <a class="btn btn-soft btn-sm" href="<?= site_url('customer/resale/'.$record['id'].'/edit') ?>">Edit Listing</a>
        <button type="button" class="btn btn-outline btn-sm" data-listing-transition="withdraw" data-id="<?= (int)$record['id'] ?>" data-version="<?= (int)$record['record_version'] ?>">Withdraw</button>
    <?php elseif(empty($historyMode)&&!empty($record['customer_withdrawn'])): ?>
        <a class="btn btn-soft btn-sm" href="<?= site_url('customer/resale/'.$record['id'].'/republish') ?>">Resubmit with Fresh OTP</a>
    <?php endif ?>
</div>
