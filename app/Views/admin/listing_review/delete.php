<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?>
<div class="page-heading"><div><a href="<?= site_url('admin/listings/'.$record['id'].'/review') ?>">&larr; Listing details</a><h1>Delete Listing SP-<?= (int)$record['id'] ?></h1></div></div>
<section class="card p-4">
    <?php if($error=session()->getFlashdata('listing_delete_error')): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <h2 class="h5"><?= esc($payload['plot_no']??'Property') ?> &middot; <?= esc(customer_listing_purpose($record)) ?></h2>
    <p>PKR <?= number_format((float)$record['asking_price']) ?><?= $record['listing_purpose']==='rent'?' / month':'' ?> &middot; <?= esc($record['status'].' / '.$record['deal']) ?> &middot; Version <?= (int)$record['record_version'] ?></p>
    <?php if($deleted): ?>
        <p class="alert alert-info">This listing has already been deleted. Its retained details and history are read-only.</p>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/listings?review=deleted') ?>">View Deleted Listings</a>
    <?php else: ?>
        <p>Delete removes this listing from the public website and normal listing queues. The customer's property, photos, assignments and audit history remain stored. Sold, cancelled and rejected outcomes are preserved. There is no restore action.</p>
        <form method="post" action="<?= site_url('admin/listings/'.$record['id'].'/delete') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= (int)$record['record_version'] ?>">
            <input type="hidden" name="fingerprint" value="<?= esc($fingerprint,'attr') ?>">
            <label class="form-label" for="listingDeleteReason">Reason (optional)</label>
            <textarea class="form-control mb-3" id="listingDeleteReason" name="reason" rows="2" maxlength="500"></textarea>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirmListingDelete" name="confirmed" value="1" required><label class="form-check-label" for="confirmListingDelete">Delete listing SP-<?= (int)$record['id'] ?>.</label></div>
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger" type="submit">Delete Listing</button><a class="btn btn-outline-secondary" href="<?= site_url('admin/listings/'.$record['id'].'/review') ?>">Cancel</a></div>
        </form>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
