<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Company Properties</h1><p>Selected company inventory from SGC and Zalmi.</p></div><a class="btn btn-outline-primary" href="<?= site_url('admin/company-properties') ?>">Refresh / Start</a></div>
<?php foreach($sections as$section): if($section['error']): ?><div class="alert alert-warning"><strong><?= esc($section['code']) ?>:</strong> <?= esc($section['error']) ?></div><?php endif; endforeach ?>
<div class="card"><div class="card-body border-bottom"><div class="row g-2 align-items-center"><div class="col-md-6"><label class="visually-hidden" for="companySearch">Search loaded properties</label><input id="companySearch" class="form-control" type="search" placeholder="Search loaded properties, project or owner..."></div><div class="col-md-3"><label class="visually-hidden" for="companySource">ERP source</label><select id="companySource" class="form-select"><option value="">All ERP sources</option><option>SGC</option><option>ZALMI</option></select></div><div class="col-md-3 text-muted small"><span id="companyCount"><?= count($rows) ?></span> properties on this page</div></div></div>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Property / Unit</th><th>ERP Source</th><th>Project / Head / Subhead</th><th>Size / Type</th><th>Current Owner</th><th>ERP Property / Booking</th><th>Sector / Block</th><th>Inventory Status</th></tr></thead><tbody>
<?php foreach($rows as$i=>$row):
$number=trim((string)($row['property_no']??''));if($number==='')$number=trim((string)($row['plot_no']??''));if($number==='')$number=trim(implode('-',array_filter([$row['file_no_series']??'',$row['file_no']??''],static fn($v)=>$v!=='')));
$floor=trim((string)($row['floor']??''));if($floor==='')$floor=trim((string)($row['floor_no']??''));
$status=(string)($row['status']??'');$statusLabel=ucwords(str_replace('_',' ',$status));
$state=$row['owner_state']??'unavailable';
$owner=$state==='current'?($row['owner_name']??''):['none'=>'No current booking','unconfirmed'=>'Unconfirmed','unavailable'=>'Not recorded'][$state]??'Not recorded';
?>
<tr class="company-property-row" data-source="<?= esc($row['source_code'],'attr') ?>"><td><?= $i+1 ?></td><td><strong><?= esc($number!==''?$number:'Not recorded') ?><?= $floor!==''?' ? '.esc($floor):'' ?></strong><small class="d-block text-muted">Inventory #<?= esc($row['id']) ?></small></td><td><?= esc($row['source_code']) ?> ERP</td><td><strong><?= esc($row['project_name']?:'Not mapped') ?></strong><small class="d-block text-muted"><?= esc($row['head_name']??'') ?><?= !empty($row['subhead_name'])?' / '.esc($row['subhead_name']):'' ?></small></td><td><?= esc(($row['size_label']??'')!==''?$row['size_label']:($row['size']??'Not recorded')) ?><small class="d-block text-muted"><?= esc(ucwords(str_replace('_',' ',$row['category']??''))) ?><?= !empty($row['nature'])?' ? '.esc(ucfirst($row['nature'])):'' ?></small></td><td><?= esc($owner) ?><?php if($state==='current'): ?><small class="d-block text-muted">Current booking primary owner</small><?php endif ?></td><td><?= esc($row['id']) ?><small class="d-block text-muted">Booking: <?= esc($row['booking_id']??'?') ?></small></td><td><?= esc($row['sector']??'?') ?> / <?= esc($row['block_no']??'?') ?></td><td><span class="badge <?= $status==='available'?'text-bg-success':($status==='booked'?'text-bg-warning':'text-bg-secondary') ?>"><?= esc($statusLabel!==''?$statusLabel:'Not recorded') ?></span></td></tr>
<?php endforeach ?>
<tr id="companyEmpty" <?= $rows?'hidden':'' ?>><td colspan="9" class="text-center py-4">No matching selected properties.</td></tr>
</tbody></table></div><div class="card-body border-top"><div class="d-flex flex-wrap gap-2 align-items-center"><?php foreach($sections as$section): if($section['more']): $params=service('request')->getGet();$params[strtolower($section['code']).'_after']=$section['next']; ?><a class="btn btn-sm btn-outline-primary" href="<?= esc(site_url('admin/company-properties').'?'.http_build_query($params),'attr') ?>">Next <?= esc($section['code']) ?> properties</a><?php endif; endforeach ?><span class="text-muted small">Up to 50 selected properties per source. Search filters the currently loaded page.</span></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function(){
const search=document.getElementById('companySearch'),source=document.getElementById('companySource'),rows=Array.from(document.querySelectorAll('.company-property-row'));
function filter(){let count=0;const q=search.value.trim().toLocaleLowerCase();rows.forEach(function(row){row.hidden=!!((source.value&&row.dataset.source!==source.value)||(q&&!row.textContent.toLocaleLowerCase().includes(q)));if(!row.hidden)count++;});document.getElementById('companyCount').textContent=count;document.getElementById('companyEmpty').hidden=count>0;}
search.addEventListener('input',filter);source.addEventListener('change',filter);
})();
</script>
<?= $this->endSection() ?>
