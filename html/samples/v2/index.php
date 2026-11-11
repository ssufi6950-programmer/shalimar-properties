<?php
$active = 'home';
$bodyClass = 'home';
$heroPhoto = '';
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
<?php
$slides = [];
foreach ($projects as $p) { $m = project_media($p); $slides[] = ['p' => $p, 'm' => $m]; }
$heroExtra = [['img' => 'ssc-gate-render', 'title' => 'Shalimar Smart City', 'sub' => 'Grand entrance · Mall Road, Sargodha', 'href' => 'projects.php#shalimar-smart-city']];
$amenities = [
  ['ssc-mosque', 'Grand Jamia Masjid', 'Shalimar Smart City'], ['gc-park', 'Parks & play areas', 'Gulberg City'],
  ['zx-office', 'Founder offices', 'Zalmi X, Lahore'], ['ssc-business-district', 'Smart Business District', 'Shalimar Smart City'],
  ['gc-boulevard', 'Tree-lined boulevards', 'Gulberg City'], ['zx-terrace', 'Rooftop terrace', 'Zalmi X, Lahore'],
  ['ssc-food-court', 'Food court', 'Shalimar Smart City'],
];
$typeTiles = [['plot', 'Plots', 'ssc-aerial-live', 'buy.php?type=plot'], ['house', 'Houses & villas', 'gc-aerial', 'buy.php?type=house'],
              ['apartment', 'Apartments', 'gc-apartments', 'buy.php?type=apartment'], ['office', 'Offices & shops', 'zx-building', 'projects.php?project=zalmi-x#units']];
?>
<section class="lux-hero" id="hero" aria-label="Featured Shalimar projects">
  <div class="lh-media" aria-hidden="true">
    <?php foreach ($slides as $i => $s): ?>
      <div class="lh-slide<?= $i === 0 ? ' on' : '' ?>"><img src="<?= e($s['m']['cover_lg']) ?>" alt="" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?> decoding="async"></div>
    <?php endforeach; ?>
  </div>
  <div class="lh-shade" aria-hidden="true"></div>

  <div class="wrap lh-inner">
    <div class="lh-copy">
      <span class="lh-kicker"><b></b><span class="hide-sm">The official property portal of Shalimar Group</span><span class="show-sm">Official portal · Shalimar Group</span></span>
      <h1 class="split-reveal">
        <span class="ln"><i>Verified addresses,</i></span>
        <span class="ln"><i><em>timeless</em> living.</i></span>
      </h1>
      <p class="lh-lede">Plots, villas, apartments and premium offices across Shalimar Smart City, Gulberg City and Zalmi X — listed only by verified owners and authorized realtors.</p>

      <form class="glass-search" id="heroSearch" action="buy.php" method="get">
        <div class="tabs" id="tabs">
          <span class="tab-pill" id="pill"></span>
          <button type="button" class="tab on" data-action="buy.php">Buy</button>
          <button type="button" class="tab" data-action="rent.php">Rent</button>
          <button type="button" class="tab" data-action="projects.php">Projects</button>
        </div>
        <div class="fields">
          <div class="field"><label for="loc">Location</label><input id="loc" name="q" placeholder="Sector II, Golf Avenue…"></div>
          <div class="field"><label for="hproj">Project</label>
            <select id="hproj" name="project"><option value="">All projects</option>
              <?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="typ">Type</label>
            <select id="typ" name="type"><option value="">Any type</option>
              <?php foreach (CATEGORIES as $c): ?><option value="<?= $c ?>"><?= e(category_label($c)) ?></option><?php endforeach; ?>
            </select></div>
          <button class="btn btn-gold" type="submit"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></button>
        </div>
      </form>
      <div class="lh-trust">
        <span><i class="fa-solid fa-circle-check"></i> Verified owners</span>
        <span><i class="fa-solid fa-shield-halved"></i> Authorized agencies</span>
        <span><i class="fa-solid fa-chart-line"></i> Live market data</span>
      </div>
    </div>

    <div class="lh-card" id="lhCard">
      <?php foreach ($slides as $i => $s): $p = $s['p']; ?>
      <a class="lh-cap<?= $i === 0 ? ' on' : '' ?>" href="projects.php#<?= e($p['slug']) ?>">
        <small>Now showing · <?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?>/<?= str_pad((string)count($slides), 2, '0', STR_PAD_LEFT) ?></small>
        <b><?= e($p['name']) ?></b>
        <span><?= e($s['m']['tagline']) ?></span>
        <em><i class="fa-solid fa-location-dot"></i> <?= e($p['location']) ?> <i class="fa-solid fa-arrow-right-long go"></i></em>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="lh-bar wrap" id="lhBar">
    <?php foreach ($slides as $i => $s): ?>
      <button type="button" class="lh-dot<?= $i === 0 ? ' on' : '' ?>" aria-label="Show <?= e($s['p']['name']) ?>"><i></i><span><?= e($s['p']['name']) ?></span></button>
    <?php endforeach; ?>
    <a href="#intro" class="lh-scroll" aria-label="Scroll down"><span></span></a>
  </div>
