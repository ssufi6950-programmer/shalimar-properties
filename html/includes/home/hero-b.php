<!-- ============ HERO: split — copy | group emblem ============ -->
<section class="h3-hero" id="hero">
  <div class="h3-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="jaali"></span></div>
  <div class="wrap h3-grid">
    <div class="h3-copy">
      <span class="h3-kicker"><b></b><?= e($H['kicker']) ?></span>
      <h1 class="h3-title hd-title">
        <span class="ln"><i><?= $H['l1'] ?></i></span>
        <span class="ln"><i><?= $H['l2'] ?></i></span>
      </h1>
      <p class="h3-lede"><?= e($H['lede']) ?></p>

      <form class="h3-search" id="heroSearch" action="buy.php" method="get">
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

      <div class="h3-stats">
        <div><b data-to="<?= count($projects) ?>">0</b><span>Communities</span></div>
        <div><b data-to="<?= count($live) ?>">0</b><span>Verified listings</span></div>
        <div><b data-to="<?= $agencyCount ?>">0</b><span>Authorized agencies</span></div>
          <div><b><?= market_count($kpi['month']) ?></b><span>Completed resales this month</span></div>
      </div>
    </div>

    <!-- group emblem composition -->
    <div class="h3-stage" id="stage" aria-label="Shalimar Group communities">
      <svg class="h3-rings" viewBox="0 0 400 400" aria-hidden="true">
        <defs>
          <radialGradient id="g3glow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#2FA06B" stop-opacity=".22"/><stop offset="1" stop-color="#2FA06B" stop-opacity="0"/></radialGradient>
          <linearGradient id="g3gold" x1="0" x2="1"><stop offset="0" stop-color="#C9A86A"/><stop offset="1" stop-color="#DCC598"/></linearGradient>
        </defs>
        <circle cx="200" cy="200" r="190" fill="url(#g3glow)"/>
        <g class="spin s1"><circle cx="200" cy="200" r="168" fill="none" stroke="url(#g3gold)" stroke-width=".8" stroke-dasharray="2 7"/><circle cx="200" cy="32" r="3.2" fill="#C9A86A"/></g>
        <g class="spin s2"><circle cx="200" cy="200" r="128" fill="none" stroke="#166D47" stroke-opacity=".28" stroke-width=".8"/><circle cx="328" cy="200" r="2.6" fill="#166D47"/><circle cx="72" cy="200" r="2.6" fill="#2FA06B"/></g>
        <g class="spin s3"><circle cx="200" cy="200" r="92" fill="none" stroke="url(#g3gold)" stroke-width=".7" stroke-dasharray="1 4"/></g>
        <g class="star" fill="none" stroke="#B08D57" stroke-opacity=".35" stroke-width=".7">
          <rect x="140" y="140" width="120" height="120"/><rect x="140" y="140" width="120" height="120" transform="rotate(45 200 200)"/>
        </g>
        <g class="h3-links" fill="none" stroke="#166D47" stroke-width="1" stroke-dasharray="3 5" stroke-opacity=".45">
          <path d="M200 200 L70 66"/><path d="M200 200 L340 150"/><path d="M200 200 L86 328"/><path d="M200 200 L330 318"/>
        </g>
      </svg>

      <div class="h3-emblem">
        <span class="pulse" aria-hidden="true"></span><span class="pulse p2" aria-hidden="true"></span>
        <div class="medal">
          <img src="assets/images/logos/sg-icon-hd.webp?v=1" alt="Shalimar Properties" width="600" height="600" decoding="async">
          <b>SHALIMAR</b><small>PROPERTIES</small>
        </div>
      </div>

      <?php foreach (array_slice($projects, 0, 3) as $i => $p): ?>
      <a class="h3-pcard <?= $cardPos[$i] ?>" href="projects.php#<?= e($p['slug']) ?>" aria-label="<?= e($p['name']) ?>" style="--d:<?= 500 + $i * 160 ?>ms;--f:<?= $i * 1.3 ?>s">
        <span class="pc-logo"><?= project_logo_html($p, 'plogo') ?></span>
        <span class="pc-txt"><small><i class="fa-solid fa-location-dot"></i> <?= e($p['city']) ?> · <?= (int)($liveByProject[$p['slug']] ?? 0) ?> live</small></span>
      </a>
      <?php endforeach; ?>

      <div class="h3-chip c4" style="--d:1100ms;--f:2.2s">
        <span class="mini-bars" aria-hidden="true"><i style="--h:40%"></i><i style="--h:65%"></i><i style="--h:50%"></i><i style="--h:85%"></i><i style="--h:70%"></i><i style="--h:100%"></i></span>
          <span><b><?= market_count($kpi['last_month']) ?></b><small>completed resales last month</small></span>
      </div>
      <div class="h3-chip c5" style="--d:1250ms;--f:.6s"><i class="fa-solid fa-circle-check"></i><span><b><?= count($cities) ?> cities</b><small><?= e(implode(' · ', $cities)) ?></small></span></div>

      <svg class="h3-skyline" viewBox="0 0 600 120" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <g stroke="#B08D57" stroke-width="1.1">
          <path pathLength="1" d="M10 114 H590"/>
          <path pathLength="1" d="M40 114 V70 H64 V114 M44 70 V62 H60 V70 M52 62 V52"/>
          <path pathLength="1" d="M78 114 V84 L96 70 L114 84 V114 M90 114 V98 H102 V114"/>
          <path pathLength="1" d="M126 114 V84 L144 70 L162 84 V114"/>
          <path pathLength="1" d="M196 114 V60 M190 60 H202 M196 60 V50 M196 50 C192 46 192 42 196 38 C200 42 200 46 196 50"/>
          <path pathLength="1" d="M214 114 V80 C214 58 238 48 254 40 C270 48 294 58 294 80 V114 M232 114 V92 C232 82 254 74 254 74 C254 74 276 82 276 92 V114 M254 40 V30"/>
          <path pathLength="1" d="M312 114 V60 M306 60 H318 M312 60 V50 M312 50 C308 46 308 42 312 38 C316 42 316 46 312 50"/>
        </g>
        <g stroke="#166D47" stroke-width="1.1">
          <path pathLength="1" d="M352 114 V22 H404 V114 M352 40 H404 M352 58 H404 M352 76 H404 M352 94 H404 M378 22 V114"/>
          <path pathLength="1" d="M420 114 V52 H458 V114 M420 68 H458 M420 84 H458 M420 100 H458"/>
          <path pathLength="1" d="M478 114 V82 C478 66 498 58 510 58 C522 58 542 66 542 82 V114 M494 114 V90 C494 82 510 78 510 78 C510 78 526 82 526 90 V114"/>
          <path pathLength="1" d="M556 114 V90 L568 80 L580 90 V114"/>
        </g>
      </svg>
    </div>
  </div>

  <div class="h3-marq" aria-hidden="true"><div class="track">
    <?php for ($k = 0; $k < 2; $k++): ?><span>
      <?php foreach ($projects as $p): ?><em><?= e($p['name']) ?></em><i>✦</i><?= e($p['location']) ?><i>✦</i><?php endforeach; ?>
      <em>Verified owners</em><i>✦</i><em>Authorized agencies</em><i>✦</i><em>Live market data</em><i>✦</i>
    </span><?php endfor; ?>
  </div></div>
</section>

