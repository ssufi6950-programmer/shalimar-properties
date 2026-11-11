<?php
$active = 'rent';
$pageTitle = 'Rent Homes — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$IMG = fn($u) => "https://images.unsplash.com/{$u}?auto=format&fit=crop&w=1000&q=80";
$listings = [
  ['PKR 185,000','per month','Furnished 2-bed with parking','Gulberg III, Lahore',2,2,'1,400 sq ft','photo-1502672260266-1c1ef2d93688'],
  ['PKR 420,000','per month','Open-plan office floor, 40 desks','Blue Area, Islamabad',0,3,'3,600 sq ft','photo-1497366216548-37526070297c'],
  ['PKR 95,000','per month','Bright studio near MM Alam','Gulberg II, Lahore',1,1,'650 sq ft','photo-1522708323590-d24dbb6b0267'],
  ['PKR 275,000','per month','3-bed flat with balcony views','DHA Phase 5, Karachi',3,3,'1,850 sq ft','photo-1560448204-e02f11c3d0e2'],
  ['PKR 150,000','per month','Family house with lawn','Bahria Town, Rawalpindi',4,4,'2,100 sq ft','photo-1600585154526-990dced4db0d'],
  ['PKR 210,000','per month','Serviced apartment, short-term OK','F-10, Islamabad',2,2,'1,100 sq ft','photo-1493809842364-78817add7ffb'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Rent</span></div>
    <h1>Places you can move into this month</h1>
    <p class="lede">Rentals with clear possession dates, deposit terms and landlord contacts. No bait listings — every unit is checked before it appears here.</p>

    <form class="find-box find-rent" id="rentFind" onsubmit="return false">
      <div class="find-tabs" role="tablist">
        <button type="button" class="find-tab on" data-mode="home"><i class="fa-solid fa-house-user"></i> Homes</button>
        <button type="button" class="find-tab" data-mode="apt"><i class="fa-solid fa-building"></i> Apartments</button>
        <button type="button" class="find-tab" data-mode="office"><i class="fa-solid fa-briefcase"></i> Offices</button>
        <button type="button" class="find-tab" data-mode="short"><i class="fa-solid fa-moon"></i> Short stay</button>
      </div>
      <div class="find-grid">
        <div class="find-field">
          <label for="rent-loc"><i class="fa-solid fa-location-dot"></i> Area / society</label>
          <input id="rent-loc" type="search" placeholder="Gulberg, Blue Area, Bahria…">
        </div>
        <div class="find-field">
          <label for="rent-city"><i class="fa-solid fa-city"></i> City</label>
          <select id="rent-city"><option>All cities</option><option>Lahore</option><option>Karachi</option><option>Islamabad</option><option>Rawalpindi</option></select>
        </div>
        <div class="find-field">
          <label for="rent-price"><i class="fa-solid fa-wallet"></i> Monthly rent</label>
          <select id="rent-price"><option>Any rent</option><option>Under 100,000</option><option>100k – 200k</option><option>200k – 400k</option><option>400k+</option></select>
        </div>
        <div class="find-field">
          <label for="rent-furnish"><i class="fa-solid fa-couch"></i> Furnishing</label>
          <select id="rent-furnish"><option>Any</option><option>Furnished</option><option>Semi-furnished</option><option>Unfurnished</option></select>
        </div>
        <button class="btn btn-solid" type="button"><i class="fa-solid fa-magnifying-glass"></i> Find rentals</button>
      </div>
      <div class="find-extra">
        <button type="button" class="find-chip on"><i class="fa-solid fa-bolt"></i> Available now</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-car"></i> Parking</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-paw"></i> Pets OK</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-shield-halved"></i> 1-month deposit</button>
        <button type="button" class="find-chip"><i class="fa-solid fa-wifi"></i> Utilities included</button>
      </div>
    </form>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="band rv" style="margin-bottom:48px">
      <div class="band-grid">
        <div><div class="stat-num" data-to="4120">0</div><p>Active rental listings</p></div>
        <div><div class="stat-num" data-to="48" data-suffix=" hrs">0</div><p>Avg. response from landlords</p></div>
        <div><div class="stat-num" data-to="92" data-suffix="%">0</div><p>Move-in within 30 days</p></div>
        <div><div class="stat-num" data-to="1">0</div><p>Month deposit capped on verified ads</p></div>
      </div>
    </div>

    <div class="sec-head rv">
      <div>
        <h2>Available now</h2>
        <p>Monthly rent shown. Security deposit and advance are listed on each detail page.</p>
      </div>
    </div>
    <div class="listing-grid">
      <?php foreach ($listings as $i => $l): ?>
      <article class="card rv" style="transition-delay:<?= ($i % 3) * 60 ?>ms">
        <div class="card-art">
          <img src="<?= $IMG($l[7]) ?>" alt="<?= htmlspecialchars($l[2]) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
          <span class="chip rent">For rent</span>
        </div>
        <div class="card-body">
          <p class="price"><?= $l[0] ?> <b><?= $l[1] ?></b></p>
          <h4><?= htmlspecialchars($l[2]) ?></h4>
          <p class="addr"><i class="fa-solid fa-location-dot" style="color:var(--green)"></i> <?= htmlspecialchars($l[3]) ?></p>
          <div class="feats">
            <?php if ($l[4]): ?><span><i class="fa-solid fa-bed" style="color:var(--green)"></i> <?= $l[4] ?> bed</span><?php endif; ?>
            <span><i class="fa-solid fa-bath" style="color:var(--green)"></i> <?= $l[5] ?> bath</span>
            <span><i class="fa-solid fa-ruler-combined" style="color:var(--green)"></i> <?= $l[6] ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="wrap two-col">
    <div class="rv">
      <h2 style="font-size:clamp(28px,3.4vw,42px);margin:0">Lease terms, written clearly</h2>
      <p style="color:var(--muted);margin:16px 0 0">Every rental includes deposit amount, notice period and whether pets or short-term stay are allowed — before you book a visit.</p>
      <ul class="steps">
        <li><div><h4>Transparent deposits</h4><p>Verified ads cap security at one month unless noted.</p></div></li>
        <li><div><h4>Possession date locked</h4><p>You know when keys are available before paying token.</p></div></li>
        <li><div><h4>Digital rent receipts</h4><p>Optional for managed properties on the platform.</p></div></li>
      </ul>
    </div>
    <div class="visual rv">
      <img src="<?= $IMG('photo-1600210492486-724fe5c67fb0') ?>" alt="Rental interior" style="width:100%;height:100%;object-fit:cover;display:block">
      <div class="float-card">
        <span class="ring"><i>30d</i></span>
        <div><strong>Typical move-in</strong><span>From shortlist to keys</span></div>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
