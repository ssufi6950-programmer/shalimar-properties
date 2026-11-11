<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/market.php';

$rawId = get_str('id',20);
$id = preg_match('/\A[1-9][0-9]{0,17}\z/D',$rawId) ? (int)$rawId : 0;
$l = $id ? find_listing($id) : null;
if ($l && !in_array($l['status'], ['live','approved','active'], true) && !is_admin()) $l = null;
if (!$l) {http_response_code(404);header('X-Robots-Tag: noindex, nofollow');}
$robots=(!$l||is_admin())?'noindex,nofollow':'index,follow';
if($l&&!is_admin()){
 $canonical=\App\Libraries\PublicWebsiteUrl::make('listing.php',['id'=>$id]);
 $metaDesc=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($l['description']??$l['title']))),0,160);
 $social=['title'=>$l['title'].' ? '.($l['purpose']==='rent'?'For Rent':'For Sale'),'description'=>$metaDesc,'url'=>$canonical,'image'=>listing_image($l)];
 $seo=['@context'=>'https://schema.org','@type'=>'RealEstateListing','name'=>$social['title'],'url'=>$canonical,'description'=>$metaDesc,'offers'=>['@type'=>'Offer','price'=>(string)$l['price'],'priceCurrency'=>'PKR','url'=>$canonical]];
 if($l['purpose']==='rent')$seo['offers']['priceSpecification']=['@type'=>'UnitPriceSpecification','price'=>(string)$l['price'],'priceCurrency'=>'PKR','unitText'=>'month'];
}


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
$deal = $l['deal'] ?? 'available';
$images = listing_gallery($l);
$back = ['rent' => 'rent.php', 'project' => 'projects.php'][$l['purpose']] ?? 'buy.php';
$waText = 'Assalam o Alaikum, I am interested in ' . $l['title'] . ' (Ref ' . $l['ref'] . ') listed on Shalimar Properties.';
$publicContact = \App\Libraries\PortalCatalogStore::connect()->publicContact((int)$l['id']);
$isAvailable = in_array($deal,['available','active'],true) && in_array($l['status'],['live','approved','active'],true);
$displayDeal = $l['status']==='sold'||$deal==='sold'?'sold':($isAvailable?'available':($deal==='available'?'unavailable':$deal));
$sellerSubtitle = $publicContact['subtitle'];
$sellerName = $publicContact['method']==='self_sell' ? ($publicContact['owner_name'] ?: $publicContact['name']) : $publicContact['name'];
$sellerRepresentative = '';

// Official reporting is separate from asking prices and monthly rents.
$similar = array_slice(array_filter(filter_listings([$l['purpose']], ['project' => $l['project'] ?? '', 'available' => 1]), fn($x) => $x['id'] !== $l['id']), 0, 6);
?>
<main id="main">
<section class="page-hero" style="padding-bottom:10px">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><a href="<?= $back ?>"><?= e(purpose_label($l['purpose'])) ?></a><span>/</span><span><?= e($l['ref']) ?></span></div>
    <?php if (!in_array($l['status'], ['live','approved','active'], true)): ?><div class="alert alert-info" style="margin-top:12px"><i class="fa-solid fa-user-shield"></i> Admin preview — this listing is <b><?= e($l['status']) ?></b> and not visible to the public.</div><?php endif; ?>
  </div>
</section>

