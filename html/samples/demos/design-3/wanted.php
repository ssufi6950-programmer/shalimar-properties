<?php
$active = 'wanted';
$pageTitle = 'Wanted Ads — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$wanted = [
  ['10 Marla plot in Smart City, Sector I', 'Buyer · Sargodha', 'Prefer Block A or C, near park. Ready for transfer this month.', 'PKR 1 – 1.25 Crore', 'fa-map'],
  ['5 Marla house in Gulberg City', 'Buyer · Sargodha', 'Double storey, possession within 30 days. Family of five.', 'PKR 2.2 – 2.7 Crore', 'fa-house'],
  ['Founder Office in Zalmi X', 'Investor · Lahore', '700 – 1,000 sq ft, mid floors. Instalment plan preferred.', 'PKR 2 – 3 Crore', 'fa-briefcase'],
  ['Upper portion for rent near Awan Chowk', 'Tenant · Sargodha', '2 bedrooms, separate entrance. Long lease OK.', 'PKR 35 – 50k / month', 'fa-key'],
  ['1 Kanal plot, Golf Avenue', 'Investor · Sargodha', 'Corner or park facing. Cash deal ready.', 'PKR 1.1 – 1.3 Crore', 'fa-golf-ball-tee'],
  ['2-bed apartment, Gulberg City', 'Buyer · Sargodha', 'Semi-furnished, first or second floor.', 'PKR 90 Lac – 1 Crore', 'fa-building'],
];
$wa = 'https://wa.me/' . SITE_WHATSAPP . '?text=';
?>
<main id="main">
<section class="page-hero">
  <div class="wrap page-hero-row">
    <div>
      <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Wanted</span></div>
      <h1>Buyers &amp; tenants looking now</h1>
      <p class="lede">Have something that fits? Offer a match through our team — contact details stay private until both sides agree.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><a href="#post" class="btn btn-solid">Post a wanted ad</a><a href="buy.php" class="btn btn-ghost">Browse listings</a></div>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <div class="wanted-grid">
      <?php foreach ($wanted as $i => $w): ?>
      <article class="wanted-card rv" style="--d:<?= ($i % 3) * 60 ?>ms">
        <span class="pill"><i class="fa-solid <?= $w[4] ?>"></i> <?= e($w[1]) ?></span>
        <h3><?= e($w[0]) ?></h3>
        <p><?= e($w[2]) ?></p>
        <div class="budget"><?= e($w[3]) ?></div>
        <a href="<?= e($wa . rawurlencode('I have a match for the wanted ad: ' . $w[0])) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" style="margin-top:12px"><i class="fa-brands fa-whatsapp"></i> Offer a match</a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" id="post">
  <div class="wrap">
    <div class="panel rv" style="max-width:720px;margin:0 auto">
      <h2 style="margin:0 0 4px;font-size:clamp(22px,2.6vw,28px)">Post what you’re looking for</h2>
      <p class="muted" style="margin:0 0 16px">Your request is sent to our team on WhatsApp and published after review.</p>
      <form id="wantedForm" class="form-grid">
        <div class="fld full"><label for="w-title">Headline</label><input id="w-title" required placeholder="e.g. 10 marla plot in Sector II"></div>
        <div class="fld"><label for="w-proj">Project / area</label><select id="w-proj"><option>Any</option><?php foreach (projects() as $p): ?><option><?= e($p['name']) ?></option><?php endforeach; ?><option>Other area</option></select></div>
        <div class="fld"><label for="w-type">Looking to</label><select id="w-type"><option>Buy</option><option>Rent</option><option>Invest</option></select></div>
        <div class="fld"><label for="w-budget">Budget range</label><input id="w-budget" placeholder="PKR 50 Lac – 1 Crore"></div>
        <div class="fld"><label for="w-phone">Your mobile</label><input id="w-phone" inputmode="tel" placeholder="03xx-xxxxxxx"></div>
        <div class="fld full"><label for="w-notes">Details</label><textarea id="w-notes" placeholder="Size, bedrooms, possession timing…"></textarea></div>
        <div class="full"><button class="btn btn-solid" type="submit"><i class="fa-brands fa-whatsapp"></i> Send wanted ad</button></div>
      </form>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById("wantedForm").addEventListener("submit", function (e) {
  e.preventDefault();
  const v = (id) => document.getElementById(id).value.trim();
  const msg = "Wanted ad\n" + v("w-title") + "\nProject: " + v("w-proj") + "\nLooking to: " + v("w-type") +
    "\nBudget: " + v("w-budget") + "\nMobile: " + v("w-phone") + "\nDetails: " + v("w-notes");
  window.open("https://wa.me/<?= e(SITE_WHATSAPP) ?>?text=" + encodeURIComponent(msg), "_blank", "noopener");
});
</script>
</body>
</html>
