<?php if(!empty($dealerAssignment['applicable'])): $assignedDealer=$dealerAssignment['assignment']??null; ?>
<section class="card p-4 mb-3"><h2 class="h5">Agency Dealer Assignment</h2>
<?php if(empty($dealerAssignment['ready'])): ?><p class="text-muted mb-0">Dealer assignment information is unavailable.</p>
<?php elseif($assignedDealer): ?><p class="mb-3"><span class="badge text-bg-success">Assigned</span></p><dl class="row mb-0">
<dt class="col-sm-4">Dealer</dt><dd class="col-sm-8"><?= esc($assignedDealer['dealer_name']) ?></dd>
<dt class="col-sm-4">Assigned by agency</dt><dd class="col-sm-8"><?= esc($assignedDealer['agency_name']) ?></dd>
<dt class="col-sm-4">Source</dt><dd class="col-sm-8"><?= esc($assignedDealer['source_code']) ?></dd>
<dt class="col-sm-4">Assigned on</dt><dd class="col-sm-8"><?= esc($assignedDealer['assigned_at']) ?></dd>
</dl><?php else: ?><p class="text-muted mb-0">No current eligible dealer assignment. The listing may be unassigned, or its listing/membership eligibility has changed.</p><?php endif ?></section>
<?php endif ?>