</section>

<section class="logo-strip" aria-label="Shalimar Group communities">
  <div class="wrap ls-in">
    <span class="ls-label">Our communities</span>
    <div class="ls-logos">
      <?php foreach ($projects as $p): ?>
        <a href="projects.php#<?= e($p['slug']) ?>" class="ls-logo" title="<?= e($p['name']) ?>"><?= project_logo_html($p, 'plogo') ?><span><?= (int)($byProject[$p['slug']] ?? 0) ?> deals this month</span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="intro" id="intro">
  <div class="wrap intro-grid">
    <div class="rv">
      <span class="kicker">The Shalimar standard</span>
      <h2 class="display">Every listing has an <em>accountable</em> owner — and every deal leaves a record.</h2>
    </div>
    <div class="intro-stats">
      <div class="istat rv" style="--d:0ms"><b data-to="<?= count($live) ?>">0</b><span>Verified live listings</span></div>
      <div class="istat rv" style="--d:90ms"><b data-to="<?= (int)$kpi['month'] ?>">0</b><span>Deals recorded this month</span></div>
      <div class="istat rv" style="--d:180ms"><b data-to="<?= $agencyCount ?>">0</b><span>Authorized agencies</span></div>
      <div class="istat rv" style="--d:270ms"><b data-to="<?= number_format((float)($kpi['yoy'] ?? 0), 1, '.', '') ?>" data-suffix="%" data-prefix="+">0</b><span>Avg. price change vs last year</span></div>
    </div>
  </div>
</section>

<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker">Signature communities</span><h2 class="display-sm">Our projects</h2></div>
      <a href="projects.php" class="link-more">Explore all projects <i class="fa-solid fa-arrow-right-long"></i></a>
    </div>
    <div class="proj-showcase">
      <?php $i = 0; foreach ($slides as $s): $p = $s['p']; $m = $s['m']; ?>
      <article class="pshow rv-img<?= $i === 0 ? ' big' : '' ?>" style="--d:<?= $i * 120 ?>ms">
        <img src="<?= e($i === 0 ? $m['cover_lg'] : $m['cover']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <div class="pshow-body">
          <span class="pshow-logo"><?= project_logo_html($p, 'plogo') ?></span>
          <h3><a class="stretched" href="projects.php#<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></h3>
          <p><?= e($m['tagline']) ?></p>
          <div class="pshow-meta"><span><i class="fa-solid fa-location-dot"></i> <?= e($p['city']) ?></span><span><i class="fa-solid fa-tag"></i> <?= e($p['price_from']) ?></span></div>
        </div>
        <span class="pshow-go" aria-hidden="true"><i class="fa-solid fa-arrow-right-long"></i></span>
      </article>
      <?php $i++; endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="types">
  <div class="wrap">
    <div class="type-tiles">
      <?php foreach ($typeTiles as $i => $t): $n = $t[0] === 'office' ? $catCount('office') + $catCount('commercial') : $catCount($t[0]); ?>
      <a href="<?= e($t[3]) ?>" class="ttile rv" style="--d:<?= $i * 70 ?>ms">
        <img src="<?= e(photo($t[2])) ?>" alt="" loading="lazy">
        <span class="ttile-txt"><b><?= e($t[1]) ?></b><small><?= $n ?> live listing<?= $n === 1 ? '' : 's' ?></small></span>
        <i class="fa-solid fa-arrow-right-long"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="featured">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker">Fresh &amp; verified</span><h2 class="display-sm">Latest listings</h2>
        <p>Posted by Shalimar customers and authorized realtors. Third-party ads go live only after review.</p></div>
      <div class="arrows">
        <button class="rq" data-rail-prev aria-label="Scroll left"><i class="fa-solid fa-arrow-left-long"></i></button>
        <button class="rq" data-rail-next aria-label="Scroll right"><i class="fa-solid fa-arrow-right-long"></i></button>
      </div>
    </div>
    <div class="rail">
      <?php foreach ($featured as $i => $l) echo render_card($l, ($i % 4) * 80); ?>
    </div>
    <a href="buy.php" class="link-more">View all listings <i class="fa-solid fa-arrow-right-long"></i></a>
  </div>
