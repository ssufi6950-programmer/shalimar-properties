<?php /* Signature communities — elegant editorial arches (root / design 5) */ ?>
<section class="sig" id="projects">
  <div class="wrap">
    <div class="sig-head rv">
      <span class="sig-kick"><i></i><?= e($T('kicker', 'Under the Shalimar umbrella')) ?><i></i></span>
      <h2><?= $T('title_h', 'Signature communities') ?></h2>
      <p><?= e($T('lede', 'Three distinct addresses, one trusted developer — each planned, built and managed by Shalimar Group.')) ?></p>
    </div>
    <div class="sig-grid">
      <?php foreach (array_values($projects) as $i => $p): $m = project_media($p);
        $img = !empty($m['gallery']) ? photo($m['gallery'][0], true) : $m['cover_lg'];
        $feats = array_slice($m['features'] ?? [], 0, 3);
        $from = preg_replace('/\s*\(.*$/', '', (string)($p['price_from'] ?? ''));
        $city = trim((string)($p['city'] ?? '')); ?>
      <article class="sig-card rv" style="--d:<?= 120 * $i ?>ms">
        <a class="sig-frame" href="projects.php#<?= e($p['slug']) ?>" aria-label="Explore <?= e($p['name']) ?>">
          <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          <span class="sig-no"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="sig-logo"><?= project_logo_html($p, 'plogo') ?></span>
        </a>
        <div class="sig-body">
          <span class="sig-city"><?= e($city !== '' ? $city : ($p['kind'] ?? '')) ?><?php if (!empty($p['kind'])): ?> · <?= e($p['kind']) ?><?php endif; ?></span>
          <h3><?= e($p['name']) ?></h3>
          <p class="sig-tag"><?= e($m['tagline'] ?? '') ?></p>
          <?php if ($feats): ?><ul class="sig-feats"><?php foreach ($feats as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul><?php endif; ?>
          <div class="sig-foot">
            <span><?php if ($from !== ''): ?><small>From</small><b><?= e($from) ?></b><?php else: ?><small>Verified</small><b><?= (int)($liveByProject[$p['slug']] ?? 0) ?> listings</b><?php endif; ?></span>
            <a href="projects.php#<?= e($p['slug']) ?>" class="sig-go">Explore <i class="fa-solid fa-arrow-right-long"></i></a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="sig-all rv"><a href="projects.php">View all projects <i class="fa-solid fa-arrow-right-long"></i></a></div>
  </div>
</section>
