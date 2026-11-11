<footer>
  <div class="wrap">
    <div class="f-grid">
      <div>
        <a href="index.php" class="f-logo" aria-label="Shalimar Properties — home"><img src="assets/images/logos/sp-logo-white.svg?v=7" alt="Shalimar Properties" width="222" height="54"></a>
        <p>The official property portal of Shalimar Group — verified plots, homes, rentals and project units, posted only by owners and authorized realtors.</p>
        <div class="f-proj">
          <?php foreach (projects() as $fp): ?>
            <a href="projects.php#<?= e($fp['slug']) ?>" title="<?= e($fp['name']) ?>"><?php if (!empty($fp['logo'])): ?><img src="<?= e(img_url($fp['logo'])) ?>" alt="<?= e($fp['name']) ?>"><?php else: ?><span class="plogo-mono" style="width:26px;height:26px;font-size:11px"><?= e(project_initials($fp['name'])) ?></span><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
        <div class="f-subp"><?php foreach (projects() as $fp): if (empty($fp['tree'])) continue; ?><div><b><?= e($fp['name']) ?>:</b> <?php $fi = 0; foreach ($fp['tree'] as $fc => $fcd): ?><?= $fi++ ? ' · ' : '' ?><a href="<?= e(sub_link($fp['slug'], (string)$fc)) ?>"><?= e($fcd['label']) ?></a><?php endforeach; ?></div><?php endforeach; ?></div>
        <div class="socials">
          <a href="tel:<?= e(SITE_TEL) ?>" aria-label="Call us"><i class="fa-solid fa-phone"></i></a>
          <a href="mailto:<?= e(SITE_EMAIL) ?>" aria-label="Email us"><i class="fa-solid fa-envelope"></i></a>
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
        <a href="<?= e(\App\Libraries\PortalLinks::portal('customer/login')) ?>">Customer Portal</a>
        <a href="<?= e(\App\Libraries\PortalLinks::portal('agency/login')) ?>">Agency Portal</a>
      </div>
      <div class="f-col">
        <h5>Contact</h5>
        <?php foreach (SITE_PHONES as $ph): ?><a href="tel:<?= e($ph[1]) ?>"><i class="fa-solid fa-phone"></i> <?= e($ph[0]) ?></a><?php endforeach; ?>
        <a href="mailto:<?= e(SITE_EMAIL) ?>"><i class="fa-solid fa-envelope"></i> <?= e(SITE_EMAIL) ?></a>
        <span style="display:block;font-size:12.5px;color:rgba(221,226,222,.6);margin-top:6px"><i class="fa-solid fa-location-dot"></i> Head Office: <?= e(SITE_ADDRESS) ?></span>
      </div>
    </div>
    <div class="f-bot">
      <span>© <?= date('Y') ?> Shalimar Properties · All rights reserved. </span>
      <span><a href="insights.php">Market report</a> · <a href="post-property.php">List with us</a></span>
    </div>
  </div>
</footer>

<button class="up" id="up" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>
<script src="assets/js/site.js?v=4"></script>
