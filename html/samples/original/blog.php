<?php
$active = 'blog';
$pageTitle = 'Blog — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$IMG = fn($u) => "https://images.unsplash.com/{$u}?auto=format&fit=crop&w=1000&q=80";
$posts = [
  ['Guides','How to read a society transfer file','A plain-language walkthrough of NOCs, dues and the documents you should refuse to skip.','12 min read · Sep 2026','photo-1450101499163-c8848c66ca85'],
  ['Market','Lahore DHA: what 10 marla actually costs now','Median asking vs closing prices from the last quarter, phase by phase.','8 min read · Sep 2026','photo-1560518883-ce09059eeffa'],
  ['Rentals','Deposit myths landlords still push','What verified rental ads on our platform can and cannot ask for.','6 min read · Aug 2026','photo-1502672260266-1c1ef2d93688'],
  ['Projects','Payment plans that won’t trap you','Red flags in instalment schedules before you book a new project.','10 min read · Aug 2026','photo-1486406146926-c627a92ad1ab'],
  ['Legal','Stamp duty basics for first-time buyers','Province-wise ranges and when registry calculator numbers diverge from the counter.','9 min read · Jul 2026','photo-1589829545856-d10d557cf95f'],
  ['Lifestyle','Staging that photographs well','Simple prep that helps our shoot team and your listing views.','5 min read · Jul 2026','photo-1600210492486-724fe5c67fb0'],
];
?>

<main>
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Blog</span></div>
    <h1>Notes from the field</h1>
    <p class="lede">Market numbers, legal checklists and buying tips written by people who visit the homes — not recycled press copy.</p>
    <div class="tag-row" style="margin-top:24px">
      <span class="pill">All</span>
      <span class="pill muted">Guides</span>
      <span class="pill muted">Market</span>
      <span class="pill muted">Legal</span>
      <span class="pill muted">Projects</span>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="blog-grid">
      <?php foreach ($posts as $i => $p): ?>
      <article class="blog-card rv" style="transition-delay:<?= ($i % 3) * 70 ?>ms">
        <div class="thumb">
          <img src="<?= $IMG($p[4]) ?>" alt="<?= htmlspecialchars($p[1]) ?>" loading="lazy">
        </div>
        <div class="body">
          <div class="meta"><span class="pill" style="padding:4px 10px"><?= $p[0] ?></span> · <?= $p[3] ?></div>
          <h3><?= htmlspecialchars($p[1]) ?></h3>
          <p><?= htmlspecialchars($p[2]) ?></p>
          <a href="#" style="display:inline-block;margin-top:14px;color:var(--green);font-weight:600;font-size:14.5px">Read article →</a>
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
        <h2>Weekly market brief</h2>
        <p>One email every Monday: verified price moves and new projects worth a look.</p>
      </div>
      <div>
        <div class="cta-form">
          <input type="email" placeholder="you@email.com" aria-label="Your email">
          <button class="btn" type="button">Subscribe</button>
        </div>
        <p class="note">Unsubscribe any time. No spam.</p>
      </div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
