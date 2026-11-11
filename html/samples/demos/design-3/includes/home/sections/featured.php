<section class="sec-tight" id="featured">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker"><?= e($T('kicker', 'Fresh & verified')) ?></span><h2 class="display-sm"><?= $T('title_h', 'Latest listings') ?></h2>
        <p><?= e($T('lede', 'Posted by Shalimar customers and authorized realtors. Third-party ads go live only after review.')) ?></p></div>
      <div class="arrows">
        <button class="rq" data-rail-prev aria-label="Scroll left"><i class="fa-solid fa-arrow-left-long"></i></button>
        <button class="rq" data-rail-next aria-label="Scroll right"><i class="fa-solid fa-arrow-right-long"></i></button>
      </div>
    </div>
    <div class="rail">
      <?php foreach ($featured as $i => $l) echo render_card($l, ($i % 4) * 80); ?>
    </div>
    <a href="buy.php" class="link-more">View all listings <i class="fa-solid fa-arrow-right-long"></i></a>
  </div>
</section>
