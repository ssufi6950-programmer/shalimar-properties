<?= $this->extend('agency/layouts/main') ?><?= $this->section('content') ?>
<main id="agency-main" class="agency-auth-wrap"><section class="agency-auth-panel">
<img class="agency-login-logo" src="<?= base_url('assets/customer/images/sp-logo.svg') ?>" alt="Shalimar Properties">
<span class="agency-kicker">AUTHORIZED AGENCY PORTAL</span><h1>Sign in</h1><p>View the active resale listings assigned to your agency.</p>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif ?>
<form method="post" action="<?= site_url('agency/login') ?>"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label" for="agencyUsername">Username or phone number</label><input class="form-control form-control-lg" id="agencyUsername" name="username" maxlength="100" autocomplete="username" required autofocus></div>
<div class="mb-3"><label class="form-label" for="agencyPassword">Password</label><input class="form-control form-control-lg" id="agencyPassword" name="password" type="password" maxlength="4096" autocomplete="current-password" required></div>
<button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button></form>
<p class="agency-login-help">Portal credentials are provided by Shalimar Properties administration.</p></section></main>
<?= $this->endSection() ?>
