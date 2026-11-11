<?= $this->extend('agency/layouts/main') ?><?= $this->section('content') ?>
<main id="agency-main" class="agency-auth-wrap"><section class="agency-auth-panel">
<span class="agency-kicker">AGENCY ACCOUNT</span><h1><?= !empty($agency['must_change_password'])?'Set your password':'Change password' ?></h1>
<p><?= esc($agency['agency_name']) ?> · Choose a new password to protect your portal access.</p>
<?php if(!empty($message)): ?><div class="alert alert-success" role="status"><?= esc($message) ?></div><?php endif ?>
<?php if(!empty($error)): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif ?>
<form method="post" action="<?= site_url('agency/change-password') ?>"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="agencyCurrentPassword">Current / temporary password</label><input class="form-control form-control-lg" id="agencyCurrentPassword" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="mb-3"><label class="form-label" for="agencyNewPassword">New password</label><input class="form-control form-control-lg" id="agencyNewPassword" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><div class="form-text">12–72 characters with uppercase, lowercase, a number and a symbol.</div></div>
<div class="mb-3"><label class="form-label" for="agencyPasswordConfirm">Confirm new password</label><input class="form-control form-control-lg" id="agencyPasswordConfirm" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
<button class="btn btn-primary btn-lg w-100" type="submit">Save password</button></form>
</section></main><?= $this->endSection() ?>
