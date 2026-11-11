<?php
$active = 'home';
$pageTitle = 'Shalimar Properties — Verified plots, homes & project units';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/market.php';

$live = filter_listings(['sale', 'resale', 'project', 'rent'], ['available' => 1]);
$featured = array_slice($live, 0, 8);
$projects = projects();
$tx = market_tx();
$kpi = market_kpis($tx);
$byProject = [];
foreach ($tx as $t) if (substr($t['date'], 0, 7) === date('Y-m')) $byProject[$t['project']] = ($byProject[$t['project']] ?? 0) + 1;
$agencyCount = count(array_filter(store_read('parties'), fn($p) => $p['role'] === 'agency' && !empty($p['active'])));
$catCount = function (string $c) use ($live) { return count(array_filter($live, fn($l) => $l['category'] === $c)); };
$quotes = [
  ['The plot file, price and block were exactly as listed. Transfer at the Shalimar office was done the same week.', 'Hamza Tariq', 'Bought 10 Marla · Shalimar Smart City'],
  ['I could see that the agency was authorized before I even called. That alone gave my family confidence.', 'Rabia Anwar', 'Bought a house · Gulberg City'],
  ['The market report showed me last month’s actual resale prices in Sector II. I negotiated with real numbers.', 'Faisal Nadeem', 'Investor · Sargodha'],
  ['Our rental was marked “Rented” the day the agreement was signed — no more calls for a house already taken.', 'Ayesha Khan', 'Owner · Gulberg City'],
];
?>
<main id="main">
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow"><b></b><?= count($live) ?> verified listings · <?= (int)$kpi['month'] ?> deals recorded this month</span>
      <h1>
        <span class="ln"><i>Own a piece of</i></span>
        <span class="ln"><i><em>Shalimar</em>, with confidence.</i></span>
      </h1>
      <p class="lede">Plots, homes and project units across Shalimar Smart City, Gulberg City and Zalmi X — posted only by verified owners and authorized realtors.</p>

      <form class="search" id="heroSearch" action="buy.php" method="get">
        <div class="tabs" id="tabs">
          <span class="tab-pill" id="pill"></span>
          <button type="button" class="tab on" data-action="buy.php">Buy</button>
          <button type="button" class="tab" data-action="rent.php">Rent</button>
          <button type="button" class="tab" data-action="projects.php">Projects</button>
        </div>
        <div class="fields">
          <div class="field">
            <label for="loc">Where</label>
            <input id="loc" name="q" placeholder="e.g. Sector II, Golf Avenue">
          </div>
          <div class="field">
            <label for="hproj">Project</label>
            <select id="hproj" name="project"><option value="">All projects</option>
              <?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="typ">Property type</label>
            <select id="typ" name="type"><option value="">Any type</option>
              <?php foreach (CATEGORIES as $c): ?><option value="<?= $c ?>"><?= e(category_label($c)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-solid" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        </div>
      </form>
      <div class="hero-trust">
        <span><i class="fa-solid fa-circle-check"></i> Verified owners</span>
        <span><i class="fa-solid fa-shield-halved"></i> Authorized agencies only</span>
        <span><i class="fa-solid fa-chart-line"></i> Live market data</span>
      </div>
    </div>

    <div class="stage" id="stage">
      <div class="dots" id="dots">
        <?php $i = 0; foreach ($projects as $p): ?><button class="dot<?= $i === 0 ? ' on' : '' ?>" aria-label="<?= e($p['name']) ?>"><i></i></button><?php $i++; endforeach; ?>
      </div>
      <div class="stage-nav">
        <button class="sq" id="prev" aria-label="Previous project"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="sq" id="next" aria-label="Next project"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
      <?php $i = 0; foreach ($projects as $p): ?>
      <article class="slide<?= $i === 0 ? ' on' : '' ?>">
        <div class="art"><img src="<?= e(img_url($p['cover'] ?? 'assets/images/hero-skyline.svg')) ?>" alt="<?= e($p['name']) ?>" <?= $i ? 'loading="lazy"' : '' ?>></div>
        <div class="slide-body">
          <?php if (!empty($p['logo'])): ?><span class="slide-logo"><img src="<?= e(img_url($p['logo'])) ?>" alt="<?= e($p['name']) ?> logo"></span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <div class="slide-meta">
            <span><i class="fa-solid fa-location-dot"></i><?= e($p['location']) ?></span>
            <span><i class="fa-solid fa-circle-check"></i><?= e($p['status']) ?></span>
            <span><i class="fa-solid fa-handshake"></i><?= (int)($byProject[$p['slug']] ?? 0) ?> deals this month</span>
          </div>
          <div class="slide-cta">
            <span class="slide-price"><?= e($p['price_from']) ?></span>
            <a href="projects.php#<?= e($p['slug']) ?>" class="btn btn-light btn-sm">Explore project <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </div>
      </article>
      <?php $i++; endforeach; ?>
    </div>
  </div>

  <div class="strip">
    <div class="marq" id="marq">
      <?php for ($r = 0; $r < 2; $r++): foreach ($projects as $p): ?>
        <span><?= e($p['name']) ?> <em>· <?= (int)($byProject[$p['slug']] ?? 0) ?> deals this month</em></span>
        <span>Plots &amp; homes in <b><?= e($p['city']) ?></b></span>
      <?php endforeach; endfor; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="areas">
  <div class="wrap">
    <div class="cats">
      <?php $cats = [['plot', 'Plots', 'fa-map'], ['house', 'Houses', 'fa-house'], ['apartment', 'Apartments', 'fa-building'], ['office', 'Offices & Shops', 'fa-briefcase']];
      foreach ($cats as $i => $c): $n = $c[0] === 'office' ? $catCount('office') + $catCount('commercial') : $catCount($c[0]); ?>
      <a href="<?= $c[0] === 'office' ? 'projects.php?project=zalmi-x#units' : 'buy.php?type=' . $c[0] ?>" class="cat rv" style="--d:<?= $i * 60 ?>ms">
        <span class="ico"><i class="fa-solid <?= $c[2] ?>"></i></span>
        <div><h4><?= e($c[1]) ?></h4><span><?= $n ?> live listing<?= $n === 1 ? '' : 's' ?></span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="featured">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="kicker">Fresh &amp; verified</span>
        <h2>Latest listings</h2>
        <p>Posted by Shalimar customers and authorized realtors. Third-party ads go live only after review.</p>
      </div>
      <div class="arrows">
        <button class="rq" data-rail-prev aria-label="Scroll left"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="rq" data-rail-next aria-label="Scroll right"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="rail">
      <?php foreach ($featured as $i => $l) echo render_card($l, ($i % 4) * 60); ?>
    </div>
    <a href="buy.php" class="link-more">View all listings <i class="fa-solid fa-arrow-right"></i></a>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap">
    <div class="band rv">
      <div class="band-grid">
        <div><div class="stat-num" data-to="<?= count($live) ?>">0</div><p>Verified live listings</p></div>
        <div><div class="stat-num" data-to="<?= (int)$kpi['month'] ?>">0</div><p>Transactions recorded this month</p></div>
        <div><div class="stat-num" data-to="<?= $agencyCount ?>">0</div><p>Authorized agencies</p></div>
        <div><div class="stat-num" data-to="<?= number_format((float)($kpi['yoy'] ?? 0), 1, '.', '') ?>" data-suffix="%" data-prefix="+">0</div><p>Avg. plot price change vs last year</p></div>
      </div>
    </div>
  </div>
</section>

<section class="sec-tight" id="how">
  <div class="wrap split">
    <div class="rv rv-l">
      <span class="kicker">Why buyers trust us</span>
      <h2 style="font-size:clamp(24px,2.8vw,34px);margin:0">A portal where every listing has an accountable owner</h2>
      <ol class="steps">
        <li><div><h4>Verified owners post directly</h4><p>Shalimar Group customers go live instantly with a “Verified Owner” badge.</p></div></li>
        <li><div><h4>Authorized agencies, on record</h4><p>Agency posts are published immediately and logged in our records.</p></div></li>
        <li><div><h4>Everyone else is reviewed</h4><p>Third-party ads stay pending until the Shalimar team approves them.</p></div></li>
        <li><div><h4>Status you can rely on</h4><p>When a deal closes, the listing shows “Sold” or “Rented” automatically.</p></div></li>
      </ol>
    </div>
    <div class="visual rv rv-r">
      <img src="assets/images/hero-skyline.svg" alt="Illustration of Shalimar communities" loading="lazy">
      <div class="float-card">
        <span class="ring"><i><?= (int)$kpi['today'] ?></i></span>
        <div><strong>Deals recorded today</strong><span>Across all Shalimar projects · <a href="insights.php" class="gold">see the report</a></span></div>
      </div>
    </div>
  </div>
</section>

<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker">Shalimar Group</span><h2>Our projects</h2></div>
      <a href="projects.php" class="link-more">All projects <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="listing-grid">
      <?php $i = 0; foreach ($projects as $p): ?>
      <article class="card rv" style="--d:<?= $i++ * 70 ?>ms">
        <div class="card-art"><img src="<?= e(img_url($p['cover'] ?? '')) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><span class="chip proj"><?= e($p['status']) ?></span></div>
        <div class="card-body">
          <?= project_logo_html($p) ?>
          <h4 style="margin-top:8px"><a class="stretched" href="projects.php#<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></h4>
          <p class="addr"><i class="fa-solid fa-location-dot"></i> <?= e($p['location']) ?></p>
          <div class="feats"><span><i class="fa-solid fa-tag"></i> <?= e($p['price_from']) ?></span></div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="stories">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker">In their words</span><h2>What our customers say</h2></div>
      <div class="arrows">
        <button class="rq" id="qprev" aria-label="Previous quote"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="rq" id="qnext" aria-label="Next quote"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="quotes rv">
      <div class="qtrack" id="qtrack">
        <?php foreach ($quotes as $q): ?>
        <div class="quote">
          <blockquote>“<?= e($q[0]) ?>”</blockquote>
          <div class="who"><span class="av"><?= e(project_initials($q[1])) ?></span><div><strong><?= e($q[1]) ?></strong><span><?= e($q[2]) ?></span></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="qbar" id="qbar"><?php foreach ($quotes as $i => $q): ?><button class="<?= $i ? '' : 'on' ?>" aria-label="Quote <?= $i + 1 ?>"></button><?php endforeach; ?></div>
    </div>
  </div>
</section>

<section class="sec-tight" id="list">
  <div class="wrap">
    <div class="cta-box rv rv-z">
      <div>
        <h2>Own a Shalimar plot or home? List it in two minutes.</h2>
        <p>Verified owners and authorized agencies go live instantly. Everyone else is reviewed by our team first.</p>
      </div>
      <div class="cta-actions">
        <a href="post-property.php" class="btn btn-light"><i class="fa-solid fa-plus"></i> Post a property</a>
        <a href="insights.php" class="btn" style="border-color:rgba(255,255,255,.4);color:#fff"><i class="fa-solid fa-chart-line"></i> Market report</a>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
(function () {
  const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
  /* search tabs: switch form target */
  const form = document.getElementById("heroSearch"), tabs = document.getElementById("tabs"), pill = document.getElementById("pill");
  const movePill = (el) => { pill.style.width = el.offsetWidth + "px"; pill.style.transform = "translateX(" + (el.offsetLeft) + "px)"; };
  tabs.querySelectorAll(".tab").forEach((t) => t.addEventListener("click", () => {
    tabs.querySelector(".tab.on").classList.remove("on"); t.classList.add("on"); movePill(t); form.action = t.dataset.action;
  }));
  const syncPill = () => movePill(tabs.querySelector(".tab.on"));
  addEventListener("load", syncPill); addEventListener("resize", syncPill); syncPill();

  /* project slider */
  const slides = [...document.querySelectorAll("#stage .slide")], dots = [...document.querySelectorAll("#dots .dot")];
  let cur = 0, timer;
  function go(n) {
    cur = (n + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle("on", i === cur));
    dots.forEach((d, i) => { d.classList.remove("on"); if (i === cur) { void d.offsetWidth; d.classList.add("on"); } });
    restart();
  }
  function restart() { clearInterval(timer); if (!reduce && slides.length > 1) timer = setInterval(() => go(cur + 1), 6500); }
  document.getElementById("next").onclick = () => go(cur + 1);
  document.getElementById("prev").onclick = () => go(cur - 1);
  dots.forEach((d, i) => (d.onclick = () => go(i)));
  const stage = document.getElementById("stage");
  stage.addEventListener("mouseenter", () => clearInterval(timer));
  stage.addEventListener("mouseleave", restart);
  let tx = 0;
  stage.addEventListener("touchstart", (e) => (tx = e.touches[0].clientX), { passive: true });
  stage.addEventListener("touchend", (e) => { const d = e.changedTouches[0].clientX - tx; if (Math.abs(d) > 45) go(cur + (d < 0 ? 1 : -1)); });
  restart();

  /* quotes */
  const qt = document.getElementById("qtrack"), qb = [...document.querySelectorAll("#qbar button")];
  let qi = 0;
  const qgo = (n) => { qi = (n + qb.length) % qb.length; qt.style.transform = "translateX(-" + qi * 100 + "%)"; qb.forEach((b, i) => b.classList.toggle("on", i === qi)); };
  document.getElementById("qnext").onclick = () => qgo(qi + 1);
  document.getElementById("qprev").onclick = () => qgo(qi - 1);
  qb.forEach((b, i) => (b.onclick = () => qgo(i)));
  if (!reduce) setInterval(() => qgo(qi + 1), 8000);
})();
</script>
</body>
</html>
