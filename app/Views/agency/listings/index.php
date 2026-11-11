<?= $this->extend('agency/layouts/main') ?><?= $this->section('content') ?>
<main id="agency-main" class="agency-main">
<div class="agency-page-heading"><div><span class="agency-kicker">YOUR AGENCY PORTFOLIO</span><h1>My Active Listings</h1><p>Only active listings assigned to <?= esc($agency['agency_name']) ?> are shown.</p></div><span class="agency-count"><?= number_format($total) ?> active</span></div>
<section class="agency-content-panel agency-listing-panel">
<form class="agency-filters" method="get" action="<?= site_url('agency/listings') ?>">
<div class="agency-search"><label for="agencyListingSearch">Search listing or plot</label><input class="form-control" type="search" id="agencyListingSearch" name="q" value="<?= esc($filters['q'],'attr') ?>" maxlength="100" placeholder="Reference or plot number"></div>
<div><label for="agencySourceFilter">ERP Source</label><select class="form-select" id="agencySourceFilter" name="source"><option value="">All sources</option><?php foreach(['SGC','ZALMI'] as$source): ?><option value="<?= $source ?>" <?= $filters['source']===$source?'selected':'' ?>><?= $source ?></option><?php endforeach ?></select></div>
<div><label for="agencyPerPage">Rows</label><select class="form-select" id="agencyPerPage" name="per_page"><?php foreach([10,20,50]as$size): ?><option value="<?= $size ?>" <?= $filters['per_page']===$size?'selected':'' ?>><?= $size ?></option><?php endforeach ?></select></div>
<button class="btn btn-primary" type="submit">Apply</button><?php if($filters['q']!==''||$filters['source']!==''): ?><a class="btn btn-outline-secondary" href="<?= site_url('agency/listings') ?>">Clear</a><?php endif ?>
</form>
<div class="agency-table-scroll" role="region" aria-label="Your active resale listings" tabindex="0"><table class="table agency-data-table" role="table"><caption class="visually-hidden">Active resale listings assigned to <?= esc($agency['agency_name']) ?></caption><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">#</th><th scope="col" role="columnheader">Listing Reference</th><th scope="col" role="columnheader">Property / Plot</th><th scope="col" role="columnheader">Project</th><th scope="col" role="columnheader">ERP Source</th><th scope="col" role="columnheader">Price / Rent</th><th scope="col" role="columnheader">Negotiable</th><th scope="col" role="columnheader">Published</th><th scope="col" role="columnheader">Status</th><th scope="col" role="columnheader">Details</th></tr></thead><tbody role="rowgroup">
<?php foreach($rows as$index=>$row): ?><tr role="row" data-agency-listing-id="<?= (int)$row['id'] ?>">
<td class="agency-record-index" role="cell" data-label="#"><?= ($page-1)*$per_page+$index+1 ?></td>
<th class="agency-record-reference" scope="row" role="rowheader" data-label="Reference"><?= esc($row['public']['reference']) ?></th>
<td class="agency-record-title" role="cell" data-label="Property / Plot"><?= esc($row['public']['plot']?:$row['public']['title']?:'Not recorded') ?></td>
<td class="agency-record-project" role="cell" data-label="Project"><?= esc($row['project_name']) ?></td>
<td class="agency-record-source" role="cell" data-label="ERP Source"><?= esc($row['source_code']) ?></td>
<td class="agency-record-price" role="cell" data-label="Price / Rent"><?= $row['asking_price']!==null?'PKR '.number_format((float)$row['asking_price']).(($row['listing_purpose']??'sale')==='rent'?' / month':''):'Not recorded' ?></td>
<td class="agency-record-negotiable" role="cell" data-label="Negotiable"><?= $row['public']['negotiable']?'Yes':'No' ?></td>
<td class="agency-record-published" role="cell" data-label="Published"><?= $row['public']['published_at']!==''?esc($row['public']['published_at']):'Not recorded' ?></td>
<td class="agency-record-status" role="cell" data-label="Status"><span class="agency-status">Active</span></td>
<td class="agency-record-actions" role="cell" data-label="Details"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('agency/listings/'.$row['id']) ?>">View Details</a></td>
</tr><?php endforeach ?>
<?php if(!$rows): ?><tr class="agency-record-empty" role="row"><td role="cell" colspan="10"><div class="agency-table-empty"><i class="bi bi-inbox"></i><strong>No active listings found</strong><span><?php if($filters['q']!==''||$filters['source']!==''): ?>Try a different search or source filter.<?php else: ?>Active listings assigned to your agency will appear here.<?php endif ?></span></div></td></tr><?php endif ?>
</tbody></table></div>
<nav class="agency-pagination" aria-label="Listing pages"><span><?= $total?number_format(($page-1)*$per_page+1):0 ?>â€“<?= number_format(min($page*$per_page,$total)) ?> of <?= number_format($total) ?> listings</span><div><?php if($page>1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= current_url().'?'.http_build_query(array_replace($filters,['page'=>$page-1])) ?>">Previous</a><?php endif ?><span>Page <?= $page ?> / <?= $pages ?></span><?php if($page<$pages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= current_url().'?'.http_build_query(array_replace($filters,['page'=>$page+1])) ?>">Next</a><?php endif ?></div></nav>
</section></main><?= $this->endSection() ?>
