<?php
/* Shared split hero with photo slider — modes: d, e, f, g */
$heroMode = $heroMode ?? 'd';
$slideImgs = [['villa', 'Golden hour'], ['villa-twilight', 'Twilight home'], ['villa-pool', 'Poolside villa'], ['villa-boulevard', 'Boulevard villas']];
$slides = array_map(fn($si) => ['img' => 'assets/images/hero/' . $si[0], 'cap' => $si[1]], $slideImgs);
$brandMsgs = [
  ['fa-shield-halved', '100% verified listings', 'Every ad comes from a verified owner or an authorized realtor.'],
  ['fa-landmark', 'Official portal of Shalimar Group', 'One trusted home for every Shalimar community.'],
  ['fa-handshake', 'Authorized realtors only', 'Agencies are approved by Shalimar and kept on record.'],
  ['fa-file-signature', 'Secure, on-record deals', 'Every transaction is logged by the Shalimar team.'],
];
$N = count($slides);
$isH = in_array($heroMode, ['h', 'h2'], true);
$trust = [['fa-shield-halved', '100% verified listings', 'Owners & realtors checked'], ['fa-landmark', 'Official Shalimar Group portal', 'One home for every community'], ['fa-handshake', 'Authorized realtors only', 'Approved & on record'], ['fa-file-signature', 'Secure, on-record deals', 'Logged by the Shalimar team']];
$pad = fn($n) => str_pad((string)$n, 2, '0', STR_PAD_LEFT);
?>
<section class="hx hx-<?= $heroMode ?>" id="hero">
  <div class="hx-bg" aria-hidden="true"></div>
  <div class="wrap hx-grid">
    <div class="hx-copy">
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
      <?php if ($heroMode === 'h'): ?>
      <ul class="h-trust">
        <?php foreach ($trust as $k => $tp): ?><li style="--i:<?= $k ?>"><i class="fa-solid <?= $tp[0] ?>"></i><span><b><?= e($tp[1]) ?></b><small><?= e($tp[2]) ?></small></span></li><?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="h3-stats d-stats">
        <div><b data-to="<?= count($projects) ?>">0</b><span>Communities</span></div>
        <div><b data-to="<?= count($live) ?>">0</b><span>Verified listings</span></div>
        <div><b data-to="<?= $agencyCount ?>">0</b><span>Authorized agencies</span></div>
        <div><b data-to="<?= (int)$kpi['month'] ?>">0</b><span>Deals this month</span></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="hx-vis" id="hs" data-n="<?= $N ?>" aria-roledescription="carousel" aria-label="Featured homes">
      <?php if ($heroMode === 'g'): ?>
      <div class="g-mosaic">
        <?php foreach (['a', 'b', 'c'] as $fi => $fr): ?>
        <div class="g-frame g-<?= $fr ?>">
          <?php foreach ($slides as $k => $s): ?><img class="hs-item g-img" data-i="<?= ($k + $fi) % $N ?>" src="<?= e($s['img'] . ($fi ? '-sm' : '')) ?>.webp" alt="" <?= $k || $fi ? 'decoding="async"' : 'fetchpriority="high"' ?>><?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <span class="g-tag"><i class="fa-solid fa-house-chimney"></i> <span class="hs-txt" data-k="cap"><?= e($slides[0]['cap']) ?></span></span>
      </div>
      <div class="g-vprog" role="tablist">
        <?php foreach ($slides as $k => $s): ?><button type="button" class="hs-dot<?= $k ? '' : ' on' ?>" data-go="<?= $k ?>" aria-label="Slide <?= $k + 1 ?>"><em><?= $pad($k + 1) ?></em><i><i></i></i></button><?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="hx-stage">
        <?php if ($heroMode === 'f'): ?><span class="f-shadow" aria-hidden="true"></span><?php endif; ?>
        <?php if ($heroMode === 'h2'): ?><span class="h2-arch" aria-hidden="true"></span><span class="h2-orn" aria-hidden="true">✦</span><?php endif; ?>
        <div class="hx-frame">
          <?php foreach ($slides as $k => $s): ?>
          <div class="hs-item hx-slide<?= $k ? '' : ' on' ?>" data-i="<?= $k ?>" data-pos="<?= $k ?>">
            <img src="<?= e($s['img']) ?>.webp" srcset="<?= e($s['img']) ?>-md.webp 1280w, <?= e($s['img']) ?>.webp 1920w" sizes="(max-width:860px) 100vw, 50vw" alt="<?= e($s['cap']) ?>" <?= $k ? 'loading="lazy"' : 'fetchpriority="high"' ?> draggable="false">
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($heroMode === 'h2'): ?>
        <div class="h2-cap"><span class="h2-num"><b class="hs-txt" data-k="num">01</b> / <?= $pad($N) ?></span><em class="hs-txt" data-k="cap"><?= e($slides[0]['cap']) ?></em><small>Homes across the Shalimar Group communities</small></div>
        <?php endif; ?>
        <?php if ($heroMode === 'd'): ?>
        <div class="d-pager"><?php foreach ($slides as $k => $s): ?><button type="button" class="hs-dot<?= $k ? '' : ' on' ?>" data-go="<?= $k ?>" aria-label="Slide <?= $k + 1 ?>"><i></i></button><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
      <?php if ($heroMode === 'e'): ?>
      <div class="e-thumbs" role="tablist">
        <?php foreach ($slides as $k => $s): ?><button type="button" class="hs-dot<?= $k ? '' : ' on' ?>" data-go="<?= $k ?>" aria-label="<?= e($s['cap']) ?>"><img src="<?= e($s['img']) ?>-xs.webp" alt=""><span><?= e($s['cap']) ?></span><i><i></i></i></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($heroMode === 'h'): ?>
      <div class="h-cap">
        <span class="h-name"><em class="hs-txt" data-k="cap"><?= e($slides[0]['cap']) ?></em></span>
        <span class="h-line" aria-hidden="true"><i class="hs-bar"></i></span>
        <span class="h-num"><b class="hs-txt" data-k="num">01</b> / <?= $pad($N) ?></span>
      </div>
      <div class="h-thumbs" role="tablist">
        <?php foreach ($slides as $k => $s): ?><button type="button" class="hs-dot<?= $k ? '' : ' on' ?>" data-go="<?= $k ?>" aria-label="<?= e($s['cap']) ?>"><img src="<?= e($s['img']) ?>-xs.webp" alt=""></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($heroMode === 'h2'): $pThumb = ['shalimar-smart-city' => 'ssc-aerial', 'gulberg-city' => 'gc-villas', 'zalmi-x' => 'zx-building']; ?>
      <nav class="h2-projects" aria-label="Shalimar Group projects">
        <?php foreach ($projects as $k => $p): ?>
        <a class="h2-proj" href="projects.php#<?= e($p['slug']) ?>" style="--i:<?= $k ?>">
          <span class="h2-pimg"><img src="assets/images/photos/<?= e($pThumb[$p['slug']] ?? 'ssc-aerial') ?>-sm.webp" alt="" loading="lazy"><span class="h2-plogo"><?= project_logo_html($p, 'plogo') ?></span></span>
          <span class="h2-ptxt"><b><?= e($p['name']) ?></b><small><span class="h2-city"><i class="fa-solid fa-location-dot"></i> <?= e($p['city'] ?? '') ?></span><span class="h2-go">Explore <i class="fa-solid fa-arrow-right-long"></i></span></small></span>
        </a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
      <?php if ($heroMode === 'h2'): ?>
      <div class="h2-pag" role="tablist">
        <?php foreach ($slides as $k => $s): ?><button type="button" class="hs-dot<?= $k ? '' : ' on' ?>" data-go="<?= $k ?>" aria-label="<?= e($s['cap']) ?>"><em><?= $pad($k + 1) ?></em><i><i></i></i></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($heroMode === 'f'): ?>
      <div class="f-nav">
        <span class="f-count"><b class="hs-txt" data-k="num">01</b><small>/ <?= $pad($N) ?></small></span>
        <span class="f-cap hs-txt" data-k="cap"><?= e($slides[0]['cap']) ?></span>
        <span class="f-bar"><i class="hs-bar"></i></span>
        <button type="button" class="rq" data-step="-1" aria-label="Previous"><i class="fa-solid fa-arrow-left-long"></i></button>
        <button type="button" class="rq" data-step="1" aria-label="Next"><i class="fa-solid fa-arrow-right-long"></i></button>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (!$isH): ?>
      <!-- floating cards -->
      <div class="d-card hx-brand" style="--d:700ms;--f:0s" aria-live="polite">
        <?php foreach ($brandMsgs as $k => $bm): ?>
        <span class="hs-item hl<?= $k ? '' : ' on' ?>" data-i="<?= $k ?>">
          <span class="hb-ic"><i class="fa-solid <?= $bm[0] ?>"></i></span>
          <span class="d-lt"><span class="d-badge">Shalimar Properties</span><b><?= e($bm[1]) ?></b><small><?= e($bm[2]) ?></small></span>
        </span>
        <?php endforeach; ?>
      </div>
      <div class="d-card d-price" style="--d:900ms;--f:1.2s">
        <small><i class="fa-solid fa-chart-line"></i> Transparent market data</small>
        <b>Live prices</b>
        <svg viewBox="0 0 120 34" aria-hidden="true"><path d="M2 30 C14 28 20 22 30 24 S48 14 58 16 S78 8 88 10 S108 3 118 4" fill="none" stroke="#2FA06B" stroke-width="2" stroke-linecap="round" pathLength="1"/></svg>
        <small class="sub">Every block · every month</small>
      </div>
      <div class="d-card d-live d-trust" style="--d:1100ms;--f:.6s"><span class="avs" aria-hidden="true"><i>SM</i><i>AK</i><i>RF</i></span><span>Trusted by Shalimar<br>Group families</span></div>
      <div class="d-card d-logos" style="--d:1250ms;--f:2s">
        <small>Shalimar Group communities</small>
        <span class="d-lg"><?php foreach ($projects as $p): ?><a href="projects.php#<?= e($p['slug']) ?>" aria-label="<?= e($p['name']) ?>"><?= project_logo_html($p, 'plogo') ?></a><?php endforeach; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isH): ?>
  <div class="wrap">
    <?php if ($heroMode === 'h2'): ?>
    <ul class="h-trust h-trust-row">
      <?php foreach ($trust as $k => $tp): ?><li style="--i:<?= $k ?>"><i class="fa-solid <?= $tp[0] ?>"></i><span><b><?= e($tp[1]) ?></b><small><?= e($tp[2]) ?></small></span></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($heroMode !== 'h2'): ?>
    <div class="h-comm">
      <span class="h-comm-l"><span class="h-orn" aria-hidden="true">✦</span> The Shalimar Group communities</span>
      <span class="h-comm-logos"><?php foreach ($projects as $p): ?><a href="projects.php#<?= e($p['slug']) ?>" aria-label="<?= e($p['name']) ?>"><?= project_logo_html($p, 'plogo') ?></a><?php endforeach; ?></span>
      <?php if ($heroMode === 'h'): ?>
      <span class="h-comm-stats"><span><b data-to="<?= count($live) ?>">0</b> verified listings</span><span><b data-to="<?= $agencyCount ?>">0</b> authorized agencies</span><span><b data-to="<?= (int)$kpi['month'] ?>">0</b> deals this month</span></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="h3-marq" aria-hidden="true"><div class="track">
    <?php for ($k = 0; $k < 2; $k++): ?><span>
      <?php foreach ($projects as $p): ?><em><?= e($p['name']) ?></em><i>✦</i><?= e($p['location']) ?><i>✦</i><?php endforeach; ?>
      <em>Verified owners</em><i>✦</i><em>Authorized agencies</em><i>✦</i><em>Live market data</em><i>✦</i>
    </span><?php endfor; ?>
  </div></div>
  <?php endif; ?>
