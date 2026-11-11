<?php
$active = 'buy';
$pageTitle = 'Buy Plots & Homes — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$q = [
  'q' => get_str('q'), 'project' => get_str('project'), 'type' => get_str('type'),
  'beds' => (int)get_str('beds'), 'verified' => get_str('verified') !== '', 'available' => get_str('available') !== '',
  'agency' => (int)get_str('agency'),
];
if (!in_array($q['type'], CATEGORIES, true)) $q['type'] = '';
[$q['min'], $q['max']] = budget_range(get_str('budget'));
$rows = filter_listings(['sale', 'resale', 'project'], $q);
$agency = $q['agency'] ? party($q['agency']) : null;
$tabs = ['' => ['All', 'fa-border-all'], 'plot' => ['Plots', 'fa-map'], 'house' => ['Houses', 'fa-house'], 'apartment' => ['Apartments', 'fa-building'], 'office' => ['Offices', 'fa-briefcase'], 'commercial' => ['Shops', 'fa-store']];
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Buy</span></div>
    <h1>Plots &amp; homes for sale</h1>
    <p class="lede">Resale plots, plot files, houses and project units in Shalimar communities — every listing posted by a verified owner, an authorized agency, or approved by our team.</p>

    <form class="find-box" method="get" action="buy.php">
      <input type="hidden" name="type" id="buyType" value="<?= e($q['type']) ?>">
      <?php if ($q['agency']): ?><input type="hidden" name="agency" value="<?= (int)$q['agency'] ?>"><?php endif; ?>
      <div class="find-tabs" data-target="buyType" data-autosubmit="1" role="tablist">
        <?php foreach ($tabs as $k => $t): ?>
          <button type="button" class="find-tab<?= $q['type'] === $k ? ' on' : '' ?>" data-value="<?= e($k) ?>"><i class="fa-solid <?= $t[1] ?>"></i> <?= e($t[0]) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="find-grid">
        <div class="find-field">
          <label for="buy-loc"><i class="fa-solid fa-location-dot"></i> Sector, block or area</label>
          <input id="buy-loc" name="q" type="search" value="<?= e($q['q']) ?>" placeholder="Sector II, Golf Avenue, Awan Chowk…">
        </div>
        <div class="find-field">
          <label for="buy-proj"><i class="fa-solid fa-city"></i> Project</label>
          <select id="buy-proj" name="project"><option value="">All projects</option>
            <?php foreach (projects() as $p): ?><option value="<?= e($p['slug']) ?>"<?= $q['project'] === $p['slug'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="find-field">
          <label for="buy-budget"><i class="fa-solid fa-coins"></i> Budget</label>
          <select id="buy-budget" name="budget">
            <?php foreach (['' => 'Any budget', 'u50l' => 'Under 50 Lac', '50l-1c' => '50 Lac – 1 Crore', '1c-2c' => '1 – 2 Crore', '2c-5c' => '2 – 5 Crore', '5c+' => '5 Crore +'] as $k => $v): ?>
              <option value="<?= $k ?>"<?= get_str('budget') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="find-field">
          <label for="buy-beds"><i class="fa-solid fa-bed"></i> Bedrooms</label>
          <select id="buy-beds" name="beds">
            <?php foreach ([0 => 'Any', 2 => '2+', 3 => '3+', 4 => '4+', 5 => '5+'] as $k => $v): ?><option value="<?= $k ?>"<?= $q['beds'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-solid" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
      </div>
      <div class="find-extra">
        <label class="find-chip"><input type="checkbox" name="verified" value="1"<?= $q['verified'] ? ' checked' : '' ?> onchange="this.form.submit()"><i class="fa-solid fa-circle-check"></i> Verified owner / agency only</label>
        <label class="find-chip"><input type="checkbox" name="available" value="1"<?= $q['available'] ? ' checked' : '' ?> onchange="this.form.submit()"><i class="fa-solid fa-eye-slash"></i> Hide sold</label>
        <a class="find-chip" href="insights.php"><i class="fa-solid fa-chart-line"></i> Check market prices</a>
        <a class="find-chip" href="buy.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <div class="result-bar">
      <div>
        <h2><?= count($rows) ?> propert<?= count($rows) === 1 ? 'y' : 'ies' ?> for sale<?= $agency ? ' by ' . e($agency['name']) : '' ?></h2>
        <p>Available listings first · newest first. Sold properties stay visible with a “Sold” tag for price reference.</p>
      </div>
      <a href="post-property.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i> Sell your property</a>
    </div>
    <?php if ($rows): ?>
      <div class="listing-grid">
        <?php foreach ($rows as $i => $l) echo render_card($l, ($i % 3) * 60); ?>
      </div>
    <?php else: ?>
      <div class="empty-note"><i class="fa-solid fa-magnifying-glass"></i>No listings match these filters. <a class="gold" href="buy.php">Clear filters</a> or <a class="gold" href="wanted.php">post a wanted ad</a>.</div>
    <?php endif; ?>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap">
    <div class="soft-band rv two-col">
      <div>
        <span class="kicker">Buyer protection</span>
        <h2 style="margin:0;font-size:clamp(22px,2.6vw,30px)">Know who is selling before you call</h2>
        <p class="muted" style="margin:8px 0 0;max-width:46ch">Badges show whether a listing comes from a verified Shalimar customer, an authorized agency or the Shalimar Group itself. Third-party listings are reviewed before they appear.</p>
      </div>
      <div class="panel">
        <ol class="steps" style="margin:0">
          <li><div><h4>Verified Owner</h4><p>Posted by the registered plot/file owner.</p></div></li>
          <li><div><h4>Authorized Agency</h4><p>Posted by a Shalimar-authorized realtor, on record.</p></div></li>
          <li><div><h4>Reviewed by admin</h4><p>Third-party listing approved by our team.</p></div></li>
        </ol>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
