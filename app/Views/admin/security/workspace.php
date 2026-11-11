<?php
// Reuse the existing prototype content behind the real CI4 authentication/permission filters.
$savedGet=$_GET;$_GET['tab']=$workspaceTab;
require ROOTPATH.'html/includes/admin/data.php';
$_GET=$savedGet;
ob_start();
if(in_array($tab,['dashboard','analytics'],true))require ROOTPATH.'html/includes/admin/views/dashboard.php';
elseif($tab==='project-form')require ROOTPATH.'html/includes/admin/views/project-form.php';
elseif(in_array($tab,['property-details','resale-details'],true))require ROOTPATH.'html/includes/admin/views/details.php';
elseif($tab==='synchronization')require ROOTPATH.'html/includes/admin/views/erp.php';
elseif(in_array($tab,['settings','reports','components'],true))require ROOTPATH.'html/includes/admin/views/'.$tab.'.php';
else require ROOTPATH.'html/includes/admin/views/collection.php';
$prototypeContent=ob_get_clean();
?>
<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?><div class="notice-inline mb-4"><i class="bi bi-info-circle"></i><span>This module remains a frontend preview with sample data. Users, Roles, Audit Logs and ERP Sources use the portal database.</span></div><?= $prototypeContent ?>
<div id="toastRegion" class="toast-region" aria-live="polite"></div><div class="modal fade" id="demoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 id="modalTitle"></h2><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="modalBody"></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="modalConfirm">Confirm</button></div></div></div></div><?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>window.AdminDemo=<?= json_encode($demoData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<?php foreach(['apexcharts.min.js','dataTables.min.js','dataTables.bootstrap5.min.js','select2.min.js']as$file):?><script src="<?= admin_legacy_url('assets/admin/vendor/'.$file) ?>"></script><?php endforeach;?>
<?php foreach(['admin.js','tables.js','dashboard.js']as$file):?><script src="<?= admin_legacy_url('assets/admin/js/'.$file) ?>"></script><?php endforeach;?><?= $this->endSection() ?>
