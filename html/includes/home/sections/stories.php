<section class="c-stories sw" id="stories" aria-label="Shalimar stories">
  <div class="wrap c-sgrid">
    <div class="c-sintro rv">
      <span class="c-tag light"><span class="dot"></span> Illustrative stories</span>
      <h2 class="sw-title">Explore example<br>property journeys.</h2>
      <p>Illustrative examples, not verified customer testimonials or completed ERP transactions. Tap a story to read it.</p>
      <div class="c-scount"><b><?= count($storyJs) ?></b><span>illustrative examples<br>separate from ERP reporting</span></div>
    </div>
    <div class="c-wall" aria-label="Stories">
      <?php for ($col = 0; $col < 3; $col++): $order = array_merge(array_slice(array_keys($storyJs), $col * 2), array_slice(array_keys($storyJs), 0, $col * 2)); ?>
      <div class="c-col<?= $col === 1 ? ' rev' : '' ?>"><div class="c-colin">
        <?php for ($rep = 0; $rep < 2; $rep++): foreach ($order as $k => $si): $s = $storyJs[$si]; $ph = ($k + $col) % 2 === 0; ?>
          <article class="c-story<?= $ph ? ' ph' : '' ?>" data-open="<?= $si ?>"<?= $rep ? ' aria-hidden="true"' : ' tabindex="0" role="button" aria-label="Read ' . e($s['name']) . '’s story"' ?>>
            <?php if ($ph): ?><img src="<?= e(str_replace('.webp', '-sm.webp', $s['img'])) ?>" alt="" loading="lazy"><?php endif; ?>
            <span class="c-sp"><?= e($s['project']) ?></span>
            <blockquote>“<?= e($s['quote']) ?>”</blockquote>
            <div class="c-sw"><span class="av"><?= e($s['ini']) ?></span><span><b><?= e($s['name']) ?></b><small><?= e($s['facts'][0]) ?> · <?= e($s['when']) ?></small></span></div>
          </article>
        <?php endforeach; endfor; ?>
      </div></div>
      <?php endfor; ?>
    </div>
  </div>
  <div class="sv" id="sv" role="dialog" aria-modal="true" aria-label="Story viewer" hidden>
    <button class="sv-x" id="svClose" aria-label="Close story"><i class="fa-solid fa-xmark"></i></button>
    <button class="sv-arrow l" id="svPrev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
    <div class="sv-frame" id="svFrame">
      <div class="sv-segs" id="svSegs"></div>
      <div class="sv-top"><span class="av" id="svAv"></span><span><b id="svName"></b><small id="svWhen"></small></span></div>
      <div class="sv-img" id="svImg"></div>
      <div class="sv-shade"></div>
      <div class="sv-content" id="svContent"></div>
      <button class="sv-tap l" id="svTapL" aria-label="Previous"></button><button class="sv-tap r" id="svTapR" aria-label="Next"></button>
    </div>
    <button class="sv-arrow r" id="svNext" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
  </div>
  <script>window.SP_STORIES = <?= json_encode($storyJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
</section>
