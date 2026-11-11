<footer>
  <div class="wrap">
    <div class="f-grid">
      <div>
        <a href="index.php" class="brand"><img src="assets/images/logos/shalimar-mark.svg" alt="" width="38" height="38"><span class="brand-text"><b>Shalimar</b><small>PROPERTIES</small></span></a>
        <p>The official property portal of Shalimar Group — verified plots, homes, rentals and project units, posted only by owners and authorized realtors.</p>
        <div class="f-proj">
          <?php foreach (projects() as $fp): ?>
            <a href="projects.php#<?= e($fp['slug']) ?>" title="<?= e($fp['name']) ?>"><?php if (!empty($fp['logo'])): ?><img src="<?= e(img_url($fp['logo'])) ?>" alt="<?= e($fp['name']) ?>"><?php else: ?><span class="plogo-mono" style="width:26px;height:26px;font-size:11px"><?= e(project_initials($fp['name'])) ?></span><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
        <div class="socials">
          <a href="https://wa.me/<?= e(SITE_WHATSAPP) ?>" aria-label="WhatsApp" rel="noopener" target="_blank"><i class="fa-brands fa-whatsapp"></i></a>
          <a href="tel:<?= e(digits(SITE_PHONE)) ?>" aria-label="Call us"><i class="fa-solid fa-phone"></i></a>
        </div>
      </div>
      <div class="f-col">
        <h5>Browse</h5>
        <a href="buy.php">Plots &amp; homes for sale</a>
        <a href="rent.php">Homes for rent</a>
        <a href="projects.php">Shalimar projects</a>
        <a href="insights.php">Market insights</a>
        <a href="wanted.php">Wanted ads</a>
      </div>
      <div class="f-col">
        <h5>Owners &amp; realtors</h5>
        <a href="post-property.php">Post a property</a>
        <a href="agencies.php">Authorized agencies</a>
        <a href="registry-calculator.php">Registry calculator</a>
        <a href="events.php">Events</a>
        <a href="admin.php">Admin sign in</a>
      </div>
      <div class="f-col">
        <h5>Contact</h5>
        <a href="tel:<?= e(digits(SITE_PHONE)) ?>"><i class="fa-solid fa-phone"></i> <?= e(SITE_PHONE) ?></a>
        <a href="https://wa.me/<?= e(SITE_WHATSAPP) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
        <a href="blog.php">Blog &amp; guides</a>
        <span style="display:block;font-size:12.5px;color:rgba(221,226,222,.5);margin-top:6px"><?= e(SITE_ADDRESS) ?></span>
      </div>
    </div>
    <div class="f-bot">
      <span>© <?= date('Y') ?> Shalimar Properties · Shalimar Group. Listings marked “sample” are demo data.</span>
      <span><a href="insights.php">Market report</a> · <a href="post-property.php">List with us</a></span>
    </div>
  </div>
</footer>

<button class="up" id="up" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>
<script src="assets/js/site.js?v=2"></script>
