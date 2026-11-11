<?php
$active = 'blog';
$pageTitle = 'Blog & Guides — Shalimar Properties';
require __DIR__ . '/includes/header.php';

$cat = get_str('cat');
/* Article titles from the Jaageer.com blog; "Read" opens the original article. */
$posts = [
  ['Investing', 'From plot to profit: the smart investor’s guide', 'How to evaluate location, payment plans and development progress before you book a plot.', '8 min read', 'assets/images/listing-plot.svg', 'https://www.jaageer.com/blog/from-plot-to-profit-the-smart-investors-guide-blog-336.html'],
  ['Commercial', 'Choosing the perfect commercial location', 'Footfall, frontage and access — a practical checklist for shops and offices.', '7 min read', 'assets/images/listing-commercial.svg', 'https://www.jaageer.com/blog/choosing-the-perfect-commercial-location-a-practical-gu-blog-337.html'],
  ['Guides', 'The complete property buyer’s checklist', 'Documents, dues and site checks you should never skip before paying token money.', '9 min read', 'assets/images/listing-house.svg', 'https://www.jaageer.com/blog/the-complete-property-buyers-checklist-blog-334.html'],
  ['Investing', 'How to spot a high-ROI property before everyone else', 'Signals in infrastructure, approvals and transaction volume that point to growth.', '6 min read', 'assets/images/projects/smart-city.svg', 'https://www.jaageer.com/blog/how-to-spot-a-high-roi-property-before-everyone-else-blog-333.html'],
  ['Commercial', 'Why commercial properties create long-term wealth', 'Rental yield, appreciation and how premium towers change the equation.', '7 min read', 'assets/images/projects/zalmi-x.svg', 'https://www.jaageer.com/blog/why-commercial-properties-create-long-term-wealth-blog-331.html'],
  ['Guides', 'Your dream property starts with the right decision', 'Matching budget, family needs and community amenities without regret.', '5 min read', 'assets/images/projects/gulberg-city.svg', 'https://www.jaageer.com/blog/your-dream-property-starts-with-the-right-decision-blog-332.html'],
];
$cats = array_values(array_unique(array_column($posts, 0)));
if ($cat !== '') $posts = array_values(array_filter($posts, fn($p) => $p[0] === $cat));
?>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Blog</span></div>
    <h1>Guides &amp; market notes</h1>
    <p class="lede">Practical reading for buyers, owners and investors — plus live numbers in our <a href="insights.php" class="gold">market insights</a>.</p>
    <div class="tag-row chips-scroll" style="margin-top:14px">
      <a href="blog.php" class="pill<?= $cat === '' ? '' : ' muted' ?>">All</a>
      <?php foreach ($cats as $c): ?><a href="blog.php?cat=<?= urlencode($c) ?>" class="pill<?= $cat === $c ? '' : ' muted' ?>"><?= e($c) ?></a><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight" style="padding-top:8px">
  <div class="wrap">
    <div class="blog-grid">
      <?php foreach ($posts as $i => $p): ?>
      <article class="blog-card rv" style="--d:<?= ($i % 3) * 60 ?>ms;position:relative">
        <div class="thumb"><img src="<?= e($p[4]) ?>" alt="" loading="lazy"></div>
        <div class="body">
          <div class="meta"><span class="pill"><?= e($p[0]) ?></span> <?= e($p[3]) ?></div>
          <h3><a class="stretched" href="<?= e($p[5]) ?>" target="_blank" rel="noopener"><?= e($p[1]) ?></a></h3>
          <p><?= e($p[2]) ?></p>
          <span class="link-more" style="margin-top:10px">Read article <i class="fa-solid fa-arrow-up-right-from-square"></i></span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec-tight">
  <div class="wrap">
    <div class="cta-box rv rv-z">
      <div><h2>Prefer numbers to opinions?</h2><p>Daily and monthly transactions, most-traded sizes and block-wise prices — updated as deals are recorded.</p></div>
      <div class="cta-actions"><a href="insights.php" class="btn btn-light"><i class="fa-solid fa-chart-line"></i> View market insights</a></div>
    </div>
  </div>
</section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
