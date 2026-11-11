<section class="card p-4 my-4" id="agency-portal-access">
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><h2 class="h5 mb-1">Agency Portal Access</h2><p class="text-muted mb-0">Access is tied to this central agency record and its SGC/Zalmi listings.</p></div>
<?php if($portalAccount): ?><span class="badge <?= (int)$portalAccount['enabled']?'text-bg-success':'text-bg-secondary' ?>"><?= (int)$portalAccount['enabled']?'Enabled':'Disabled' ?></span><?php endif ?>
</div>
<?php if(!$portalEligible): ?><div class="alert alert-warning mt-3 mb-0">Usable access requires an active external agency with a current authorized SGC or Zalmi ERP mapping.</div><?php endif ?>
<?php if(!$portalAccount && $portalEligible): ?>
<form class="mt-3" method="post" action="<?= site_url('admin/agencies/'.$agency['id'].'/portal-access') ?>"><?= csrf_field() ?><fieldset <?= !can('agencies.edit')?'disabled':'' ?>>
<legend class="h6">Generate Portal Credentials</legend>
<div class="mb-3"><label class="form-label" for="agency-credential-name">Agency name</label><input class="form-control" id="agency-credential-name" value="<?= esc($agency['agency_name'],'attr') ?>" readonly></div>
<div class="form-check mb-2"><input class="form-check-input" type="radio" name="username_type" id="agency-user-phone" value="phone" <?= $phoneUsernameValid?'':'disabled' ?>><label class="form-check-label" for="agency-user-phone">Use agency phone as username<?= $phoneUsernameValid?' — '.esc($agency['phone']):' (no valid Pakistani mobile saved)' ?></label></div>
<div class="form-check"><input class="form-check-input" type="radio" name="username_type" id="agency-user-custom" value="custom" checked><label class="form-check-label" for="agency-user-custom">Use custom username</label></div>
<div class="mt-2" data-custom-username><label class="form-label" for="agency-custom-username">Custom username</label><input class="form-control" id="agency-custom-username" name="custom_username" maxlength="50" minlength="3" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" autocomplete="off" placeholder="e.g. agencyname01" required></div>
<div class="mt-2" data-phone-username hidden><label class="form-label" for="agency-phone-username">Phone username</label><input class="form-control" id="agency-phone-username" value="<?= esc($agency['phone'],'attr') ?>" readonly></div>
<p class="form-text">A secure temporary password will be generated, shown once and expire after 48 hours. Credentials are not sent by SMS or email. The agency must change its password at first sign-in.</p>
<button class="btn btn-primary" type="submit">Generate Portal Credentials</button></fieldset></form>
<?php elseif($portalAccount): ?>
<dl class="row mt-3 mb-2"><dt class="col-sm-3">Username</dt><dd class="col-sm-9"><?= esc($portalAccount['username']) ?> <span class="text-muted">(<?= esc($portalAccount['username_type']) ?>)</span></dd><dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= esc($portalAccount['created_at']) ?></dd><dt class="col-sm-3">Last login</dt><dd class="col-sm-9"><?= esc($portalAccount['last_login_at']??'Not yet used') ?></dd><dt class="col-sm-3">Password setup</dt><dd class="col-sm-9"><?= (int)$portalAccount['must_change_password']?'Temporary password; change required':'Changed by agency' ?></dd></dl>
<div class="d-flex gap-2 flex-wrap"><?php if(can('agencies.edit')): ?>
<?php if($portalEligible): ?><form method="post" action="<?= site_url('admin/agencies/'.$agency['id'].'/portal-access/reset') ?>" onsubmit="return confirm('Reset this agency password? Existing sessions will be signed out and the new password will be shown once.')"><?= csrf_field() ?><button class="btn btn-outline-primary">Reset Password</button></form><?php endif ?>
<?php if($portalEligible || (int)$portalAccount['enabled']): ?><form method="post" action="<?= site_url('admin/agencies/'.$agency['id'].'/portal-access/status') ?>" onsubmit="return confirm('<?= (int)$portalAccount['enabled']?'Disable':'Enable' ?> this agency portal account?')"><?= csrf_field() ?><input type="hidden" name="enabled" value="<?= (int)$portalAccount['enabled']?'0':'1' ?>"><button class="btn btn-outline-secondary"><?= (int)$portalAccount['enabled']?'Disable Portal Access':'Enable Portal Access' ?></button></form><?php endif ?>
<?php endif ?></div>
<?php endif ?></section>
<script>
(()=>{const section=document.getElementById('agency-portal-access'),custom=section.querySelector('#agency-custom-username');if(!custom)return;const update=()=>{const phone=section.querySelector('#agency-user-phone').checked;custom.disabled=phone;custom.required=!phone;section.querySelector('[data-custom-username]').hidden=phone;section.querySelector('[data-phone-username]').hidden=!phone;};section.querySelectorAll('[name="username_type"]').forEach(r=>r.addEventListener('change',update));update();})();
</script>
