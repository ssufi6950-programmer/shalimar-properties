<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?>
<div class="page-heading"><div><a href="<?= site_url('admin/agencies/'.$agency['id']) ?>">Back to Agency</a><h1>Agency Portal Credentials</h1><p><?= esc($agency['agency_name']) ?></p></div></div>
<div class="alert alert-warning" role="alert"><strong>Copy these credentials now.</strong> The temporary password is shown only in this response and expires in 48 hours. It is not stored or sent to the agency.</div>
<section class="card p-4" data-one-time-credentials>
<div class="mb-3"><label class="form-label" for="agency-portal-username">Username</label><div class="input-group"><input class="form-control" id="agency-portal-username" readonly value="<?= esc($credentials['username'],'attr') ?>"><button class="btn btn-outline-secondary" type="button" data-copy="agency-portal-username">Copy username</button></div></div>
<div class="mb-3"><label class="form-label" for="agency-portal-password">Temporary password</label><div class="input-group"><input class="form-control font-monospace" id="agency-portal-password" readonly value="<?= esc($credentials['temporary_password'],'attr') ?>"><button class="btn btn-outline-secondary" type="button" data-copy="agency-portal-password">Copy password</button></div></div>
<p class="form-text">On first sign-in the agency must set a new password. Share these credentials securely outside this portal.</p><a class="btn btn-primary" href="<?= site_url('admin/agencies/'.$agency['id']) ?>">Return to Agency</a></section>
<?= $this->endSection() ?><?= $this->section('scripts') ?><script>
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.copy);try{await navigator.clipboard.writeText(input.value);button.textContent='Copied';}catch(e){input.focus();input.select();document.execCommand('copy');button.textContent='Copied';}setTimeout(()=>button.textContent=button.dataset.copy==='agency-portal-password'?'Copy password':'Copy username',1200)}));
window.addEventListener('pagehide',()=>{document.getElementById('agency-portal-password').value='';});
window.addEventListener('pageshow',event=>{if(event.persisted)location.replace(<?= json_encode(site_url('admin/agencies/'.$agency['id']),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>);});
</script><?= $this->endSection() ?>
