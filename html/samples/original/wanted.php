<?php
$active = 'wanted';
$pageTitle = 'Wanted Ads — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$wanted = [
  ['Family seeking 1 kanal in DHA Lahore','Buyer · Lahore','Need park-facing or corner. Budget flexible for clean title. Possession within 60 days.','PKR 8–12 cr','fa-house'],
  ['3-bed rental near Blue Area','Tenant · Islamabad','Furnished preferred. Long lease OK. Move-in by mid-month.','PKR 180–250k / mo','fa-key'],
  ['Corner plot in Bahria Town','Investor · Rawalpindi','5 or 10 marla, clear demarcation. Cash deal ready.','PKR 1.5–2.8 cr','fa-map'],
  ['Ground-floor shop · Gulberg','Business · Lahore','High footfall street only. 800–1,200 sq ft.','PKR 4–7 cr','fa-store'],
  ['Sea-view apartment · Clifton','Buyer · Karachi','2–3 bed, mid floors. Willing to wait for transfer-ready units.','PKR 5–8 cr','fa-building'],
  ['Farmhouse weekend lease','Tenant · Lahore','Bedian / BRB side. Groups of 12 max. Monthly or quarterly.','PKR 300–500k / mo','fa-tree'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Wanted</span></div>
    <h1>Buyers looking — match your listing</h1>
    <p class="lede">Serious wanted ads from verified users. If you own something that fits, reply through the platform — contact details stay private until both sides agree.</p>
    <div style="margin-top:28px;display:flex;flex-wrap:wrap;gap:12px">
      <a href="#post" class="btn btn-solid">Post a wanted ad</a>
      <a href="buy.php" class="btn btn-ghost">Browse for-sale homes</a>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2>Active wanted requests</h2>
        <p>Updated daily. Agents can pitch matching inventory in one click.</p>
      </div>
    </div>
    <div class="wanted-grid">
      <?php foreach ($wanted as $i => $w): ?>
      <article class="wanted-card rv" style="transition-delay:<?= ($i % 2) * 70 ?>ms">
        <span class="pill"><i class="fa-solid <?= $w[4] ?>"></i> <?= htmlspecialchars($w[1]) ?></span>
        <h3><?= htmlspecialchars($w[0]) ?></h3>
        <p><?= htmlspecialchars($w[2]) ?></p>
        <div class="budget"><?= $w[3] ?></div>
        <a href="#" class="btn btn-ghost" style="margin-top:16px;padding:10px 16px;font-size:14px">Offer a match</a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="post">
  <div class="wrap">
    <div class="panel rv" style="max-width:720px;margin:0 auto">
      <h2 style="margin:0 0 8px;font-size:clamp(24px,3vw,34px)">Post what you’re looking for</h2>
      <p style="color:var(--muted);margin:0 0 24px">We’ll review within 24 hours before it goes live.</p>
      <form class="calc-form" onsubmit="return false">
        <div class="field">
          <label for="w-title">Headline</label>
          <input id="w-title" placeholder="e.g. 10 marla house in Askari 11">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="field">
            <label for="w-city">City</label>
            <select id="w-city"><option>Lahore</option><option>Karachi</option><option>Islamabad</option><option>Rawalpindi</option></select>
          </div>
          <div class="field">
            <label for="w-type">Looking to</label>
            <select id="w-type"><option>Buy</option><option>Rent</option><option>Invest / plot</option></select>
          </div>
        </div>
        <div class="field">
          <label for="w-budget">Budget range</label>
          <input id="w-budget" placeholder="PKR 3–5 cr">
        </div>
        <div class="field">
          <label for="w-notes">Details</label>
          <input id="w-notes" placeholder="Beds, area, possession timing…">
        </div>
        <button class="btn btn-solid" type="button" style="margin-top:8px">Submit wanted ad</button>
      </form>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
