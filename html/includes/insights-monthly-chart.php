<?php $monthlyReady=!empty($market['monthly_types_available']);$monthlyRows=$market['monthly_by_type']??[];$monthlyTotal=array_sum(array_map('array_sum',$monthlyRows)); ?>
<div class="chart-card rv" style="margin-top:14px">
  <h3>Monthly transactions</h3>
  <p class="sub">Last 12 months by type (PKT)</p>
  <?php if($monthlyReady&&$monthlyTotal>0): ?>
    <p class="muted">Sale Deals combines sales and resales. Rental Deals includes all recorded check-ins, including owner check-ins.</p>
    <div class="chart-box" style="height:280px"><canvas id="chMonthly" aria-label="<?= e('Monthly transactions: '.implode('; ',array_map(static fn($month,$parts)=>$month.': Sale Deals '.$parts['sale_deals'].', Rental Deals '.$parts['rental_deals'],array_keys($monthlyRows),array_values($monthlyRows)))) ?>" role="img"></canvas></div>
  <?php elseif($monthlyReady): ?>
    <p class="muted" role="status">No Sale Deals or Rental Deals were recorded in these months for these filters.</p>
  <?php else: ?>
    <p class="muted" role="status">Monthly transaction breakdown is temporarily unavailable.</p>
  <?php endif ?>
</div>
