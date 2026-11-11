<section class="sec-tight" id="projects">
  <div class="wrap">
    <div class="c-head rv"><?= $T('title_h', '<h2>Communities</h2>') ?><p><?= e($T('lede', 'Hover — or tap — a project to open it.')) ?></p></div>
    <div class="c-acc rv" id="cAcc">
      <?php foreach ($projects as $i => $p): $m = project_media($p); ?>
      <article class="c-panel<?= $i ? '' : ' on' ?>" tabindex="0">
        <img src="<?= e($m['cover_lg']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <span class="c-vt"><em><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></em><?= e($p['name']) ?></span>
        <div class="c-pbody">
          <span class="c-plogo"><?= project_logo_html($p, 'plogo') ?></span>
          <h3><?= e($p['name']) ?></h3>
          <p><?= e($m['tagline']) ?> — <?= e($p['location']) ?></p>
          <div class="c-pk"><span><b><?= (int)($liveByProject[$p['slug']] ?? 0) ?></b>live</span><span><b><?= (int)($byProject[$p['slug']] ?? 0) ?></b>deals / mo</span><span><b><?= (int)($p['progress'] ?? 0) ?>%</b>developed</span></div>
          <a href="projects.php#<?= e($p['slug']) ?>" class="c-btn">Open project <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
