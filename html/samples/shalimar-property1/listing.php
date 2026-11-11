<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/market.php';

$id = (int)get_str('id');
$l = $id ? find_listing($id) : null;
if ($l && $l['status'] !== 'live' && !is_admin()) $l = null;
if (!$l) http_response_code(404);

$active = $l ? ($l['purpose'] === 'rent' ? 'rent' : ($l['purpose'] === 'project' ? 'projects' : 'buy')) : '';
$pageTitle = $l ? $l['title'] . ' — Shalimar Properties' : 'Listing not found — Shalimar Properties';
require __DIR__ . '/includes/header.php';

if (!$l): ?>
<main id="main"><section class="page-hero"><div class="wrap">
  <div class="empty-note"><i class="fa-solid fa-house-circle-xmark"></i>This listing is not available. It may be awaiting review or has been removed.<br><br><a class="btn btn-solid" href="buy.php">Browse listings</a></div>
</div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?></body></html>
<?php exit; endif;

$proj = project($l['project'] ?? null);
$badge = listing_badge($l);
$deal = $l['deal'] ?? 'available';
$images = $l['images'] ?: [category_art($l['category'])];
$back = ['rent' => 'rent.php', 'project' => 'projects.php'][$l['purpose']] ?? 'buy.php';
$waText = 'Assalam o Alaikum, I am interested in ' . $l['title'] . ' (Ref ' . $l['ref'] . ') listed on Shalimar Properties.';

// Market context: recent deals in the same block / sector
$ctx = [];
if ($proj) {
    $f = ['project' => $l['project'], 'block' => $l['block'] ?: null];
    $ctx = array_slice(array_reverse(market_tx(array_filter($f))), 0, 6);
    $rates = market_prices(market_tx(['project' => $l['project']]));
    $rateRow = null;
    foreach ($rates as $r) if ($r['block'] === $l['block']) $rateRow = $r;
}
$similar = array_slice(array_filter(filter_listings([$l['purpose']], ['project' => $l['project'] ?? '', 'available' => 1]), fn($x) => $x['id'] !== $l['id']), 0, 6);
?>
<main id="main">
<section class="page-hero" style="padding-bottom:10px">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><a href="<?= $back ?>"><?= e(purpose_label($l['purpose'])) ?></a><span>/</span><span><?= e($l['ref']) ?></span></div>
    <?php if ($l['status'] !== 'live'): ?><div class="alert alert-info" style="margin-top:12px"><i class="fa-solid fa-user-shield"></i> Admin preview — this listing is <b><?= e($l['status']) ?></b> and not visible to the public.</div><?php endif; ?>
  </div>
</section>

