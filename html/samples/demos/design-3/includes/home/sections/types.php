<section class="sec-tight" id="types" style="padding-top:8px">
  <div class="wrap">
    <div class="h3-types">
      <?php foreach ($typeTiles as $i => $t): $c = $t[0] === 'office' ? $catCount('office') + $catCount('commercial') : $catCount($t[0]); ?>
      <a href="<?= e($t[3]) ?>" class="h3-type rv" style="--d:<?= $i * 70 ?>ms">
        <span class="ti"><i class="<?= $t[2] ?>"></i></span>
        <span class="tt"><b><?= e($t[1]) ?></b><small><?= e($t[4]) ?></small></span>
        <span class="tn"><b><?= $c ?></b><small>live</small></span>
        <i class="fa-solid fa-arrow-right-long go"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
