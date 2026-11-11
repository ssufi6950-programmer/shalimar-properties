<?php
$active = 'events';
$pageTitle = 'Events — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$events = [
  ['18','OCT','Open house · DHA Phase 6','Sat 11:00–14:00 · Lahore','Walk three kanal homes with our photography and measurement team on site. RSVP required.','fa-house'],
  ['22','OCT','Investor evening · Canal Road','Wed 18:30 · Lahore','Developer Q&A on two new commercial floors and payment schedules.','fa-chart-line'],
  ['02','NOV','First-time buyer workshop','Sun 15:00 · Online','Stamp duty, token money and how to use the registry calculator before you bid.','fa-chalkboard-user'],
  ['09','NOV','Karachi waterfront tour','Sun 10:00–13:00 · Clifton','Guided visits of three sea-facing apartments ready for transfer.','fa-water'],
  ['16','NOV','Agency partner meetup','Sat 16:00 · Islamabad','Networking for verified agencies — lead routing and photography slots.','fa-handshake'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Events</span></div>
    <h1>Open houses & market sessions</h1>
    <p class="lede">In-person tours, investor briefings and free workshops. Seats are limited so every visit stays useful — not a crowd in a lobby.</p>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap" style="display:grid;grid-template-columns:1.4fr .8fr;gap:40px;align-items:start">
    <div class="event-timeline">
      <?php foreach ($events as $i => $e): ?>
      <div class="event-item rv" style="transition-delay:<?= $i * 60 ?>ms">
        <div class="event-dot"><i class="fa-solid <?= $e[5] ?>"></i></div>
        <article class="event-card">
          <div class="when"><?= $e[0] ?> <?= $e[1] ?> · <?= htmlspecialchars($e[3]) ?></div>
          <h3><?= htmlspecialchars($e[2]) ?></h3>
          <p><?= htmlspecialchars($e[4]) ?></p>
          <a href="#" class="btn btn-solid" style="margin-top:16px;padding:10px 18px;font-size:14px">Reserve seat</a>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <aside class="panel rv" style="position:sticky;top:100px">
      <h3 style="margin:0 0 8px;font-size:22px">Host an open house</h3>
      <p style="color:var(--muted);margin:0 0 18px;font-size:14.5px">Sellers and agencies can book a weekend slot. We handle photography and visitor check-in.</p>
      <a href="index.php#list" class="btn btn-solid" style="width:100%;justify-content:center">Request a slot</a>
      <hr style="border:0;border-top:1px solid var(--line);margin:22px 0">
      <h4 style="margin:0 0 10px;font-size:16px">What to bring</h4>
      <ul style="margin:0;padding-left:18px;color:var(--muted);font-size:14.5px;line-height:1.7">
        <li>CNIC copy</li>
        <li>Budget range</li>
        <li>Preferred possession window</li>
      </ul>
    </aside>
  </div>
</section>
<style>
@media(max-width:900px){
  main > section:nth-of-type(2) > .wrap{grid-template-columns:1fr !important}
  aside.panel{position:static !important}
}
</style>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