</section>

<section class="parallax-band" aria-label="Market intelligence">
  <div class="pb-media" data-parallax="0.18"><img src="<?= e(photo('ssc-gate-aerial', true)) ?>" alt="" loading="lazy"></div>
  <div class="wrap pb-in">
    <div class="rv">
      <span class="kicker light">Market intelligence</span>
      <h2 class="display light">Negotiate with real numbers, not rumours.</h2>
      <p>Daily and monthly transactions, the most-traded plot sizes and block-wise prices against last year — recorded by the Shalimar team as deals close.</p>
      <a href="insights.php" class="btn btn-gold"><i class="fa-solid fa-chart-line"></i> Open the market report</a>
    </div>
    <div class="pb-glass rv" style="--d:150ms">
      <div><b data-to="<?= (int)$kpi['today'] ?>">0</b><span>Deals today</span></div>
      <div><b data-to="<?= (int)$kpi['last_month'] ?>">0</b><span>Deals last month</span></div>
      <div><b><?= e(fmt_pkr($kpi['value30'], false)) ?></b><span>Traded · 30 days</span></div>
    </div>
  </div>
</section>

<section class="sec-tight" id="how">
  <div class="wrap split">
    <div class="collage" aria-hidden="true">
      <div class="c1 rv-img"><img src="<?= e(photo('gc-homes')) ?>" alt="" loading="lazy"></div>
      <div class="c2 rv-img" style="--d:160ms"><img src="<?= e(photo('ssc-business-bay')) ?>" alt="" loading="lazy"></div>
      <div class="c-badge rv" style="--d:300ms"><span class="ring"><i><?= (int)$kpi['today'] ?></i></span><div><strong>Deals recorded today</strong><span>Across all Shalimar projects</span></div></div>
    </div>
    <div>
      <span class="kicker rv">Why buyers trust us</span>
      <h2 class="display-sm rv">A portal built on verification</h2>
      <ol class="steps">
        <li class="rv" style="--d:0ms"><div><h4>Verified owners post directly</h4><p>Shalimar Group customers go live instantly with a “Verified Owner” badge.</p></div></li>
        <li class="rv" style="--d:80ms"><div><h4>Authorized agencies, on record</h4><p>Agency posts are published immediately and logged in our records.</p></div></li>
        <li class="rv" style="--d:160ms"><div><h4>Everyone else is reviewed</h4><p>Third-party ads stay pending until the Shalimar team approves them.</p></div></li>
        <li class="rv" style="--d:240ms"><div><h4>Status you can rely on</h4><p>When a deal closes, the listing shows “Sold” or “Rented” automatically.</p></div></li>
      </ol>
    </div>
  </div>
</section>

<section class="sec-tight" id="lifestyle">
  <div class="wrap">
    <div class="sec-head rv"><div><span class="kicker">Life at Shalimar</span><h2 class="display-sm">Amenities that define a community</h2></div>
      <div class="arrows"><button class="rq" data-rail-prev aria-label="Scroll left"><i class="fa-solid fa-arrow-left-long"></i></button><button class="rq" data-rail-next aria-label="Scroll right"><i class="fa-solid fa-arrow-right-long"></i></button></div></div>
    <div class="rail mosaic">
      <?php foreach ($amenities as $i => $a): ?>
      <figure class="mtile rv" style="--d:<?= ($i % 4) * 70 ?>ms"><img src="<?= e(photo($a[0])) ?>" alt="<?= e($a[1]) ?>" loading="lazy"><figcaption><b><?= e($a[1]) ?></b><span><?= e($a[2]) ?></span></figcaption></figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="stories">
  <div class="wrap">
    <div class="quotes rv">
      <span class="q-mark" aria-hidden="true">“</span>
      <div class="qtrack" id="qtrack">
        <?php foreach ($quotes as $q): ?>
        <div class="quote">
          <blockquote><?= e($q[0]) ?></blockquote>
          <div class="who"><span class="av"><?= e(project_initials($q[1])) ?></span><div><strong><?= e($q[1]) ?></strong><span><?= e($q[2]) ?></span></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="q-nav">
        <div class="qbar" id="qbar"><?php foreach ($quotes as $i => $q): ?><button class="<?= $i ? '' : 'on' ?>" aria-label="Quote <?= $i + 1 ?>"></button><?php endforeach; ?></div>
        <div class="arrows"><button class="rq" id="qprev" aria-label="Previous quote"><i class="fa-solid fa-arrow-left-long"></i></button><button class="rq" id="qnext" aria-label="Next quote"><i class="fa-solid fa-arrow-right-long"></i></button></div>
      </div>
    </div>
  </div>
