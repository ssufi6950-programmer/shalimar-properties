<?php if(can('agents.view')&&can('resale_listings.view')&&($agentReviews=service('shalimarAgents')->reviewCount())>0): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4" role="status" data-agent-review-alert><strong><?= $agentReviews ?> Shalimar Agent assignments require review</strong><a class="btn btn-sm btn-outline-dark" href="<?= site_url('admin/agents?review=1') ?>">Review Assignments</a></div>
<?php endif ?>
