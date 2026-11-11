<?php
$slideImgs = [['villa', 'Golden hour'], ['villa-twilight', 'Twilight home'], ['villa-pool', 'Poolside villa'], ['villa-boulevard', 'Boulevard villas']];
$brandMsgs = [
  ['fa-shield-halved', '100% verified listings', 'Every ad comes from a verified owner or an authorized realtor.'],
  ['fa-landmark', 'Official portal of Shalimar Group', 'One trusted home for every Shalimar community.'],
  ['fa-handshake', 'Authorized realtors only', 'Agencies are approved by Shalimar and kept on record.'],
  ['fa-file-signature', 'Secure, on-record deals', 'Every transaction is logged by the Shalimar team.'],
];
?>
<section class="d-hero is-d2" id="hero">
  <div class="d-bg" aria-hidden="true"></div>
    <div class="d2-media" id="stage" aria-hidden="true">
    <?php foreach ($slideImgs as $k => $si): ?><div class="d2-slide<?= $k ? '' : ' on' ?>"><img src="assets/images/hero/<?= $si[0] ?>.webp" srcset="assets/images/hero/<?= $si[0] ?>-md.webp 1280w, assets/images/hero/<?= $si[0] ?>.webp 1920w" sizes="(max-width:860px) 100vw, 50vw" alt="" <?= $k ? 'loading="lazy"' : 'fetchpriority="high"' ?>></div><?php endforeach; ?>
    <svg class="d2-curve" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0 0 H18 C4 30 4 70 18 100 H0 Z" fill="currentColor"/></svg>
  </div>
  <div class="wrap d-grid">
    <div class="d-copy">
      <span class="d-kicker"><b></b><?= e($H['kicker']) ?></span>
      <h1 class="d-title hd-title">
        <span class="ln"><i><?= $H['l1'] ?></i></span>
        <span class="ln"><i><?= $H['l2'] ?></i></span>
      </h1>
      <p class="d-lede"><?= e($H['lede']) ?></p>

      <form class="h3-search d-search" id="heroSearch" action="buy.php" method="get">
        <div class="tabs" id="tabs">
          <span class="tab-pill" id="pill"></span>
          <button type="button" class="tab on" data-action="buy.php">Buy</button>
          <button type="button" class="tab" data-action="rent.php">Rent</button>
          <button type="button" class="tab" data-action="projects.php">Projects</button>
        </div>
        <div class="fields">
          <div class="field f-loc"><label for="loc"><i class="fa-solid fa-location-dot"></i> Location</label><input id="loc" name="q" placeholder="Sector or block"></div>
          <div class="field"><label for="hproj"><i class="fa-solid fa-city"></i> Project</label>
            <select id="hproj" name="project"><option value="">All projects</option>
              <?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="typ"><i class="fa-solid fa-shapes"></i> Type</label>
            <select id="typ" name="type"><option value="">Any type</option>
              <?php foreach (CATEGORIES as $c): ?><option value="<?= $c ?>"><?= e(category_label($c)) ?></option><?php endforeach; ?>
            </select></div>
          <button class="btn btn-solid h3-go" type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></button>
        </div>
      </form>

      <div class="h3-stats d-stats">
        <div><b data-to="<?= count($projects) ?>">0</b><span>Communities</span></div>
        <div><b data-to="<?= count($live) ?>">0</b><span>Verified listings</span></div>
        <div><b data-to="<?= $agencyCount ?>">0</b><span>Authorized agencies</span></div>
        <div><b data-to="<?= (int)$kpi['month'] ?>">0</b><span>Deals this month</span></div>
      </div>
    </div>

    <div class="d-visual">
      <div class="d-card hx-brand d2-brand" style="--d:700ms;--f:0s" aria-live="polite">
        <?php foreach ($brandMsgs as $k => $bm): ?>
        <span class="hl<?= $k ? '' : ' on' ?>"><span class="hb-ic"><i class="fa-solid <?= $bm[0] ?>"></i></span><span class="d-lt"><span class="d-badge">Shalimar Properties</span><b><?= e($bm[1]) ?></b><small><?= e($bm[2]) ?></small></span></span>
        <?php endforeach; ?>
      </div>
      <div class="d-card d-live d-trust" style="--d:1100ms;--f:.6s"><span class="avs" aria-hidden="true"><i>SM</i><i>AK</i><i>RF</i></span><span>Trusted by Shalimar<br>Group families</span></div>
      <div class="d-card d-logos" style="--d:1250ms;--f:2s">
        <small>Shalimar Group communities</small>
        <span class="d-lg"><?php foreach ($projects as $p): ?><a href="projects.php#<?= e($p['slug']) ?>" aria-label="<?= e($p['name']) ?>"><?= project_logo_html($p, 'plogo') ?></a><?php endforeach; ?></span>
      </div>
            <div class="d2-dots" id="d2Dots"><?php foreach ($slideImgs as $k => $si): ?><button class="<?= $k ? '' : 'on' ?>" type="button" aria-label="<?= e($si[1]) ?>"><i></i><span><?= e($si[1]) ?></span></button><?php endforeach; ?></div>
    </div>
  </div>

  <div class="h3-marq" aria-hidden="true"><div class="track">
    <?php for ($k = 0; $k < 2; $k++): ?><span>
      <?php foreach ($projects as $p): ?><em><?= e($p['name']) ?></em><i>✦</i><?= e($p['location']) ?><i>✦</i><?php endforeach; ?>
      <em>Verified owners</em><i>✦</i><em>Authorized agencies</em><i>✦</i><em>Live market data</em><i>✦</i>
    </span><?php endfor; ?>
  </div></div>
</section>

