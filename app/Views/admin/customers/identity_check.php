<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?>
<div class="page-heading"><div><div class="eyebrow">CUSTOMER SUPPORT <span class="eyebrow-line"></span> ERP CHECK</div><h1>Customer Identity Check</h1><p>Run the same read-only identity lookup used by customer sign-in, against active ERP sources.</p></div></div>
<div class="alert alert-warning" role="note">This sends the entered CNIC and mobile to the configured ERP identity endpoints. It does not send an OTP, save the values, or change ERP/portal records. Access is restricted to Admins with customer edit permission.</div>
<section class="card p-3 p-md-4 mb-4">
    <form method="post" action="<?= esc(site_url('admin/customer-identity-check'),'attr') ?>" autocomplete="off" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label" for="identityCnic">CNIC</label><input class="form-control" id="identityCnic" name="cnic" inputmode="numeric" maxlength="15" placeholder="13 digits" required></div>
        <div class="col-md-6"><label class="form-label" for="identityMobile">ERP-registered mobile</label><input class="form-control" id="identityMobile" name="mobile" inputmode="tel" maxlength="20" placeholder="03XXXXXXXXX or +923XXXXXXXXX" required></div>
        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Check identity</button></div>
    </form>
</section>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif ?>
<?php if($sources): ?><section class="card p-3 p-md-4"><h2 class="h5">Source results</h2><p class="text-muted small">Raw API bodies and entered identifiers are intentionally not displayed.</p><div class="table-responsive"><table class="table align-middle"><thead><tr><th>ERP source</th><th>HTTP</th><th>Result</th><th>Matched identity</th><th>Details</th></tr></thead><tbody>
<?php foreach($sources as $source): ?><tr><td><?= esc($source['source_name']) ?><?php if($source['source_code']!==''): ?><small class="d-block text-muted"><?= esc($source['source_code']) ?></small><?php endif ?></td><td><?= $source['http_status']===null?'—':(int)$source['http_status'] ?></td><td><span class="badge <?= $source['result']==='matched'?'text-bg-success':($source['result']==='no_match'?'text-bg-secondary':'text-bg-danger') ?>"><?= esc(ucwords(str_replace('_',' ',$source['result']))) ?></span></td><td><?= (int)($source['match_count']??0) ?> match(es)<?php foreach(($source['matches']??[]) as $match): ?><div class="small"><?= esc($match['display_name']) ?> · ERP customer #<?= esc($match['customer_id']) ?></div><?php endforeach ?></td><td><?= esc($source['message']) ?></td></tr><?php endforeach ?>
</tbody></table></div></section><?php endif ?>
<?= $this->endSection() ?>
