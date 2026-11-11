<?php
$collection=$tab==='resale'?'listings':$tab;
$descriptions=[
 'projects'=>'A connected view of your communities, inventory and ERP mappings.',
 'properties'=>'Every plot, every owner, every detail. Your complete property inventory.',
 'resale'=>'Manage your resale pipeline, from first listing to final handover.',
 'customers'=>'Your customer relationships, ownership records and resale activity.',
 'mapping'=>'Connect portal projects to their ERP projects, heads and subheads.',
 'heads'=>'Organize your inventory with consistent ERP heads and subheads.',
 'agents'=>'The people turning property opportunities into successful resales.',
 'agencies'=>'A trusted network of authorized real estate partners.',
 'inquiries'=>'Keep every conversation moving toward the right opportunity.',
 'users'=>'Manage your workspace team and review their access at a glance.',
 'activity'=>'A transparent view of recent actions across your workspace.',
];
$titles=['projects'=>'Projects Management','properties'=>'Properties Management','resale'=>'Resale Listings','customers'=>'Customer Directory','mapping'=>'ERP Project Mapping','heads'=>'Head / Subhead','agents'=>'Shalimar Agents','agencies'=>'Authorized Agencies','inquiries'=>'Leads & Inquiries','users'=>'Users & Roles','activity'=>'Activity Logs'];
$actions=['projects'=>'Add New Project','mapping'=>'Add Mapping','heads'=>'Add Head / Subhead','agents'=>'Add Agent','agencies'=>'Add Agency','customers'=>'Add Customer','inquiries'=>'Add Inquiry','users'=>'Invite Team Member'];
$searchPlaceholder=['properties'=>'Search property, booking or customer...','listings'=>'Search listing, property or seller...','projects'=>'Search projects or locations...'][$collection]??'Search '.strtolower($pageTitle).'...';
?>
<div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE <span class="eyebrow-line"></span> <?= in_array($tab,['sources','mapping','heads'])?'ERP MANAGEMENT':'MANAGEMENT' ?></div><h1><?= ad_e($titles[$tab]??$pageTitle) ?></h1><p><?= ad_e($descriptions[$tab]??'') ?></p></div><div class="heading-actions"><button class="btn btn-outline-secondary" data-export="<?= ad_e($collection) ?>"><i class="bi bi-download"></i> Export</button><?php if(isset($actions[$tab])): ?><?php if($tab==='projects'): ?><a class="btn btn-primary" href="admin.php?tab=project-form"><i class="bi bi-plus-lg"></i> Add New Project</a><?php else: ?><button class="btn btn-primary" data-add="<?= ad_e($collection) ?>"><i class="bi bi-plus-lg"></i> <?= ad_e($actions[$tab]) ?></button><?php endif; ?><?php endif; ?></div></div>
<?php if($tab==='projects'): ?><div class="summary-strip"><div><span class="summary-icon"><i class="bi bi-buildings"></i></span><span><small>Total projects</small><strong>4</strong></span></div><div><span class="summary-icon"><i class="bi bi-house-door"></i></span><span><small>Total properties</small><strong>12,480</strong></span></div><div><span class="summary-icon"><i class="bi bi-arrow-left-right"></i></span><span><small>Active listings</small><strong>348</strong></span></div><div><span class="summary-icon amber"><i class="bi bi-diagram-3"></i></span><span><small>Pending mappings</small><strong>1</strong></span></div></div><?php endif; ?>
<?php if($tab==='resale'): ?><div class="collection-tabs" role="group" aria-label="Listing status"><button class="active" data-status="">All listings <span>12</span></button><button data-status="Active">Active <span>4</span></button><button data-status="Pending">Pending approval <span>2</span></button><button data-status="Sold">Sold <span>2</span></button><button data-status="Draft">Drafts <span>1</span></button></div><?php endif; ?>
<?php if($tab==='users'): ?><div class="notice-inline"><i class="bi bi-shield-lock"></i><span>Role and invitation controls are previews. No accounts are created or permissions changed in this demo.</span></div><?php endif; ?>
<?php require __DIR__.'/../components/data_table.php'; ?>
