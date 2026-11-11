<?php
$active = 'agencies';
$pageTitle = 'Agencies — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$agencies = [
  ['AH','Awan Homes','Lahore · DHA & Askari','4.9','128 deals · 14 agents'],
  ['CR','Clifton Realty','Karachi · Clifton & Defence','4.8','96 deals · 11 agents'],
  ['MV','Margalla Vista Agency','Islamabad · F & E sectors','4.9','74 deals · 9 agents'],
  ['BT','Bahria Trusted Brokers','Rawalpindi · Bahria Town','4.7','112 deals · 16 agents'],
  ['GL','Gulberg Living Co.','Lahore · Gulberg & Model Town','4.8','89 deals · 10 agents'],
  ['SK','Skyline Karachi','Karachi · Gulshan & PECHS','4.6','61 deals · 8 agents'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Agencies</span></div>
    <h1>Verified agencies, real response times</h1>
    <p class="lede">Agencies on Shalimar Properties keep live calendars, disclose commission up front and get scored on how quickly they reply to buyers.</p>
    <form class="filters" onsubmit="return false">
      <input type="search" placeholder="Search agency name" aria-label="Search agencies" style="min-width:200px;flex:1">
      <select aria-label="City"><option>All cities</option><option>Lahore</option><option>Karachi</option><option>Islamabad</option><option>Rawalpindi</option></select>
      <select aria-label="Specialty"><option>Specialty</option><option>Residential sale</option><option>Rentals</option><option>Commercial</option><option>Plots</option></select>
      <button class="btn btn-solid" type="button">Find agencies</button>
    </form>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="agency-grid">
      <?php foreach ($agencies as $i => $a): ?>
      <article class="agency rv" style="transition-delay:<?= ($i % 2) * 70 ?>ms">
        <div class="logo"><?= $a[0] ?></div>
        <div>
          <h3><?= htmlspecialchars($a[1]) ?></h3>
          <p class="meta"><i class="fa-solid fa-location-dot" style="color:var(--green)"></i> <?= htmlspecialchars($a[2]) ?></p>
          <p class="stars">★ <?= $a[3] ?> <span style="color:var(--muted)">· <?= $a[4] ?></span></p>
          <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
            <a href="#" class="btn btn-solid" style="padding:10px 16px;font-size:14px">View listings</a>
            <a href="#" class="btn btn-ghost" style="padding:10px 16px;font-size:14px">Contact</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="wrap soft-band rv">
    <div class="two-col">
      <div>
        <h2 style="margin:0;font-size:clamp(26px,3.2vw,40px)">Run an agency? Join the network</h2>
        <p style="color:var(--muted);margin:14px 0 0;max-width:42ch">Get verified badges, lead routing and photography support. Listing stays free until the property sells.</p>
      </div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <a href="index.php#list" class="btn btn-solid">Apply as agency</a>
        <a href="blog.php" class="btn btn-ghost">Read partner guide</a>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
