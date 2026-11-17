<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-heading">
    <div>
        <h1>Projects</h1>
        <p><?= count($rows) ?> projects from the portal database.</p>
    </div>
    <?php if (can('projects.create')): ?>
        <a class="btn btn-primary" href="<?= site_url('admin/workspace/project-form') ?>"><i class="bi bi-plus-lg"></i> Add Project</a>
    <?php endif ?>
</div>
<section class="card table-card">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Template</th>
                    <th>Editor saved</th>
                    <th>Public status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?= esc($row['display_name']) ?></strong>
                            <small class="d-block text-muted"><?= esc($row['slug']) ?></small>
                        </td>
                        <td><?= esc(\App\Services\ProjectPageEditor::TEMPLATES[$row['editor']['template'] ?? ''] ?? 'Choose on first edit') ?></td>
                        <td><?= esc($row['editor']['updated_at'] ?? 'Not saved yet') ?></td>
                        <td><?= $row['published'] ? 'Published project' : 'Draft project' ?></td>
                        <td>
                            <?php if (can('projects.edit')): ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/workspace/project-form') ?>?id=<?= rawurlencode($row['slug']) ?>">Edit Project</a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">No projects have been added yet.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