</section>

<section class="sec-tight" id="list">
  <div class="wrap">
    <div class="cta-photo rv-img">
      <img src="<?= e(photo('gc-villas', true)) ?>" alt="" loading="lazy">
      <div class="cta-in">
        <span class="kicker light">For owners &amp; realtors</span>
        <h2 class="display light">Own a Shalimar plot or home? List it in two minutes.</h2>
        <p>Verified owners and authorized agencies go live instantly. Everyone else is reviewed by our team first.</p>
        <div class="cta-actions">
          <a href="post-property.php" class="btn btn-gold"><i class="fa-solid fa-plus"></i> Post a property</a>
          <a href="agencies.php" class="btn btn-glass"><i class="fa-solid fa-briefcase"></i> Authorized agencies</a>
        </div>
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
  const movePill = (el) => { pill.style.width = el.offsetWidth + "px"; pill.style.transform = "translateX(" + el.offsetLeft + "px)"; };
  tabs.querySelectorAll(".tab").forEach((t) => t.addEventListener("click", () => {
    tabs.querySelector(".tab.on").classList.remove("on"); t.classList.add("on"); movePill(t); form.action = t.dataset.action;
  }));
  const syncPill = () => movePill(tabs.querySelector(".tab.on"));
  addEventListener("load", syncPill); addEventListener("resize", syncPill); syncPill();

  /* hero slider (Ken Burns crossfade) */
  const slides = [...document.querySelectorAll(".lh-slide")], caps = [...document.querySelectorAll(".lh-cap")], dots = [...document.querySelectorAll(".lh-dot")];
  let cur = 0, timer;
  function go(n) {
    cur = (n + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle("on", i === cur));
    caps.forEach((s, i) => s.classList.toggle("on", i === cur));
    dots.forEach((d, i) => { d.classList.remove("on"); if (i === cur) { void d.offsetWidth; d.classList.add("on"); } });
    restart();
  }
  function restart() { clearInterval(timer); if (!reduce && slides.length > 1) timer = setInterval(() => go(cur + 1), 7000); }
  dots.forEach((d, i) => (d.onclick = () => go(i)));
  const hero = document.getElementById("hero");
  let tx = 0;
  hero.addEventListener("touchstart", (e) => (tx = e.touches[0].clientX), { passive: true });
  hero.addEventListener("touchend", (e) => { const d = e.changedTouches[0].clientX - tx; if (Math.abs(d) > 60 && !e.target.closest("form")) go(cur + (d < 0 ? 1 : -1)); });
  document.addEventListener("visibilitychange", () => (document.hidden ? clearInterval(timer) : restart()));
  restart();

  /* quotes */
  const qt = document.getElementById("qtrack"), qb = [...document.querySelectorAll("#qbar button")];
  let qi = 0, qtimer;
  const qgo = (n) => { qi = (n + qb.length) % qb.length; qt.style.transform = "translateX(-" + qi * 100 + "%)"; qb.forEach((b, i) => b.classList.toggle("on", i === qi)); };
  document.getElementById("qnext").onclick = () => qgo(qi + 1);
  document.getElementById("qprev").onclick = () => qgo(qi - 1);
  qb.forEach((b, i) => (b.onclick = () => qgo(i)));
  if (!reduce) qtimer = setInterval(() => qgo(qi + 1), 8000);
})();
</script>
</body>
</html>
