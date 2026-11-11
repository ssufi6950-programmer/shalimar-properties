<?php
$active = 'events';
$pageTitle = 'Events — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$events = [
  ['18 OCT', 'Open house · Gulberg Villas', 'Sat 11:00–14:00 · Gulberg City, Sargodha', 'Walk through finished 3.5 and 5 marla villas with our sales team. Families welcome.', 'fa-house'],
  ['25 OCT', 'Plot balloting · Shalimar Smart City', 'Sat 15:00 · Shalimar Smart City, Mall Road', 'Live balloting for 5, 7 and 10 marla plots in Sector II. Booking holders please bring CNIC.', 'fa-ticket'],
  ['01 NOV', 'Investor evening · Zalmi X', 'Sat 18:30 · Pine Avenue Road, Lahore', 'Floor plans, payment schedule and rental outlook for Founder Offices and Flagship Outlets.', 'fa-chart-line'],
  ['15 NOV', 'Authorized realtors meetup', 'Sat 16:00 · BBT 02, Shalimar Smart City', 'Policy updates for authorized agencies — posting rights, records and lead routing.', 'fa-handshake'],
];
$wa = 'https://wa.me/' . SITE_WHATSAPP . '?text=';
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Events</span></div>
    <h1>Open houses, balloting &amp; briefings</h1>
    <p class="lede">Meet the Shalimar team on site. Seats are limited so every visit stays personal and useful.</p>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap events-layout">
    <div class="event-timeline">
      <?php foreach ($events as $i => $ev): ?>
      <div class="event-item rv" style="--d:<?= $i * 60 ?>ms">
        <div class="event-dot"><i class="fa-solid <?= $ev[4] ?>"></i></div>
        <article class="event-card">
          <div class="when"><?= e($ev[0]) ?> · <?= e($ev[2]) ?></div>
          <h3><?= e($ev[1]) ?></h3>
          <p><?= e($ev[3]) ?></p>
          <a href="<?= e($wa . rawurlencode('I would like to reserve a seat for: ' . $ev[1] . ' (' . $ev[0] . ')')) ?>" target="_blank" rel="noopener" class="btn btn-solid btn-sm" style="margin-top:10px"><i class="fa-brands fa-whatsapp"></i> Reserve seat</a>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <aside class="panel rv sticky-aside">
      <h3>Host an open house</h3>
      <p class="muted" style="margin:0 0 14px;font-size:13.5px">Verified owners and authorized agencies can request a weekend slot. We handle visitor check-in.</p>
      <a href="<?= e($wa . rawurlencode('I would like to host an open house.')) ?>" target="_blank" rel="noopener" class="btn btn-solid btn-block">Request a slot</a>
      <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
      <h4 style="margin:0 0 6px;font-size:14px">What to bring</h4>
      <ul style="margin:0;padding-left:18px;color:var(--muted);font-size:13.5px;line-height:1.8">
        <li>CNIC copy</li><li>Booking / file number (if any)</li><li>Budget range</li>
      </ul>
    </aside>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
