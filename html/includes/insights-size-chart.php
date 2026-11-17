<?php
// Preserve the existing include path for deployment compatibility.
$categoryReady=!empty($market['categories_available']);$categoryCounts=$market['categories']??[];
unset($categoryCounts['']);
$categoryTotal=array_sum($categoryCounts);
?>
<div class="chart-card rv" style="--d:80ms">
  <h3>Deals by Property Category</h3>
  <p class="sub">Last 30 days &middot; Based on recorded property categories.</p>
  <?php if($categoryReady&&$categoryTotal>0): ?>
    <p class="muted"><?= market_count($categoryTotal) ?> deals, grouped by category.</p>
    <div class="chart-box" style="height:<?= max(240,count($categoryCounts)*29+35) ?>px"><canvas id="chCategories" aria-label="<?= e('Deals by property category: '.implode('; ',array_map(static fn($category,$count)=>($category===''?'Unassigned':ucwords($category)).': '.$count,array_keys($categoryCounts),array_values($categoryCounts)))) ?>" role="img"></canvas></div>
  <?php elseif($categoryReady): ?>
    <p class="muted" role="status">No deals with a recorded category were found in the last 30 days for these filters.</p>
  <?php else: ?>
    <p class="muted" role="status">Property category breakdown is temporarily unavailable.</p>
  <?php endif ?>
</div>
