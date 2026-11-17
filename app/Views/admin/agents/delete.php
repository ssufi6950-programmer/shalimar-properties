<?= $this->extend('admin/layouts/main') ?><?= $this->section('content') ?>
<div class="page-heading"><div><h1>Delete Agent</h1><p>Review this portal record before deleting it.</p></div></div>
<section class="card p-4">
    <h2 class="h5"><?= esc($agent['name']) ?> <small class="text-muted">Portal Agent #<?= (int)$agent['id'] ?></small></h2>
    <p><?= (int)$listingCount ?> listing assignments · <?= count($links) ?> ERP links</p>
    <?php if($listingCount): ?>
        <div class="alert alert-warning">This agent has listing assignments or history and cannot be deleted. Use Disable on the agents screen instead.</div>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/agents') ?>">Back to Agents</a>
    <?php else: ?>
        <p>This removes the unused agent and its ERP links from the portal. The ERP dealer record stays unchanged. A future ERP sync may import the agent again.</p>
        <form method="post" action="<?= site_url('admin/agents/'.(int)$agent['id'].'/delete') ?>">
            <?= csrf_field() ?>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirmAgentDelete" name="confirmed" value="1" required><label class="form-check-label" for="confirmAgentDelete">Delete this unused portal agent.</label></div>
            <div class="d-flex gap-2"><button class="btn btn-danger" type="submit">Delete Agent</button><a class="btn btn-outline-secondary" href="<?= site_url('admin/agents') ?>">Cancel</a></div>
        </form>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
