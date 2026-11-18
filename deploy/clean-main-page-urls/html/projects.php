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
    <div class="crumbs"><a href="home">Home</a><span>/</span><span>Projects</span></div>
    <h1>Shalimar Group projects</h1>
    <p class="lede">From master-planned communities in Sargodha to a premium commercial tower in Lahore — explore each project, its sectors and blocks, approved properties and official ERP market reporting.</p>
    <div class="proj-tabs chips-scroll" id="jsonProjectTabs" style="margin-top:14px"></div>
  </div>
</section>

<section class="sec-tight" style="padding-top:35px">
  <div class="wrap">
    <p id="jsonProjectsStatus" class="muted" role="status">Loading projects...</p>
    <div class="project-list" id="jsonProjects"></div>
    <div class="empty-note" id="jsonProjectsEmpty" hidden>No saved project content is currently available.</div>
  </div>
</section>

<section class="sec-tight" id="units">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker">Direct from Shalimar Group</span><h2>Project units<?= $sel ? ' · ' . e($projects[$sel]['name']) : '' ?></h2><p>New bookings and units released by the developer. Sold units remain visible with a “Sold” tag.</p></div>
    </div>
    <div class="tag-row chips-scroll" style="margin-bottom:14px">
      <a href="projects#units" class="pill<?= $sel === '' ? '' : ' muted' ?>">All projects</a>
      <?php foreach ($projects as $p): ?><a href="projects?project=<?= e($p['slug']) ?>#units" class="pill<?= $sel === $p['slug'] ? '' : ' muted' ?>"><?= e($p['name']) ?></a><?php endforeach; ?>
    </div>
    <?php if ($units): ?>
      <div class="listing-grid"><?php foreach ($units as $i => $l) echo render_card($l, ($i % 3) * 60); ?></div>
    <?php else: ?>
      <div class="empty-note"><i class="fa-solid fa-city"></i>No units released for this project right now. <a class="gold" href="buy?project=<?= e($sel) ?>">See resale listings</a>.</div>
    <?php endif; ?>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap">
    <div class="cta-box rv rv-z">
      <div><h2>Investor briefing, backed by real numbers</h2><p>See daily and monthly transactions, most-traded sizes and price movement by block in our market insights.</p></div>
      <div class="cta-actions"><a href="insights" class="btn btn-light"><i class="fa-solid fa-chart-line"></i> Open market insights</a></div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
window.JsonProjectPreview = <?= json_encode([
    'api' => \App\Libraries\PortalLinks::portal('api/projects'),
    'livePage' => true,
    'metrics' => array_map(static fn ($slug) => [
        'monthly' => market_count($officialMarket['available'] ? (int) ($monthTx[$slug] ?? 0) : null),
        'listings' => (int) ($liveCount[$slug] ?? 0),
    ], array_combine(array_keys($projects), array_keys($projects))),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= e(\App\Libraries\PortalLinks::portal('assets/projects/projects-json-preview.js')) ?>?v=2"></script>
</body>
</html>
