<?php
$active='projects';
$prettyRoute=true;
$projectName=(string)($project['name']??$project['slug']);
$pageTitle=$projectName.' — Available Units';
require ROOTPATH.'html/includes/header.php';
?>
<style>
.pi-page{--pi-green:#176b4b;--pi-muted:#718078;padding:30px 0 64px}.pi-wrap{width:min(1240px,calc(100% - 36px));margin:auto}.pi-breadcrumb{display:flex;gap:10px;align-items:center;color:var(--pi-muted);font-size:13px;margin:0 0 14px}.pi-breadcrumb a{color:inherit}.pi-head{display:flex;justify-content:space-between;gap:20px;align-items:end;margin-bottom:22px}.pi-head h1{font-size:clamp(28px,4vw,42px);margin:0}.pi-head p{margin:8px 0 0;color:var(--pi-muted)}.pi-actions,.pi-filters{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.pi-btn{border:1px solid #d8e2dc;background:#fff;color:#173d31;padding:9px 14px;border-radius:10px;font-weight:650;text-decoration:none;cursor:pointer}.pi-btn:hover{border-color:var(--pi-green);color:var(--pi-green)}.pi-note,.pi-state{border:1px solid #dce7e0;background:#fff;border-radius:14px;padding:18px;margin:14px 0;color:#52645a}.pi-summary,.pi-breakdowns{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin:18px 0}.pi-card,.pi-panel{background:#fff;border:1px solid #e1e8e2;border-radius:16px;padding:17px;box-shadow:0 5px 18px #173d310b}.pi-card small{display:block;color:var(--pi-muted);font-weight:650}.pi-card strong{display:block;font-size:27px;color:#173d31;margin-top:4px}.pi-panel h2{font-size:20px;margin:0 0 5px}.pi-panel>p{color:var(--pi-muted);font-size:13px}.pi-row{display:grid;grid-template-columns:minmax(130px,1fr) repeat(4,minmax(60px,auto)) auto;gap:12px;align-items:center;border-top:1px solid #edf1ee;padding:13px 0}.pi-row-name{font-weight:700}.pi-meter{height:6px;background:#ecf0ec;border-radius:6px;overflow:hidden;margin-top:6px}.pi-meter i{height:100%;display:block;background:#287c59}.pi-toolbar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0}.pi-filters select,.pi-filters input{min-height:40px;border:1px solid #d8e2dc;border-radius:9px;padding:8px 10px;background:#fff}.pi-grid-viewport{overflow:auto;max-height:72vh;border:1px solid #dce5de;background:#f3f6f3;border-radius:16px;padding:18px}.pi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px;transform-origin:top left}.pi-tile{min-height:68px;border:1px solid #cdd7d0;border-radius:9px;background:#dfe4e0;color:#37443c;font-size:12px;font-weight:700;cursor:pointer;padding:8px;overflow-wrap:anywhere}.pi-tile.available{background:#dff1e5;border-color:#a7d3b5;color:#17613d}.pi-tile.booked{background:#f7e1df;border-color:#e4b6b0;color:#8c322e}.pi-tile.dim{opacity:.25}.pi-legend{display:flex;gap:15px;flex-wrap:wrap;color:#52645a;font-size:13px}.pi-legend span:before{content:'';display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:6px;background:#dfe4e0}.pi-legend .available:before{background:#78bb8b}.pi-legend .booked:before{background:#d9827b}.pi-modal{position:fixed;inset:0;background:#10271f99;display:none;place-items:center;padding:18px;z-index:9999}.pi-modal.open{display:grid}.pi-modal-card{width:min(480px,100%);background:#fff;border-radius:16px;padding:22px;box-shadow:0 20px 70px #0003}.pi-modal-card h2{margin-top:0}.pi-facts{display:grid;grid-template-columns:140px 1fr;gap:8px;font-size:14px}.pi-facts dt{color:var(--pi-muted)}.pi-facts dd{margin:0;font-weight:650}.pi-progress{color:#176b4b;font-weight:650}.pi-status{padding:16px;border-radius:12px;background:#f1f5f1;margin:14px 0}.pi-hide{display:none!important}
@media(max-width:700px){.pi-page{padding-top:20px}.pi-wrap{width:min(100% - 24px,1240px)}.pi-head{align-items:start;flex-direction:column}.pi-row{grid-template-columns:1fr auto auto;gap:7px}.pi-row .pi-hide-mobile{display:none}.pi-grid-viewport{max-height:65vh;padding:10px}.pi-grid{grid-template-columns:repeat(auto-fill,minmax(68px,1fr));gap:6px}.pi-tile{min-height:60px;padding:6px;font-size:11px}.pi-breakdowns{grid-template-columns:1fr}.pi-facts{grid-template-columns:112px 1fr}}
</style>
<style>
/* Property details open as a responsive side drawer. */
.pi-modal.open{display:flex;justify-content:flex-end;align-items:stretch;padding:0}
.pi-modal-card{width:min(480px,100%);height:100%;overflow:auto;border-radius:16px 0 0 16px}
@media(max-width:520px){.pi-modal-card{width:100%;border-radius:0}}
</style>
<main id="main" class="pi-page" data-project-inventory data-map-mode="<?= $mapMode?'true':'false' ?>">
<div class="pi-wrap">
  <nav class="pi-breadcrumb" aria-label="Breadcrumb"><a href="<?= e(\App\Libraries\PortalLinks::publicSite('projects')) ?>">Projects</a><span>/</span><span><?= e($projectName) ?></span><?php if($mapMode): ?><span>/</span><span>Inventory map</span><?php endif ?></nav>
  <div class="pi-head"><div><span class="kicker">PROJECT INVENTORY</span><h1><?= e($projectName) ?> — Available Units</h1><p>Read-only inventory status from the verified ERP project mapping.</p></div><div class="pi-actions"><a class="pi-btn" href="<?= e(site_url('project-inventory/'.rawurlencode($project['slug']))) ?>">← Overview</a><button type="button" class="pi-btn" data-refresh>↻ Refresh inventory</button></div></div>
  <div class="pi-state" data-state role="status">Loading verified ERP inventory…</div>
  <div data-content class="pi-hide">
    <p class="pi-note pi-hide" data-multi-source></p><p class="pi-note" data-freshness></p>
    <div data-sources></div>
  </div>
</div>
</main>
<div class="pi-modal" data-modal role="dialog" aria-modal="true" aria-labelledby="piModalTitle"><section class="pi-modal-card"><div style="display:flex;justify-content:space-between;gap:10px"><h2 id="piModalTitle">Unit details</h2><button class="pi-btn" type="button" data-modal-close aria-label="Close details">Close</button></div><dl class="pi-facts" data-modal-facts></dl></section></div>
<script>window.ProjectInventoryPage=<?= json_encode(['url'=>$dataUrl,'mapMode'=>$mapMode,'mapUrl'=>site_url('project-inventory/'.rawurlencode($project['slug']).'/map')],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) ?>;</script>
<script src="<?= e(\App\Libraries\PortalLinks::publicSite('assets/inventory/project-inventory.js')) ?>?v=1"></script>
<?php require ROOTPATH.'html/includes/footer.php'; ?>