</section>
<script>
(function () {
  const root = document.getElementById("hs"), N = +root.dataset.n, mode = "<?= $heroMode ?>";
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const S = <?= json_encode(array_map(fn($s) => ['cap' => $s['cap']], $slides), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const DUR = 6000;
  let cur = 0, t, paused = false;
  function go(n, dir) {
    const prev = cur; cur = ((n % N) + N) % N;
    root.style.setProperty("--cur", cur);
    root.dataset.dir = dir || (cur > prev ? 1 : -1);
    root.querySelectorAll(".hs-item").forEach((el) => {
      const i = +el.dataset.i, on = i === cur;
      el.classList.toggle("was", i === prev && prev !== cur);
      el.classList.toggle("on", on);
      el.dataset.pos = (i - cur + N) % N;
    });
    root.querySelectorAll(".hs-dot").forEach((d, k) => { d.classList.remove("on"); if (k === cur) { void d.offsetWidth; d.classList.add("on"); } });
    root.querySelectorAll(".hs-txt").forEach((el) => { el.textContent = el.dataset.k === "num" ? String(cur + 1).padStart(2, "0") : S[cur][el.dataset.k]; el.classList.remove("swap"); void el.offsetWidth; el.classList.add("swap"); });
    root.querySelectorAll(".hs-bar").forEach((b) => { b.classList.remove("run"); void b.offsetWidth; b.classList.add("run"); });
    arm();
  }
  function arm() { clearTimeout(t); if (!reduce && !paused) t = setTimeout(() => go(cur + 1, 1), DUR); }
  root.querySelectorAll("[data-go]").forEach((b) => (b.onclick = () => go(+b.dataset.go)));
  root.querySelectorAll("[data-step]").forEach((b) => (b.onclick = () => go(cur + +b.dataset.step, +b.dataset.step)));
  root.addEventListener("mouseenter", () => { paused = true; root.classList.add("paused"); clearTimeout(t); });
  root.addEventListener("mouseleave", () => { paused = false; root.classList.remove("paused"); arm(); });
  /* swipe / drag */
  const frame = root.querySelector(".hx-frame, .g-mosaic");
  let sx = null, dx = 0;
  frame.addEventListener("pointerdown", (e) => { sx = e.clientX; dx = 0; frame.setPointerCapture && frame.setPointerCapture(e.pointerId); });
  frame.addEventListener("pointermove", (e) => { if (sx === null) return; dx = e.clientX - sx; if (mode === "f") root.style.setProperty("--drag", dx + "px"); });
  const end = () => { if (sx === null) return; root.style.setProperty("--drag", "0px"); if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); sx = null; };
  frame.addEventListener("pointerup", end); frame.addEventListener("pointercancel", end);
  document.addEventListener("visibilitychange", () => (document.hidden ? clearTimeout(t) : arm()));
  go(0, 1);
})();
</script>
