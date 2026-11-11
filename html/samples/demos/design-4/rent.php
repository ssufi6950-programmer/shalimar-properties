<?php
$active = 'rent';
$pageTitle = 'Homes for Rent — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$q = [
  'q' => get_str('q'), 'project' => get_str('project'), 'type' => get_str('type'),
  'beds' => (int)get_str('beds'), 'verified' => get_str('verified') !== '', 'available' => get_str('available') !== '',
];
if (!in_array($q['type'], CATEGORIES, true)) $q['type'] = '';
[$q['min'], $q['max']] = budget_range(get_str('budget'));
$rows = filter_listings(['rent'], $q);
$allRent = filter_listings(['rent'], []);
$availableCount = count(array_filter($allRent, fn($l) => $l['deal'] === 'available'));
$rentedCount = count($allRent) - $availableCount;
$tabs = ['' => ['All', 'fa-border-all'], 'house' => ['Houses & portions', 'fa-house-user'], 'apartment' => ['Flats', 'fa-building'], 'office' => ['Offices', 'fa-briefcase']];
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Rent</span></div>
    <h1>Homes for rent</h1>
    <p class="lede">Houses, portions and flats with clear rent and contact details. When an agreement is signed, the listing is marked “Rented” automatically — no calls for homes already taken.</p>

    <form class="find-box" method="get" action="rent.php">
      <input type="hidden" name="type" id="rentType" value="<?= e($q['type']) ?>">
      <div class="find-tabs" data-target="rentType" data-autosubmit="1">
        <?php foreach ($tabs as $k => $t): ?>
          <button type="button" class="find-tab<?= $q['type'] === $k ? ' on' : '' ?>" data-value="<?= e($k) ?>"><i class="fa-solid <?= $t[1] ?>"></i> <?= e($t[0]) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="find-grid">
        <div class="find-field">
          <label for="rent-loc"><i class="fa-solid fa-location-dot"></i> Area / society</label>
          <input id="rent-loc" name="q" type="search" value="<?= e($q['q']) ?>" placeholder="Gulberg City, Cantt Villas, Chatha Town…">
        </div>
        <div class="find-field">
          <label for="rent-proj"><i class="fa-solid fa-city"></i> Project</label>
          <select id="rent-proj" name="project"><option value="">Any (incl. outside projects)</option>
            <?php foreach (projects() as $p): ?><option value="<?= e($p['slug']) ?>"<?= $q['project'] === $p['slug'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="find-field">
          <label for="rent-price"><i class="fa-solid fa-wallet"></i> Monthly rent</label>
          <select id="rent-price" name="budget">
            <?php foreach (['' => 'Any rent', 'r-u40' => 'Under 40,000', 'r-40-80' => '40,000 – 80,000', 'r-80+' => '80,000 +'] as $k => $v): ?>
              <option value="<?= $k ?>"<?= get_str('budget') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="find-field">
          <label for="rent-beds"><i class="fa-solid fa-bed"></i> Bedrooms</label>
          <select id="rent-beds" name="beds">
            <?php foreach ([0 => 'Any', 2 => '2+', 3 => '3+', 4 => '4+'] as $k => $v): ?><option value="<?= $k ?>"<?= $q['beds'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-solid" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Find rentals</button>
      </div>
      <div class="find-extra">
        <label class="find-chip"><input type="checkbox" name="available" value="1"<?= $q['available'] ? ' checked' : '' ?> onchange="this.form.submit()"><i class="fa-solid fa-bolt"></i> Available now</label>
        <label class="find-chip"><input type="checkbox" name="verified" value="1"<?= $q['verified'] ? ' checked' : '' ?> onchange="this.form.submit()"><i class="fa-solid fa-circle-check"></i> Verified only</label>
        <a class="find-chip" href="rent.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <div class="band rv" style="margin-bottom:22px">
      <div class="band-grid">
        <div><div class="stat-num" data-to="<?= $availableCount ?>">0</div><p>Rentals available now</p></div>
        <div><div class="stat-num" data-to="<?= $rentedCount ?>">0</div><p>Recently rented out</p></div>
        <div><div class="stat-num" data-to="<?= count(array_filter($allRent, fn($l) => listing_badge($l) !== '')) ?>">0</div><p>From verified owners / agencies</p></div>
        <div><div class="stat-num" data-to="24" data-suffix=" hrs">0</div><p>Typical admin review time</p></div>
      </div>
    </div>
    <div class="result-bar">
      <div><h2><?= count($rows) ?> rental<?= count($rows) === 1 ? '' : 's' ?></h2><p>Monthly rent shown. Advance and security as agreed with the owner.</p></div>
      <a href="post-property.php?purpose=rent" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i> Rent out your property</a>
    </div>
    <?php if ($rows): ?>
      <div class="listing-grid"><?php foreach ($rows as $i => $l) echo render_card($l, ($i % 3) * 60); ?></div>
    <?php else: ?>
      <div class="empty-note"><i class="fa-solid fa-house-circle-xmark"></i>No rentals match these filters. <a class="gold" href="rent.php">Clear filters</a>.</div>
    <?php endif; ?>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
