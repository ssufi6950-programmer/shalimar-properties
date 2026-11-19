<div class="border rounded p-3 mb-3">
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= esc(site_url('admin/property-explorer').'?'.http_build_query(['project'=>$project['slug']]),'attr') ?>">Back to project filters</a>
    <?php if ($scope): ?>
    <nav class="small mb-3" aria-label="Filter ancestors" style="overflow-wrap:anywhere">
        <?= esc($project['name']) ?>
        <?php foreach ($scope['path'] as $ancestor): ?>
            &#8250; <a href="<?= esc(site_url('admin/property-explorer').'?'.http_build_query(['project'=>$project['slug'],'parent'=>(string)$ancestor['id']]),'attr') ?>"><?= esc($ancestor['display_name']) ?></a>
        <?php endforeach ?>
    </nav>
    <h2 class="h6">Existing children of <?= esc(end($scope['path'])['display_name']) ?></h2>
    <p class="small text-muted mb-2">
        <?= count($scope['children']) ?> saved options<?= $scope['child_field']!==null ? ' - Field: '.esc(\App\Services\ExplorerChildBuilder::fieldLabel($scope['child_field'])) : '' ?>.
        Existing options are retained and may be edited.
    </p>
    <p class="alert alert-info small mb-3" role="status">New filter types can only be added at Layer 1, from the project filters page.</p>
    <details class="small text-muted mb-3">
        <summary>Source &amp; matching details</summary>
        <p><?= esc($scope['source']['name']) ?> &#183; Source #<?= esc($scope['source_id']) ?> &#183; Head #<?= esc($scope['head_id']) ?>. All ancestors apply to the same property.</p>
        <?php if($scope['child_field']!==null): ?><p><?= esc(\App\Services\ExplorerChildBuilder::readiness($scope['child_field'])['message']) ?></p><?php endif ?>
    </details>
    <?php endif ?>
</div>
