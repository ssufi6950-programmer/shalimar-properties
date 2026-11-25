<?php
// $directory contains only local, currently authorized public directory records.
$search=get_str('q');
$agencies=array_values(array_filter($directory,static function($agency)use($search){
    if($search!==''&&mb_stripos($agency['name'].' '.$agency['city'].' '.$agency['address'],$search)===false)return false;
    return true;
}));
?>
<style>
.public-agency-directory .agency{grid-template-columns:72px minmax(0,1fr);align-items:start}
.public-agency-directory .agency>div{min-width:0}
.public-agency-directory .agency h3,.public-agency-directory .agency .meta{overflow-wrap:anywhere}
.public-agency-directory .agency .logo{width:72px;height:72px;padding:7px;border:1px solid #e7e2d5;border-radius:16px;background:#fff;box-shadow:0 5px 16px rgba(20,55,39,.09);font-family:inherit;font-size:19px;transition:transform .22s ease,box-shadow .22s ease}
.public-agency-directory .agency:hover .logo{transform:translateY(-2px);box-shadow:0 8px 20px rgba(20,55,39,.14)}
.public-agency-directory .agency .logo img{display:block;width:100%;height:100%;object-fit:contain;padding:2px}
.public-agency-directory .agency .logo:not(.has-image){background:linear-gradient(145deg,var(--green),var(--green-deep));border-color:rgba(217,182,111,.75);box-shadow:inset 0 0 0 2px rgba(217,182,111,.18)}
@media(max-width:640px){.public-agency-directory .agency{grid-template-columns:60px minmax(0,1fr);gap:12px;padding:14px}.public-agency-directory .agency .logo{width:60px;height:60px;border-radius:14px;padding:6px}}
@media(prefers-reduced-motion:reduce){.public-agency-directory .agency .logo{transition:none}}
</style>
<section class="page-hero"><div class="wrap">
  <div class="crumbs"><a href="home">Home</a><span>/</span><span>Agencies</span></div>
  <h1>Authorized Agencies</h1>
  <p class="lede">Explore agencies currently authorized by SGC or Zalmi.</p>
  <form class="filters" method="get" action="agencies">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search agency or area" aria-label="Search agencies" style="flex:1 1 220px">
    <button class="btn btn-solid" type="submit">Find agencies</button>
    <?php if($search!==''): ?><a class="btn btn-ghost" href="agencies">Reset</a><?php endif ?>
  </form>
</div></section>
<section class="sec-tight public-agency-directory" style="padding-top:8px"><div class="wrap">
  <p class="muted" role="status"><?= count($agencies) ?> of <?= count($directory) ?> authorized agencies</p>
  <?php if($agencies): ?><div class="agency-grid">
    <?php foreach($agencies as$i=>$a): ?><article class="agency rv" style="--d:<?= ($i%3)*60 ?>ms">
      <div class="logo<?= !empty($a['logo'])?' has-image':'' ?>" aria-hidden="true"><?php if(!empty($a['logo'])): ?><img src="<?= e($a['logo']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.hidden=true;this.parentElement.classList.remove('has-image');this.nextElementSibling.hidden=false"><span hidden><?= e(project_initials($a['name'])) ?></span><?php else: ?><?= e(project_initials($a['name'])) ?><?php endif ?></div>
      <div>
        <h3><?= e($a['name']) ?></h3>
        <?php if($a['address']!==''||$a['city']!==''): ?><p class="meta"><?= e(implode(' · ',array_filter([$a['address'],$a['city']],static fn($v)=>$v!==''))) ?></p><?php endif ?>
        <span class="vbadge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Authorized Agency</span>
        <?php if($a['email']!==''): ?><p class="meta"><?= e($a['email']) ?></p><?php endif ?>
        <div class="acts">
          <?php if($a['portal_agency_id']!==null): ?><a class="btn btn-solid btn-sm" href="buy?agency=<?= $a['portal_agency_id'] ?>">Sale listings</a><a class="btn btn-ghost btn-sm" href="rent?agency=<?= $a['portal_agency_id'] ?>">Rent listings</a><?php endif ?>
          <?php if($a['tel']!==''): ?><a class="btn btn-ghost btn-sm" href="<?= e($a['tel']) ?>">Call</a><?php endif ?>
          <?php if($a['whatsapp']!==''): ?><a class="btn btn-ghost btn-sm" href="<?= e($a['whatsapp']) ?>">WhatsApp</a><?php endif ?>
          <?php if($a['mailto']!==''): ?><a class="btn btn-ghost btn-sm" href="<?= e($a['mailto']) ?>">Email</a><?php endif ?>
        </div>
      </div>
    </article><?php endforeach ?>
  </div><?php else: ?><div class="empty-note" role="status">No authorized agencies match your search. <a class="gold" href="agencies">Show all</a></div><?php endif ?>
</div></section>
