<?php
$active = 'buy';
$pageTitle = 'Buy Homes — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$IMG = fn($u) => "https://images.unsplash.com/{$u}?auto=format&fit=crop&w=1000&q=80";
$listings = [
  ['PKR 4.25 cr','10 marla','Modern house with rooftop terrace','Askari 11, Lahore',4,4,'2,250 sq ft','photo-1512917774080-9991f1c4c750'],
  ['PKR 9.4 cr','1 kanal','Kanal corner house with walled garden','DHA Phase 6, Lahore',5,6,'4,200 sq ft','photo-1613977257363-707ba9348227'],
  ['PKR 6.1 cr','2,850 sq ft','Sea-facing apartment, 14th floor','Clifton Block 4, Karachi',3,3,'2,850 sq ft','photo-1545324418-cc1a3fa10c00'],
  ['PKR 14.8 cr','600 sq yd','Margalla-view residence with studio','F-7, Islamabad',4,5,'5,100 sq ft','photo-1600585154340-be6161a56a0c'],
  ['PKR 11.9 cr','2 kanal','Farmhouse with orchard & guest annexe','Bedian Road, Lahore',6,7,'4,800 sq ft','photo-1600607687939-ce8a6c25118c'],
  ['PKR 3.1 cr','5 marla','Newly built townhouse, corner unit','Bahria Town, Rawalpindi',3,4,'1,650 sq ft','photo-1568605114967-8130f3a36994'],
  ['PKR 7.6 cr','1 kanal','Double-height living, park facing','DHA Phase 8, Lahore',5,5,'3,100 sq ft','photo-1600596542815-ffad4c1539a9'],
  ['PKR 2.35 cr','5 marla','Finished townhouse, ready to move','Bahria Orchard, Lahore',3,4,'1,720 sq ft','photo-1570129477492-45c003edd2be'],
  ['PKR 5.4 cr','12 marla','Renovated family home near park','Gulberg III, Lahore',4,4,'2,680 sq ft','photo-1600047509807-ba8f99d2cd00'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Buy</span></div>
    <h1>Homes ready for transfer</h1>
    <p class="lede">Every sale listing is visited, measured and photographed before it goes live. Filter by city, budget and size — prices shown are what sellers actually accept.</p>

    <form class="find-box find-buy" id="buyFind" onsubmit="return false">
      <div class="find-tabs" role="tablist">
        <button type="button" class="find-tab on" data-mode="house"><i class="fa-solid fa-house"></i> Houses</button>
        <button type="button" class="find-tab" data-mode="apt"><i class="fa-solid fa-building"></i> Apartments</button>
        <button type="button" class="find-tab" data-mode="plot"><i class="fa-solid fa-map"></i> Plots</button>
        <button type="button" class="find-tab" data-mode="farm"><i class="fa-solid fa-tree"></i> Farmhouses</button>
      </div>
      <div class="find-grid">
        <div class="find-field">
          <label for="buy-loc"><i class="fa-solid fa-location-dot"></i> Location</label>
          <input id="buy-loc" type="search" placeholder="DHA Phase 6, Gulberg, Clifton…">
        </div>
        <div class="find-field">
          <label for="buy-city"><i class="fa-solid fa-city"></i> City</label>
          <select id="buy-city"><option>All cities</option><option>Lahore</option><option>Karachi</option><option>Islamabad</option><option>Rawalpindi</option><option>Faisalabad</option></select>
        </div>
        <div class="find-field">
          <label for="buy-budget"><i class="fa-solid fa-coins"></i> Budget</label>
          <select id="buy-budget"><option>Any budget</option><option>Under 3 crore</option><option>3 – 7 crore</option><option>7 – 15 crore</option><option>15 crore+</option></select>
        </div>
        <div class="find-field">
          <label for="buy-beds"><i class="fa-solid fa-bed"></i> Bedrooms</label>
          <select id="buy-beds"><option>Any</option><option>2+</option><option>3+</option><option>4+</option><option>5+</option></select>
        </div>
        <button class="btn btn-solid" type="button"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
      </div>
      <div class="find-extra">
        <button type="button" class="find-chip on"><i class="fa-solid fa-circle-check"></i> Verified only</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-camera"></i> With photos</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-file-circle-check"></i> Title cleared</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-maximize"></i> Corner unit</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-tree"></i> Park facing</button>
      </div>
    </form>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2>9,840 homes for sale</h2>
        <p>Sorted by recently verified. Tap a card to open the full file.</p>
      </div>
      <div class="tag-row">
        <span class="pill">Verified photos</span>
        <span class="pill muted">Title checked</span>
      </div>
    </div>
    <div class="listing-grid">
      <?php foreach ($listings as $i => $l): ?>
      <article class="card rv" style="transition-delay:<?= ($i % 3) * 60 ?>ms">
        <div class="card-art">
          <img src="<?= $IMG($l[7]) ?>" alt="<?= htmlspecialchars($l[2]) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
          <span class="chip">For sale</span>
        </div>
        <div class="card-body">
          <p class="price"><?= $l[0] ?> <b><?= $l[1] ?></b></p>
          <h4><?= htmlspecialchars($l[2]) ?></h4>
          <p class="addr"><i class="fa-solid fa-location-dot" style="color:var(--green)"></i> <?= htmlspecialchars($l[3]) ?></p>
          <div class="feats">
            <span><i class="fa-solid fa-bed" style="color:var(--green)"></i> <?= $l[4] ?> bed</span>
            <span><i class="fa-solid fa-bath" style="color:var(--green)"></i> <?= $l[5] ?> bath</span>
            <span><i class="fa-solid fa-ruler-combined" style="color:var(--green)"></i> <?= $l[6] ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section style="padding-top:20px">
  <div class="wrap">
    <div class="soft-band rv two-col">
      <div>
        <span class="eyebrow"><b></b>Buyer protection</span>
        <h2 style="margin:14px 0 0;font-size:clamp(26px,3.2vw,40px)">We clear the file before you pay</h2>
        <p style="color:var(--muted);margin:14px 0 0;max-width:42ch">Title, NOC and society dues are verified by our legal desk. You walk into transfer day with a cleared checklist.</p>
      </div>
      <div class="panel" style="background:var(--paper-2)">
        <ol class="steps" style="margin:0">
          <li><div><h4>Shortlist online</h4><p>Floor plans and walkthroughs on every listing.</p></div></li>
          <li><div><h4>Book a viewing</h4><p>Pick from the agent’s live calendar.</p></div></li>
          <li><div><h4>Legal desk clears</h4><p>Average file cleared in 6 working days.</p></div></li>
        </ol>
      </div>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="cta-box rv">
      <div>
        <h2>Need help shortlisting?</h2>
        <p>Tell us your city and budget. A buyer advisor will send three matching homes within a day.</p>
      </div>
      <div>
        <div class="cta-form">
          <input type="email" placeholder="you@email.com" aria-label="Your email">
          <button class="btn" type="button">Get matches</button>
        </div>
        <p class="note">Free. No obligation to buy.</p>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
