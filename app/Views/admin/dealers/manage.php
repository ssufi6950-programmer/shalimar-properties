<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?>
<section class="card p-4"><h1 class="h4">Dealer Login Recovery</h1><p><?= esc($member['dealer_name'].' · '.$member['agency_name'].' · '.$member['source_code']) ?></p><div data-login-member="<?= (int)$member['membership_id'] ?>"><span data-account-label></span><button class="btn btn-primary" data-dealer-login="<?= (int)$member['membership_id'] ?>" type="button">Inspect / Manage Login</button></div></section>
<?= $this->include('agency/dealers/login_modal') ?>
<?= $this->endSection() ?><?= $this->section('scripts') ?><script src="<?= base_url('assets/agency/js/dealer-logins.js') ?>?v=<?= filemtime(FCPATH.'assets/agency/js/dealer-logins.js') ?>"></script><?= $this->endSection() ?>
