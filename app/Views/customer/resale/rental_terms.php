<dl class="app-facts app-rental-terms">
<?php foreach(['security_deposit'=>'Security deposit (PKR)','advance_rent'=>'Advance rent (PKR)','furnishing'=>'Furnishing','lease_duration_months'=>'Lease duration']as$key=>$label):$value=$rental[$key]??null; ?>
<div><dt><?= esc($label) ?></dt><dd><?php if($value===null||$value===''): ?>Not specified<?php elseif(in_array($key,['security_deposit','advance_rent'],true)): ?>PKR <?= number_format((float)$value) ?><?php elseif($key==='lease_duration_months'): ?><?= esc($value) ?> months<?php else: ?><?= esc(ucwords(str_replace('_',' ',(string)$value))) ?><?php endif ?></dd></div>
<?php endforeach ?>
</dl>