<section class="sec-tight" style="padding-top:6px">
  <div class="wrap ld-grid">
    <div class="rv">
      <div class="gallery"><img id="gMain" src="<?= e(img_url($images[0])) ?>" alt="<?= e($l['title']) ?>">
        <span class="chip<?= $l['purpose'] === 'rent' ? ' rent' : ($l['purpose'] === 'project' ? ' proj' : '') ?>"><?= e(purpose_label($l['purpose'])) ?></span>
        <?php if ($deal !== 'available'): ?><span class="ribbon ribbon-<?= e($deal) ?>"><?= e(deal_label($deal)) ?></span><?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
      <div class="gthumbs">
        <?php foreach ($images as $i => $img): ?><button type="button" class="<?= $i ? '' : 'on' ?>" data-src="<?= e(img_url($img)) ?>" aria-label="Photo <?= $i + 1 ?>"><img src="<?= e(img_url($img)) ?>" alt=""></button><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <h1 style="font-size:clamp(22px,2.8vw,32px);margin:16px 0 4px"><?= e($l['title']) ?></h1>
      <p class="addr" style="font-size:14px"><i class="fa-solid fa-location-dot"></i> <?= e(listing_location($l)) ?><?= $l['sector'] ? ' · ' . e($l['sector']) : '' ?></p>

      <div class="spec-grid">
        <div class="spec"><small>Size</small><b><?= e(size_text($l['size'], $l['unit'])) ?></b></div>
        <div class="spec"><small>Type</small><b><?= e(category_label($l['category'])) ?></b></div>
        <div class="spec"><small>Bedrooms</small><b><?= $l['beds'] ? (int)$l['beds'] : '—' ?></b></div>
        <div class="spec"><small>Bathrooms</small><b><?= $l['baths'] ? (int)$l['baths'] : '—' ?></b></div>
        <?php if ($proj): ?>
        <div class="spec"><small>Project</small><b><?= e($proj['name']) ?></b></div>
        <div class="spec"><small>Sector</small><b><?= e($l['sector'] ?: '—') ?></b></div>
        <div class="spec"><small>Block</small><b><?= e($l['block'] ?: '—') ?></b></div>
        <?php endif; ?>
        <div class="spec"><small>Reference</small><b><?= e($l['ref']) ?></b></div>
      </div>

      <div class="panel" style="margin-top:6px">
        <h3 style="font-size:18px">About this property</h3>
        <p class="muted" style="margin:6px 0 0;white-space:pre-line"><?= e($l['description']) ?></p>
        <?php if (strpos((string)$l['source'], 'jaageer') !== false): ?><p style="font-size:12px;margin:10px 0 0" class="muted"><span class="demo-flag"><i class="fa-solid fa-flask"></i> Sample</span> Details based on <?= e($l['source']) ?>.</p><?php endif; ?>
      </div>

      <?php if ($proj && $ctx): ?>
      <div class="panel" style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline">
          <h3 style="font-size:18px">Recent deals in <?= e($l['block'] ?: $proj['name']) ?></h3>
          <a class="link-more" href="insights.php?project=<?= e($l['project']) ?>&amp;block=<?= urlencode((string)$l['block']) ?>">Full report <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <?php if (!empty($rateRow) && $rateRow['now'] > 0): ?>
          <p class="muted" style="margin:4px 0 10px;font-size:13.5px">Average rate (last 90 days): <b style="color:var(--ink)"><?= e(fmt_rate($rateRow['now'], $rateRow['unit'])) ?></b>
          <?php if ($rateRow['change'] !== null): ?> · <span class="<?= $rateRow['change'] >= 0 ? 'up-txt' : 'down-txt' ?>"><?= $rateRow['change'] >= 0 ? '▲' : '▼' ?> <?= number_format(abs($rateRow['change']), 1) ?>%</span> vs a year ago<?php endif; ?></p>
        <?php endif; ?>
        <div class="table-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Type</th><th>Size</th><th>Block</th><th class="num">Price</th></tr></thead><tbody>
          <?php foreach ($ctx as $t): ?><tr><td><?= e(date('d M Y', strtotime($t['date']))) ?></td><td><?= e(TX_TYPES[$t['type']] ?? $t['type']) ?></td><td><?= e(size_text($t['size'], $t['unit'])) ?></td><td><?= e($t['block']) ?></td><td class="num"><?= e(fmt_pkr($t['price'])) ?><?= $t['type'] === 'rent' ? ' /mo' : '' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>
      <?php endif; ?>
    </div>

    <aside class="panel contact-card rv rv-r sticky-aside">
      <span class="status-big <?= e($deal) ?>"><i class="fa-solid <?= $deal === 'available' ? 'fa-circle-check' : 'fa-lock' ?>"></i> <?= e(deal_label($deal)) ?></span>
      <p class="price" style="margin-top:10px"><?= listing_price($l) ?></p>
      <?php if ($badge): ?><span class="vbadge lg"><i class="fa-solid fa-circle-check"></i> <?= e($badge) ?></span><?php else: ?><span class="vbadge vbadge-muted lg"><i class="fa-regular fa-clock"></i> Third-party · reviewed by admin</span><?php endif; ?>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
      <div class="who" style="margin:0">
        <span class="av"><?= e(project_initials($l['poster']['name'])) ?></span>
        <div><strong><?= e($l['poster']['name']) ?></strong><span><?= e(role_of($l)['label'] ?? '') ?></span></div>
      </div>
      <div style="display:grid;gap:8px;margin-top:14px">
        <?php if ($deal === 'available'): ?>
          <a class="btn btn-solid btn-block" href="https://wa.me/<?= e(SITE_WHATSAPP) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp enquiry</a>
          <a class="btn btn-ghost btn-block" href="tel:<?= e(digits(SITE_PHONE)) ?>"><i class="fa-solid fa-phone"></i> Call <?= e(SITE_PHONE) ?></a>
        <?php else: ?>
          <p class="muted" style="margin:0;font-size:13.5px">This property has been <?= $deal === 'rented' ? 'rented out' : 'sold' ?>. See similar options below.</p>
          <a class="btn btn-ghost btn-block" href="<?= $back ?>">Browse similar</a>
        <?php endif; ?>
      </div>
      <p class="muted" style="font-size:12px;margin:12px 0 0">Posted <?= e(date('d M Y', strtotime((string)($l['published_at'] ?: $l['created_at'])))) ?> · Enquiries are routed through the Shalimar help line.</p>
      <?php if (is_admin()): ?><a class="btn btn-ghost btn-sm" style="margin-top:10px" href="admin.php?tab=listings&amp;q=<?= e($l['ref']) ?>"><i class="fa-solid fa-user-shield"></i> Manage in admin</a><?php endif; ?>
    </aside>
  </div>
</section>

<?php if ($similar): ?>
<section class="sec-tight">
  <div class="wrap">
    <div class="sec-head"><div><h2>Similar listings</h2></div>
      <div class="arrows"><button class="rq" data-rail-prev aria-label="Scroll left"><i class="fa-solid fa-chevron-left"></i></button><button class="rq" data-rail-next aria-label="Scroll right"><i class="fa-solid fa-chevron-right"></i></button></div></div>
    <div class="rail"><?php foreach (array_values($similar) as $i => $s) echo render_card($s, $i * 50); ?></div>
  </div>
</section>
<?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
document.querySelectorAll(".gthumbs button").forEach((b) => b.addEventListener("click", () => {
  document.getElementById("gMain").src = b.dataset.src;
  document.querySelectorAll(".gthumbs button").forEach((x) => x.classList.toggle("on", x === b));
}));
</script>
</body>
</html>
