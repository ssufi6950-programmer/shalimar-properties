<?php
$active = 'projects';
$pageTitle = 'Shalimar Projects — Shalimar Smart City, Gulberg City, Zalmi X';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/market.php';

$projects = projects();
$sel = get_str('project');
if ($sel !== '' && !isset($projects[$sel])) $sel = '';
$units = filter_listings(['sale','resale','project','rent'], ['project' => $sel]);
$officialMarket = market_snapshot();
$monthTx = $officialMarket['available'] ? ($officialMarket['project_month_counts'] ?? []) : [];
$liveCount = [];
foreach (live_listings() as $l) if ($l['project']) $liveCount[$l['project']] = ($liveCount[$l['project']] ?? 0) + 1;
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Projects</span></div>
    <h1>Shalimar Group projects</h1>
    <p class="lede">From master-planned communities in Sargodha to a premium commercial tower in Lahore — explore each project, its sectors and blocks, approved properties and official ERP market reporting.</p>
    <div class="proj-tabs chips-scroll" style="margin-top:14px">
      <?php foreach ($projects as $p): ?>
        <a class="proj-tab" href="#<?= e($p['slug']) ?>"><?php if (!empty($p['logo'])): ?><img src="<?= e(img_url($p['logo'])) ?>" alt=""><?php else: ?><span class="plogo-mono"><?= e(project_initials($p['name'])) ?></span><?php endif; ?><?= e($p['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" style="padding-top:35px">
  <div class="wrap">
    <div class="project-list">
      <?php $i = 0; foreach ($projects as $p): $m = project_media($p); ?>
      <article class="project rv" id="<?= e($p['slug']) ?>" style="--d:<?= ($i++ % 2) * 80 ?>ms">
        <div class="media">
          <img src="<?= e($m['cover_lg']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          <span class="plogo-wrap"><?= project_logo_html($p) ?></span>
        </div>
        <div class="info">
          <div class="tag-row"><span class="pill"><?= e($p['status']) ?></span><span class="pill gold"><?= e($p['kind']) ?></span></div>
          <h3><?= e($p['name']) ?></h3>
          <?php if ($m['tagline']): ?><p class="p-tag"><?= e($m['tagline']) ?></p><?php endif; ?>
          <p class="addr" style="margin:4px 0 8px"><i class="fa-solid fa-location-dot"></i> <?= e($p['location']) ?></p>
          <p><?= e($p['summary']) ?></p>
          <?php if ($m['features']): ?><div class="p-feats"><?php foreach ($m['features'] as $ft): ?><span><i class="fa-solid fa-diamond"></i><?= e($ft) ?></span><?php endforeach; ?></div><?php endif; ?>
          <div class="proj-facts">
            <div><small>Developer</small><b><?= e($p['developer']) ?></b></div>
            <div><small>Completed ERP resales this month</small><b><?= market_count($officialMarket['available']?(int)($monthTx[$p['slug']] ?? 0):null) ?></b></div>
            <div><small>Live listings</small><b><?= (int)($liveCount[$p['slug']] ?? 0) ?></b></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--muted)"><span>Development progress</span><strong style="color:var(--ink)"><?= (int)$p['progress'] ?>%</strong></div>
          <div class="progress"><i style="--w:<?= (int)$p['progress'] ?>%"></i></div>
          <?php if ($m['gallery']): ?><div class="p-gallery"><?php foreach (array_slice($m['gallery'], 1, 4) as $g): ?><a href="<?= e(photo($g, true)) ?>" data-lightbox="<?= e($p['slug']) ?>" aria-label="Open photo"><img src="<?= e(photo($g)) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
          <?= subprojects_html($p) ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px">
            <span class="price" style="font-size:17px;margin-right:auto"><?= e($p['price_from']) ?></span>
            <a href="projects.php?project=<?= e($p['slug']) ?>#units" class="btn btn-solid btn-sm">Available units</a>
            <a href="buy.php?project=<?= e($p['slug']) ?>" class="btn btn-ghost btn-sm">Resale</a>
            <a href="insights.php?project=<?= e($p['slug']) ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-chart-line"></i> Prices</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="units">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker">Direct from Shalimar Group</span><h2>Project units<?= $sel ? ' · ' . e($projects[$sel]['name']) : '' ?></h2><p>New bookings and units released by the developer. Sold units remain visible with a “Sold” tag.</p></div>
    </div>
    <div class="tag-row chips-scroll" style="margin-bottom:14px">
      <a href="projects.php#units" class="pill<?= $sel === '' ? '' : ' muted' ?>">All projects</a>
      <?php foreach ($projects as $p): ?><a href="projects.php?project=<?= e($p['slug']) ?>#units" class="pill<?= $sel === $p['slug'] ? '' : ' muted' ?>"><?= e($p['name']) ?></a><?php endforeach; ?>
    </div>
    <?php if ($units): ?>
      <div class="listing-grid"><?php foreach ($units as $i => $l) echo render_card($l, ($i % 3) * 60); ?></div>
    <?php else: ?>
      <div class="empty-note"><i class="fa-solid fa-city"></i>No units released for this project right now. <a class="gold" href="buy.php?project=<?= e($sel) ?>">See resale listings</a>.</div>
    <?php endif; ?>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap">
    <div class="cta-box rv rv-z">
      <div><h2>Investor briefing, backed by real numbers</h2><p>See daily and monthly transactions, most-traded sizes and price movement by block in our market insights.</p></div>
      <div class="cta-actions"><a href="insights.php" class="btn btn-light"><i class="fa-solid fa-chart-line"></i> Open market insights</a></div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