<section class="sec-tight" style="padding-top:6px">
  <div class="wrap ld-grid">
    <div class="rv">
      <div class="gallery"><img width="900" height="600" decoding="async" id="gMain" src="<?= e(img_url($images[0])) ?>" alt="<?= e($l['title']) ?>">
        <span class="chip<?= $l['purpose'] === 'rent' ? ' rent' : ($l['purpose'] === 'project' ? ' proj' : '') ?>"><?= e(purpose_label($l['purpose'])) ?></span>
        <?php if ($deal !== 'available'): ?><span class="ribbon ribbon-<?= e($deal) ?>"><?= e(deal_label($deal)) ?></span><?php endif; ?>
      </div>
      <?php if (!images_public($l)||empty($l['images'])||is_placeholder((string)($l['images'][0]??''))): ?><p class="public-photo-placeholder">Property photo not provided. Illustration shown.</p><?php endif ?>
      <?php if (count($images) > 1): ?>
      <div class="gthumbs">
        <?php foreach ($images as $i => $img): ?><button type="button" class="<?= $i ? '' : 'on' ?>" data-src="<?= e(img_url($img)) ?>" aria-label="Photo <?= $i + 1 ?>"><img src="<?= e(img_url($img)) ?>" alt="" loading="lazy" width="120" height="80"></button><?php endforeach; ?>
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
        <div class="spec"><small>Category</small><b><?= e($l['sector'] ? cat_label($l['project'] ?? null, $l['sector']) : '—') ?></b></div>
        <div class="spec"><small>Sub-category</small><b><?= e($l['block'] ? sub_label($l['project'] ?? null, $l['sector'] ?? '', $l['block']) : '—') ?></b></div>
        <?php endif; ?>
        <div class="spec"><small>Reference</small><b><?= e($l['ref']) ?></b></div>
      </div>

      <div class="panel" style="margin-top:6px">
        <h3 style="font-size:18px">About this property</h3>
        <p class="muted" style="margin:6px 0 0;white-space:pre-line"><?= e($l['description']) ?></p>
        <?php if (strpos((string)$l['source'], 'jaageer') !== false): ?><p style="font-size:12px;margin:10px 0 0" class="muted"><span class="demo-flag"><i class="fa-solid fa-flask"></i> Sample</span> Details based on <?= e($l['source']) ?>.</p><?php endif; ?>
      </div>

      <?php if ($l['purpose']==='rent'&&!empty($l['rental'])): ?><section class="panel rental-facts"><h2 style="font-size:18px">Rental terms</h2><dl><?php foreach(['security_deposit'=>'Security deposit','advance_rent'=>'Advance rent','furnishing'=>'Furnishing','lease_duration_months'=>'Lease duration']as$key=>$label): $value=$l['rental'][$key]??null;if($value===null||$value==='')continue; ?><div><dt><?= e($label) ?></dt><dd><?= e(in_array($key,['security_deposit','advance_rent'],true)?fmt_pkr($value):($key==='lease_duration_months'?$value.' months':ucwords(str_replace('_',' ',(string)$value)))) ?></dd></div><?php endforeach ?></dl></section><?php endif ?>
      <div class="panel" style="margin-top:14px"><h3 style="font-size:18px">Official ERP market insights</h3><p class="muted">Asking prices and monthly rents are separate from completed ERP resale transactions.</p><a class="link-more" href="insights.php<?= $proj?'?project='.rawurlencode($l['project']):'' ?>">View verified market reporting</a></div>
    </div>

    <aside class="panel contact-card rv rv-r sticky-aside">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
        <span class="status-big <?= e($displayDeal) ?>" data-listing-availability><i class="fa-solid <?= $displayDeal === 'available' ? 'fa-circle-check' : 'fa-lock' ?>"></i> <?= e(deal_label($displayDeal)) ?></span>
        <?php if ($publicContact['badge']): ?><span class="vbadge" style="margin:0;flex-shrink:0" data-selling-method><i class="fa-solid <?= $publicContact['method']==='self_sell'?'fa-user':($publicContact['method']==='shalimar_agent'?'fa-user-tie':'fa-building') ?>" aria-hidden="true"></i> <?= e($publicContact['badge']) ?></span><?php endif; ?>
      </div>
      <p class="price" style="margin-top:10px"><?= listing_price($l) ?></p>
      <?php if (!empty($l['charges'])): $ch = $l['charges']; ?><div class="lp-charges" style="display:grid;gap:4px;margin:8px 0 4px;padding:10px 12px;border-radius:12px;background:var(--paper);border:1px solid var(--line);font-size:13px"><div style="display:flex;justify-content:space-between;gap:10px"><span class="muted">Service charges<?= ($ch['service_basis'] ?? '') === 'one_percent' ? ' (1%)' : ((($ch['service_basis'] ?? '') === 'half_rent') ? ' (half month rent)' : '') ?></span><b><?= e(fmt_pkr((int)($ch['service'] ?? 0))) ?></b></div><?php if (!empty($ch['docs'])): ?><div style="display:flex;justify-content:space-between;gap:10px"><span class="muted">Documentation</span><b><?= e(fmt_pkr((int)$ch['docs'])) ?></b></div><?php endif; ?><?php $gt = (int)$l['price'] + (int)($ch['service'] ?? 0) + (int)($ch['docs'] ?? 0); ?><div class="lp-total" style="display:flex;justify-content:space-between;gap:10px;margin-top:4px;padding-top:6px;border-top:1px dashed var(--line);font-size:14px"><b><?= ($l['purpose'] ?? '') === 'rent' ? 'Total (first month)' : 'Grand total' ?></b><b style="color:var(--green-deep)"><?= e(fmt_pkr($gt)) ?></b></div></div><?php endif; ?>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
      <div class="who" style="margin:0">
        <span class="av" aria-hidden="true"><?= e(project_initials($sellerName)) ?></span>
        <div style="min-width:0;overflow-wrap:anywhere"><strong data-seller-name><?= e($sellerName) ?></strong><span data-seller-description><?= e($sellerSubtitle) ?></span><?php if($sellerRepresentative!==''): ?><span style="display:block" data-seller-representative><?= e($sellerRepresentative) ?></span><?php endif; ?></div>
      </div>
      <div style="display:grid;gap:8px;margin-top:14px">
        <?php if ($isAvailable && $publicContact['available']): ?>
          <?php if ($publicContact['whatsapp']): ?><a class="btn btn-solid btn-block" data-listing-whatsapp href="<?= e($publicContact['whatsapp'].'?text='.rawurlencode($waText)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp <?= e($publicContact['target']) ?></a><?php endif; ?>
          <?php if ($publicContact['tel']): ?><a class="btn btn-ghost btn-block" data-listing-call href="<?= e($publicContact['tel']) ?>"><i class="fa-solid fa-phone"></i> Call <?= e($publicContact['target']) ?></a><?php endif; ?>
          <?php if ($publicContact['mailto']): ?><a class="btn btn-ghost btn-block" data-listing-email href="<?= e($publicContact['mailto'].'?subject='.rawurlencode('Property Enquiry — '.$l['ref'])) ?>"><i class="fa-regular fa-envelope"></i> Email <?= e($publicContact['target']) ?></a><?php endif; ?>
        <?php elseif ($isAvailable): ?>
          <p class="muted" role="status" data-contact-unavailable><?= e($publicContact['notice']) ?></p>
        <?php else: ?>
          <p class="muted" style="margin:0;font-size:13.5px"><?= $displayDeal==='sold'?'This property has been sold and is no longer available for purchase.':($displayDeal==='rented'?'This property has been rented out.':'This listing is not currently available for purchase.') ?></p>
          <a class="btn btn-ghost btn-block" href="<?= $back ?>">Browse similar</a>
        <?php endif; ?>
      </div>
      <?php if ($publicContact['available']): ?><p class="muted" style="font-size:12px;margin:12px 0 0" data-contact-notice><?= e($publicContact['notice']) ?></p><?php endif; ?>
      <?php if ($isAvailable && $publicContact['identity_available']): ?><p class="muted" style="font-size:12px;margin:12px 0 0" data-seller-footer><?= e($publicContact['description']) ?></p><?php endif; ?>
      <p class="muted" style="font-size:12px;margin:12px 0 0">Posted <?= e(date('d M Y', strtotime((string)($l['published_at'] ?: $l['created_at'])))) ?></p>
      <section aria-label="Shalimar Support" style="border-top:1px solid var(--line);margin-top:16px;padding-top:12px"><p class="muted" style="font-size:12px;margin:0 0 8px">Need Help? Contact Shalimar Support</p><a class="btn btn-ghost btn-block" href="tel:<?= e(SITE_TEL) ?>" data-shalimar-support><i class="fa-solid fa-headset"></i> Shalimar Support</a></section>
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
