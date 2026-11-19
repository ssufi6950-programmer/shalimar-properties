<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<?php $isChild = $isChild ?? false; ?>
<section class="card p-3">
    <h1 class="h4">Property Explorer &#8212; Filter Builder</h1>
    <p class="text-muted small"><?= $isChild ? 'Manage options inside the selected parent filter.' : 'Select a project, then manage its filters and the children inside each filter.' ?></p>
    <?php foreach (['error' => 'danger', 'success' => 'success'] as $key => $style): ?>
        <?php if ($message = session()->getFlashdata($key)): ?><div class="alert alert-<?= $style ?>" role="status"><?= esc($message) ?></div><?php endif ?>
    <?php endforeach ?>
    <form method="get" action="<?= site_url('admin/property-explorer') ?>" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <div><label for="explorerProject" class="form-label">Portal project</label><select class="form-select" id="explorerProject" name="project"><?php foreach ($projects as $item): ?><option value="<?= esc($item['slug'], 'attr') ?>" <?= $project['slug'] === $item['slug'] ? 'selected' : '' ?>><?= esc($item['name']) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-primary" type="submit">Open project</button>
    </form>
    <?php if ($metadataError !== ''): ?><div class="alert alert-warning" role="alert"><?= esc($metadataError) ?></div><?php endif ?>
    <?php if ($isChild): ?>
        <?= $this->include('admin/projects/explorer_children_context') ?>
    <?php elseif ($scope): ?>
        <div class="border rounded p-3 mb-3">
            <p class="mb-2"><strong><?= esc($project['name']) ?></strong> <span class="text-muted small"> &#183; <?= esc(\App\Services\ExplorerChildBuilder::fieldLabel($scope['field'])) ?> filters</span></p>
            <details class="small text-muted mb-2"><summary>Source details</summary><p><?= esc($scope['source']['name']) ?> &#183; Source #<?= esc($scope['source_id']) ?> &#183; Head #<?= esc($scope['head_id']) ?> &#183; <code><?= esc($scope['field']) ?></code>.
                <?= $scope['field'] === 'pro_inventory.sector' ? 'All distinct valid sectors under this Head, across ALL Subheads.' : 'Only Sub Location IDs in this project?s current mappings.' ?></p>
            </details>
            <form method="post" action="<?= site_url('admin/property-explorer/fetch') ?>">
                <?= csrf_field() ?><input type="hidden" name="project" value="<?= esc($project['slug'], 'attr') ?>">
                <?php if (can('projects.edit')): ?><button class="btn btn-outline-primary" type="submit"><?= isset($draft['fetched_at']) ? 'Refresh from ERP' : 'Fetch Options from ERP' ?></button><?php endif ?>
                <small class="d-block text-muted mt-2">Find more options. Your saved settings are kept.</small>
            </form>
        </div>
    <?php endif ?>
    <h2 class="h6 mt-3"><?= $isChild ? 'Options inside this parent' : 'Project filters' ?> <span class="text-muted">(<?= count($rows) ?>)</span></h2>
    <?php if (isset($draft['fetched_at'])): ?><p class="small text-muted">New options are unchecked. Select and save to keep them.</p><?php endif ?>
    <noscript><div class="alert alert-info">Enable JavaScript to submit selected filters. Fetch and saved-filter viewing remain available.</div></noscript>
    <form method="post" action="<?= site_url('admin/property-explorer') ?>" id="filterBuilderSave">
        <?= csrf_field() ?>
        <input type="hidden" name="project" value="<?= esc($project['slug'], 'attr') ?>">
        <?php if ($isChild && $scope): ?><input type="hidden" name="parent" value="<?= esc($scope['parent_id'], 'attr') ?>"><?php endif ?>
        <input type="hidden" name="draft_id" value="<?= esc($draft['id'] ?? '', 'attr') ?>">
        <input type="hidden" name="selection" value="[]">
        <?php if (!$rows): ?><p class="text-muted" role="status">No saved or fetched options. Fetch from ERP to discover real source values.</p><?php endif ?>
        <?php foreach ($rows as $key => $row): ?>
            <fieldset class="border-top py-3" data-filter-key="<?= esc($key, 'attr') ?>" data-version="<?= (int) $row['record_version'] ?>" <?= !can('projects.edit') || !$row['editable'] ? 'disabled' : '' ?>>
                <div class="d-flex gap-2 align-items-start mb-2">
                    <input class="form-check-input" type="checkbox" id="select-<?= $key ?>" data-select <?= $row['saved'] || ($row['copy_selected']??false) ? 'checked' : '' ?>>
                    <div class="flex-grow-1" style="min-width:0">
                        <label for="select-<?= $key ?>"><strong><?= esc($row['display_name']) ?></strong></label>
                        <span class="badge <?= !$row['saved'] ? 'bg-warning text-dark' : ((int)$row['is_visible'] ? 'bg-success' : 'bg-secondary') ?> ms-1"><?= !$row['saved'] ? 'New &#183; unsaved' : ((int)$row['is_visible'] ? 'Shown' : 'Hidden') ?></span>
                        <?php if ($row['saved']): ?>
                            <?php $summary=$childSummaries[(string)$row['id']]??['count'=>0,'visible'=>0,'fields'=>[],'children'=>[]]; ?>
                            <div class="small mt-2" data-child-summary="<?= (int)$row['id'] ?>">
                                <?php if(!isset($childSummaries)): ?><span class="text-muted">Child summary is temporarily unavailable. Open this filter to review its children.</span>
                                <?php elseif($summary['count']): ?>
                                    <strong><?= esc(implode(' / ',array_values($summary['fields']))) ?></strong> &#183; <?= $summary['count'] ?> child options &#183; <?= $summary['visible'] ?> shown
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                    <?php foreach($summary['children']as$child): ?><a class="border rounded px-2 py-1 text-decoration-none" style="max-width:100%;overflow-wrap:anywhere" href="<?= esc(site_url('admin/property-explorer').'?'.http_build_query(['project'=>$project['slug'],'parent'=>$child['id']]),'attr') ?>"><?= esc($child['name']) ?><?= $child['visible']?'':' (Hidden)' ?></a><?php endforeach ?>
                                    </div>
                                <?php else: ?><span class="text-muted">No child filters configured.</span><?php endif ?>
                            </div>
                            <?php if($summary['count']): ?><a class="btn btn-sm btn-outline-primary mt-2" href="<?= esc(site_url('admin/property-explorer') . '?' . http_build_query(['project'=>$project['slug'],'parent'=>(string)$row['id']]), 'attr') ?>"><?= can('projects.edit') ? 'Manage existing children' : 'View children' ?></a><?php else: ?><p class="small text-muted mt-2 mb-0">New filter types can only be added at Layer 1.</p><?php endif ?>
                        <?php endif ?>
                        <details class="small text-muted mt-2"><summary>Original value &amp; details</summary>Exact source value: <code><?= esc(json_encode($row['value'], JSON_UNESCAPED_UNICODE)) ?></code> &#183; <?= $row['saved']?'Saved #'.(int)$row['id']:'Not saved' ?><?= !$row['editable']?' &#183; Outside current mapping; editing unavailable':'' ?><?= !$row['available']&&$row['saved']?' &#183; Retained saved option; not confirmed by the latest fetch':'' ?></details>
                    </div>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4"><label class="form-label" for="name-<?= $key ?>">Website Display Name</label><input class="form-control" data-field="label" id="name-<?= $key ?>" maxlength="120" value="<?= esc($row['display_name'], 'attr') ?>"></div>
                    <div class="col-6 col-lg-2"><label class="form-label" for="icon-<?= $key ?>">Icon</label><select class="form-select" data-field="icon" id="icon-<?= $key ?>"><?php foreach ($icons as $icon => $name): ?><option value="<?= esc($icon, 'attr') ?>" <?= $row['icon'] === $icon ? 'selected' : '' ?>><?= esc($name) ?></option><?php endforeach ?></select></div>
                    <div class="col-6 col-lg-2"><label class="form-label" for="show-<?= $key ?>">Visibility</label><select class="form-select" data-field="visible" id="show-<?= $key ?>"><option value="0" <?= !(int) $row['is_visible'] ? 'selected' : '' ?>>Hide</option><option value="1" <?= (int) $row['is_visible'] ? 'selected' : '' ?>>Show</option></select></div>
                    <div class="col-6 col-lg-2"><label class="form-label" for="order-<?= $key ?>">Display Order</label><input class="form-control" data-field="order" type="number" id="order-<?= $key ?>" min="0" max="99999" value="<?= (int) $row['display_order'] ?>"></div>
                </div>
            </fieldset>
        <?php endforeach ?>
        <?php if ($rows && $scope && can('projects.edit')): ?><button class="btn btn-primary mt-3" type="submit">Save Selected Filters</button><?php endif ?>
        <p class="small text-muted mt-2">Select the options you want to save. To hide a saved option, choose Hide and save. Unchecking does not delete it.</p>
        <p class="text-danger small" id="builderValidation" role="alert" hidden></p>
    </form>
</section>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(() => {
    const discovery = document.getElementById('childCatalog');
    if (discovery?.dataset.auto === '1') discovery.requestSubmit();
    const chooser = document.getElementById('childField');
    const readiness = document.getElementById('childReadiness');
    function updateReadiness() {
        if (readiness && chooser) readiness.textContent = chooser.selectedOptions[0]?.dataset.readiness || 'Choose a field to see its matching status.';
    }
    chooser?.addEventListener('change', updateReadiness);
    updateReadiness();
    const form = document.getElementById('filterBuilderSave');
    form.addEventListener('submit', event => {
        const selected = [...form.querySelectorAll('fieldset[data-filter-key]:not([disabled])')]
            .filter(row => row.querySelector('[data-select]').checked)
            .map(row => {
                const item = { key: row.dataset.filterKey, version: row.dataset.version };
                row.querySelectorAll('[data-field]').forEach(field => item[field.dataset.field] = field.value);
                return item;
            });
        if (!selected.length) {
            event.preventDefault();
            const error = document.getElementById('builderValidation');
            error.textContent = 'Select at least one filter to save.';
            error.hidden = false;
        }
        form.elements.selection.value = JSON.stringify(selected);
    });
})();
</script>
<?= $this->endSection() ?>
