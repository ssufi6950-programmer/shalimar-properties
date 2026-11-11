<?php
$active = 'agencies';
$pageTitle = 'Authorized Agencies — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$search = get_str('q');
$city = get_str('city');
$agencies = array_values(array_filter(store_read('parties'), function ($p) use ($search, $city) {
    if ($p['role'] !== 'agency' || empty($p['active'])) return false;
    if ($search !== '' && mb_stripos($p['name'] . ' ' . $p['area'], $search) === false) return false;
    if ($city !== '' && strcasecmp($p['city'], $city) !== 0) return false;
    return true;
}));
$counts = [];
foreach (live_listings() as $l) if (!empty($l['poster']['party_id'])) $counts[$l['poster']['party_id']] = ($counts[$l['poster']['party_id']] ?? 0) + 1;
$cities = array_unique(array_map(fn($p) => $p['city'], array_filter(store_read('parties'), fn($p) => $p['role'] === 'agency')));
sort($cities);
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Agencies</span></div>
    <h1>Shalimar-authorized agencies</h1>
    <p class="lede">Only realtors authorized by Shalimar Group can publish instantly on this portal — every post they make is logged on record. Deal with confidence.</p>
    <form class="filters" method="get" action="agencies.php">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search agency or area" aria-label="Search agencies" style="flex:1 1 220px">
      <select name="city" aria-label="City"><option value="">All cities</option>
        <?php foreach ($cities as $c): ?><option<?= $city === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-solid" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Find agencies</button>
    </form>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <?php if ($agencies): ?>
    <div class="agency-grid">
      <?php foreach ($agencies as $i => $a): ?>
      <article class="agency rv" style="--d:<?= ($i % 3) * 60 ?>ms">
        <div class="logo"><?= e(project_initials($a['name'])) ?></div>
        <div>
          <h3><?= e($a['name']) ?></h3>
          <p class="meta"><i class="fa-solid fa-location-dot" style="color:var(--green)"></i> <?= e($a['area']) ?> · <?= e($a['city']) ?></p>
          <p class="stars">★ <?= e(number_format((float)$a['rating'], 1)) ?> <span class="muted">· <?= (int)$a['deals'] ?> deals · <?= (int)($counts[$a['id']] ?? 0) ?> live</span></p>
          <span class="vbadge"><i class="fa-solid fa-circle-check"></i> Authorized Agency · <?= e($a['code']) ?></span>
          <div class="acts">
            <a href="buy.php?agency=<?= (int)$a['id'] ?>" class="btn btn-solid btn-sm">View listings</a>
            <a href="tel:<?= e(digits($a['phone'])) ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-phone"></i> Call</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="empty-note"><i class="fa-solid fa-briefcase"></i>No agencies match your search. <a class="gold" href="agencies.php">Show all</a></div>
    <?php endif; ?>
    <p class="muted" style="font-size:12px;margin-top:12px"><span class="demo-flag"><i class="fa-solid fa-flask"></i> Demo</span> Agency names and numbers shown are sample data for the demo.</p>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap soft-band rv two-col">
    <div>
      <span class="kicker">For realtors</span>
      <h2 style="margin:0;font-size:clamp(22px,2.6vw,30px)">Become a Shalimar-authorized agency</h2>
      <p class="muted" style="margin:8px 0 0;max-width:46ch">Authorized agencies publish instantly with an “Authorized Agency” badge and appear in this directory. Contact our office to apply.</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <a href="tel:<?= e(digits(SITE_PHONE)) ?>" class="btn btn-solid"><i class="fa-solid fa-phone"></i> <?= e(SITE_PHONE) ?></a>
      <a href="post-property.php" class="btn btn-ghost">Post as an agency</a>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
