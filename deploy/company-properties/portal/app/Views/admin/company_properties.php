<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><h1>Company Properties</h1><p>Inventory selected for Shalimar Properties. Only records marked smp_active = yes are displayed.</p></div><a class="btn btn-outline-primary" href="<?= site_url('admin/company-properties') ?>">Refresh / Start</a></div>
<?php foreach($sections as $section): ?>
<section class="card p-3 mb-4"><h2 class="h5"><?= esc($section['code']) ?> Company Properties</h2>
<?php if($section['error']): ?><div class="alert alert-warning"><?= esc($section['error']) ?></div>
<?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>#</th><th>Inventory ID</th><th>Property</th><th>Type</th><th>Nature</th><th>Size / Unit ID</th><th>Head / Subhead IDs</th><th>Sector / Block</th><th>Status</th></tr></thead><tbody>
<?php foreach($section['rows'] as $i=>$row): ?><tr><td><?= $i+1 ?></td><td><?= esc($row['id']) ?></td><td><strong><?= esc($row['property_no']??$row['plot_no']??'Not recorded') ?></strong><small class="d-block"><?= esc(trim(($row['file_no_series']??'').' '.($row['file_no']??''))) ?></small><small class="d-block"><?= esc($row['floor_no']??'') ?></small></td><td><?= esc(ucwords(str_replace('_',' ',$row['category']??''))) ?></td><td><?= esc($row['nature']??'') ?></td><td><?= esc($row['size']??'') ?> / <?= esc($row['size_code']??'Not recorded') ?></td><td><?= esc($row['head']??'') ?> / <?= esc($row['sub_head']??'') ?></td><td><?= esc($row['sector']??'') ?> / <?= esc($row['block_no']??'') ?></td><td><?= esc($row['status']??'') ?></td></tr><?php endforeach ?>
<?php if(!$section['rows']): ?><tr><td colspan="9" class="text-center py-4">No inventory marked smp_active = yes.</td></tr><?php endif ?></tbody></table></div><p class="text-muted small"><?= count($section['rows']) ?> records on this page (maximum 50).</p>
<?php if($section['more']): $params=service('request')->getGet();$params[strtolower($section['code']).'_after']=$section['next']; ?><a class="btn btn-sm btn-outline-primary" href="<?= esc(site_url('admin/company-properties').'?'.http_build_query($params),'attr') ?>">Next <?= esc($section['code']) ?> properties</a><?php endif ?>
<?php endif ?></section><?php endforeach ?>
<?= $this->endSection() ?>
