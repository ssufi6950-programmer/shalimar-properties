<div class="border rounded p-3 mb-3">
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= esc(site_url('admin/property-explorer').'?'.http_build_query(['project'=>$project['slug']]),'attr') ?>">Back to project filters</a>
    <?php if ($scope): ?>
    <nav class="small mb-3" aria-label="Filter ancestors" style="overflow-wrap:anywhere">
        <?= esc($project['name']) ?>
        <?php foreach ($scope['path'] as $ancestor): ?> &#8250; <a href="<?= esc(site_url('admin/property-explorer').'?'.http_build_query(['project'=>$project['slug'],'parent'=>(string)$ancestor['id']]),'attr') ?>"><?= esc($ancestor['display_name']) ?></a><?php endforeach ?>
    </nav>
    <h2 class="h6">Children of <?= esc(end($scope['path'])['display_name']) ?></h2>
    <p class="small text-muted mb-2"><?= count($scope['children']) ?> saved options<?= $scope['child_field']!==null ? ' - Field: '.esc(\App\Services\ExplorerChildBuilder::fieldLabel($scope['child_field'])) : ' - No child field chosen yet' ?></p>
    <p class="small text-muted">Choose a field, load its options, then select and save.</p>
    <?php if(can('projects.edit')&&isset($childSummaries)): ?>
    <details class="mb-3 border rounded p-2"><summary>Copy options from another parent</summary>
        <form class="mt-2" method="post" action="<?= site_url('admin/property-explorer/fetch') ?>">
            <?= csrf_field() ?><input type="hidden" name="project" value="<?= esc($project['slug'],'attr') ?>"><input type="hidden" name="parent" value="<?= esc($scope['parent_id'],'attr') ?>"><input type="hidden" name="operation" value="copy">
            <label class="form-label" for="copySource">Copy child options from</label>
            <select class="form-select" id="copySource" name="copy_source" required>
                <option value="">Choose a parent</option>
                <?php foreach($childSummaries as$parentId=>$summary): ?>
                <?php if((string)$parentId===$scope['parent_id']||$summary['source_id']!==$scope['source_id']||$summary['head_id']!==$scope['head_id']||count($summary['fields'])!==1||($scope['child_field']!==null&&!isset($summary['fields'][$scope['child_field']])))continue; ?>
                <option value="<?= esc($parentId,'attr') ?>"><?= esc($summary['name'].' (#'.$parentId.') - '.implode(' / ',array_values($summary['fields'])).' - '.$summary['count'].' options') ?></option>
                <?php endforeach ?>
            </select>
            <p class="small text-muted mt-2">Copies names, icons, visibility and order for this row only. Values are checked against this destination. Existing options stay unchanged. Review the draft before saving.</p>
            <button type="submit" class="btn btn-sm btn-outline-primary">Copy to draft</button>
        </form>
    </details>
    <?php endif ?>
    <details class="small text-muted mb-3"><summary>Source &amp; matching details</summary>
        <p><?= esc($scope['source']['name']) ?> ? Source #<?= esc($scope['source_id']) ?> ? Head #<?= esc($scope['head_id']) ?>. All ancestors apply to the same property. Phase, Street and composite Size/Unit are excluded.</p>
        <?php if($scope['child_field']!==null): ?><p><?= esc(\App\Services\ExplorerChildBuilder::readiness($scope['child_field'])['message']) ?></p><?php endif ?>
    </details>
    <form id="childCatalog" data-auto="<?= ($autoDiscover ?? false) ? '1' : '0' ?>" method="post" action="<?= site_url('admin/property-explorer/fetch') ?>">
        <?= csrf_field() ?><input type="hidden" name="project" value="<?= esc($project['slug'],'attr') ?>"><input type="hidden" name="parent" value="<?= esc($scope['parent_id'],'attr') ?>"><input type="hidden" name="operation" value="fields">
        <?php if (can('projects.edit')): ?><button class="btn btn-sm btn-outline-primary" type="submit">Load available fields</button><?php endif ?>
    </form>
    <?php if (isset($draft['catalog']['fields'])): ?>
        <?php $catalog=$draft['catalog']['fields'];$selected=$draft['field']??$scope['child_field']; ?>
        <form class="mt-3" method="post" action="<?= site_url('admin/property-explorer/fetch') ?>">
            <?= csrf_field() ?><input type="hidden" name="project" value="<?= esc($project['slug'],'attr') ?>"><input type="hidden" name="parent" value="<?= esc($scope['parent_id'],'attr') ?>"><input type="hidden" name="operation" value="values">
            <label class="form-label" for="childField">1. Choose child field</label>
            <select class="form-select" id="childField" name="field" required <?= !can('projects.edit')?'disabled':'' ?>>
                <option value="">Choose a safe field</option>
                <?php foreach($catalog as$key=>$field): ?>
                <option value="<?= esc($key,'attr') ?>" <?= $selected===$key?'selected':'' ?> <?= $scope['child_field']!==null&&$scope['child_field']!==$key?'disabled':'' ?> data-readiness="<?= esc($field['readiness']['message'].' Public child navigation is not enabled in C1.','attr') ?>"><?= esc($field['label'].' &#8212; '.$field['data_type'].' &#8212; '.$key) ?></option>
                <?php endforeach ?>
            </select>
            <?php if($scope['child_field']!==null): ?><p class="small mt-2">Saved options use <strong><?= esc(\App\Services\ExplorerChildBuilder::fieldLabel($scope['child_field'])) ?></strong>. The field stays fixed to protect existing children.</p><?php endif ?>
            <p class="alert alert-warning small mt-2" id="childReadiness" role="status">Public matching readiness is separate from ERP discovery.</p>
            <?php if(can('projects.edit')&&$catalog&&($scope['child_field']===null||isset($catalog[$scope['child_field']]))): ?><button class="btn btn-outline-primary" type="submit">2. Load options</button><?php endif ?>
        </form>
        <details class="mt-3 small"><summary>Field capabilities</summary><div aria-label="Discovered field capabilities">
            <?php foreach($catalog as$field): ?><p class="mb-2"><strong><?= esc($field['label']) ?></strong>: ERP discovery available &#183; Admin option fetching available.<br><?= esc($field['readiness']['message']) ?> Public child navigation: disabled in C1.</p><?php endforeach ?>
        </div></details>
        <?php if($draft['catalog']['unavailable']??[]): ?><details class="small mt-2"><summary>Fields unavailable in this branch</summary><?php foreach($draft['catalog']['unavailable']as$item): ?><p><code><?= esc($item['key']) ?></code>: <?= esc($item['reason']) ?></p><?php endforeach ?></details><?php endif ?>
        <?php if(!$catalog): ?><p role="status" class="text-muted mt-2">No suitable, populated, low-cardinality fields are available for this branch. Saved children are preserved.</p><?php endif ?>
    <?php else: ?><p class="small mt-2" role="status">Load available fields to add options. Existing saved children can still be edited below.</p><?php endif ?>
    <?php endif ?>
</div>
