<?php
$active = 'projects';
$pageTitle = 'New Projects — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$IMG = fn($u) => "https://images.unsplash.com/{$u}?auto=format&fit=crop&w=1200&q=80";
$projects = [
  ['Shalimar Greens Phase II','Lahore · Raiwind Road','Gated community with parks, schools and retail strip. Booking open for 5 & 10 marla plots.',78,'photo-1600585154340-be6161a56a0c','From PKR 1.2 cr','Possession 2027'],
  ['Clifton Heights Residences','Karachi · Clifton','Sea-facing apartments with private club and three parking levels. Limited penthouses left.',62,'photo-1545324418-cc1a3fa10c00','From PKR 3.8 cr','Handover Q4 2026'],
  ['Margalla Vista Villas','Islamabad · Zone V','Low-density villas with Margalla views and shared equestrian paddock.',45,'photo-1600596542815-ffad4c1539a9','From PKR 8.5 cr','Booking open'],
  ['Canal Business Hub','Lahore · Canal Road','Grade-A office floors with shared lobby, generator and fibre backbone.',91,'photo-1497366216548-37526070297c','From PKR 45,000 / sq ft','Ready shell'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Projects</span></div>
    <h1>New projects, thoroughly vetted</h1>
    <p class="lede">Developer track record, NOCs and payment plans reviewed before a project appears here. Progress percentages update from site visits — not press releases.</p>
    <div class="filters" style="margin-top:28px">
      <span class="pill">All cities</span>
      <span class="pill muted">Residential</span>
      <span class="pill muted">Commercial</span>
      <span class="pill muted">Plots</span>
      <span class="pill muted">Ready soon</span>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="project-list">
      <?php foreach ($projects as $i => $p): ?>
      <article class="project rv" style="transition-delay:<?= $i * 80 ?>ms">
        <div class="media">
          <img src="<?= $IMG($p[4]) ?>" alt="<?= htmlspecialchars($p[0]) ?>" loading="lazy">
        </div>
        <div class="info">
          <span class="pill"><?= htmlspecialchars($p[6]) ?></span>
          <h3><?= htmlspecialchars($p[0]) ?></h3>
          <p class="addr" style="margin:0 0 10px"><i class="fa-solid fa-location-dot" style="color:var(--green)"></i> <?= htmlspecialchars($p[1]) ?></p>
          <p><?= htmlspecialchars($p[2]) ?></p>
          <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:var(--muted)">
            <span>Construction progress</span>
            <strong style="color:var(--ink)"><?= $p[3] ?>%</strong>
          </div>
          <div class="progress"><i style="width:<?= $p[3] ?>%"></i></div>
          <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:22px">
            <span class="price" style="font-size:22px"><?= $p[5] ?></span>
            <a href="#" class="btn btn-solid">View project</a>
            <a href="#" class="btn btn-ghost">Payment plan</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="cta-box rv">
      <div>
        <h2>Investor briefing, on request</h2>
        <p>Get a side-by-side of three projects matching your ticket size, city and risk appetite.</p>
      </div>
      <div>
        <div class="cta-form">
          <input type="email" placeholder="you@email.com" aria-label="Your email">
          <button class="btn" type="button">Request brief</button>
        </div>
        <p class="note">Sent as a PDF within two working days.</p>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
