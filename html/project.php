<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['project'] ?? '';
$prettyRoute = preg_match('~(?:^|/)project/[a-z0-9-]+/?(?:\?.*)?$~i', (string)($_SERVER['REQUEST_URI'] ?? '')) === 1;
$availableProjects = projects();
$selectedProject = is_string($slug) ? ($availableProjects[$slug] ?? null) : null;

if ($selectedProject === null) {
    http_response_code(404);
}

$heading = $selectedProject['name'] ?? 'Project not found';
$purpose = $_GET['purpose'] ?? 'sale';
if (!is_string($purpose) || !in_array($purpose, ['sale', 'rent'], true)) {
    http_response_code(400);
    $purpose = 'sale';
    $purposeError = 'Choose Buy or Rent.';
}
$optionKey = $_GET['layer2'] ?? '';
$treeInput = $_GET['tree'] ?? '';
$state = ['roots'=>[],'row'=>[],'path'=>[],'root'=>null,'chosen'=>null,'tree'=>[],'unsupported'=>false];
$metadataError = '';
$selectionError = $purposeError ?? '';
$chosen = null;
$chosenExplorer = null;
$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
if ($selectedProject !== null) {
    $store = \App\Libraries\PortalCatalogStore::connect();
    try {
        if (!is_string($optionKey) || !is_string($treeInput)) throw new \InvalidArgumentException('Choose a valid project filter.');
        $state = $store->publicExplorerState($slug, $optionKey, $treeInput);
        $chosen = $state['chosen'];
        if ($state['path']) {
            $chosenExplorer = [
                'project'=>$slug,
                'source_id'=>$state['root']['source_id'],
                'head_id'=>$state['root']['head_id'],
                'path'=>array_map(static fn(array $node): array => [
                    'id'=>(string)$node['id'],
                    'source_field'=>(string)$node['source_field'],
                    'source_value'=>(string)$node['source_value'],
                ], $state['path']),
            ];
        }
    } catch (\Throwable $exception) {
        if ($exception instanceof \InvalidArgumentException) {
            $selectionError = 'This filter path is invalid or no longer available. Choose All to continue.';
            http_response_code(400);
        } else {
            error_log('[Property Explorer] Local metadata unavailable: ' . get_class($exception));
            $metadataError = 'Filter options are temporarily unavailable. You can still browse All.';
            if ($optionKey !== '' || $treeInput !== '') {
                $selectionError = 'This saved filter path is temporarily unavailable. Choose All after filters load again.';
                http_response_code(400);
            }
        }
        try { $state = $store->publicExplorerState($slug, '', ''); } catch (\Throwable $ignored) { $state = ['roots'=>[],'row'=>[],'path'=>[],'root'=>null,'chosen'=>null,'tree'=>[],'unsupported'=>false]; }
        $chosen = null;
        $chosenExplorer = null;
    }
    if ($selectionError !== '') {
        // Keep the error state empty rather than ever falling back to unfiltered listings.
        $chosenExplorer = null;
    }
    if ($selectionError !== '' && http_response_code() < 400) {
        http_response_code(400);
    }
    $filters = \App\Services\PublicListingSearch::filters(['project' => $slug, 'page' => $_GET['page'] ?? '1', 'per_page' => '12'], $purpose);
    if ($selectionError !== '') $filters['errors'][] = $selectionError;
    if ($filters['errors']) http_response_code(400);
    $result = $store->publicSearch($filters, [], true, $chosenExplorer);
}
$activeRootKey = $selectionError === '' && is_string($optionKey) ? $optionKey : '';
$activeTree = $selectionError === '' ? ($state['tree'] ?? []) : [];
$explorerUrl = static function (string $root = '', array $tree = [], int $page = 1, ?string $forPurpose = null) use ($slug, $purpose): string {
    $query = ['project' => $slug, 'purpose' => $forPurpose ?? $purpose];
    if ($root !== '') $query['layer2'] = $root;
    if ($tree) $query['tree'] = implode('.', $tree);
    if ($page > 1) $query['page'] = $page;
    return 'project.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
};
$pathLabels = array_map(static fn(array $node): string => (string)($node['display_name'] ?? ''), $state['path'] ?? []);
$breadcrumb = array_values(array_filter([$heading, ...$pathLabels], static fn(string $label): bool => $label !== ''));
$backUrl = '';
if ($activeRootKey !== '') {
    if ($activeTree) {
        $backTree = $activeTree;
        array_pop($backTree);
        $backUrl = $explorerUrl($activeRootKey, $backTree);
    } else {
        $backUrl = $explorerUrl();
    }
}
$rowOptions = $state['row'] ?? [];
$isRootView = $activeRootKey === '' && !$activeTree;
$treeBase = $activeTree;
$projectMode = \App\Services\PropertyExplorer::mode($slug);
$filterRowLabel = ($projectMode[0] ?? '') === 'sector' && $isRootView ? 'Sectors' : 'Property filters';
$childFieldLabels = [];
if (!$isRootView) {
    foreach ($rowOptions as $option) {
        $field = (string)($option['source_field'] ?? '');
        if ($field === '') continue;
        $childFieldLabels[$field] = match ($field) {
            'pro_inventory.floor_no' => 'Floor',
            'pro_inventory.aprt_catagory' => 'Apartment Category',
            'pro_inventory.no_of_floor' => 'Number of Floors',
            default => \App\Services\ExplorerChildBuilder::fieldLabel($field),
        };
    }
}
$childFilterLabel = implode(' / ', array_values($childFieldLabels));
if ($childFilterLabel !== '') $filterRowLabel = 'Filter by: ' . $childFilterLabel;
$active = 'projects';
$pageTitle = $heading . ' | Shalimar Properties';
$metaDesc = $heading;
$robots = $selectedProject === null ? 'noindex,nofollow' : 'index,follow';
$canonical = $selectedProject === null ? \App\Libraries\PublicWebsiteUrl::make('project.php') : project_seo_url($slug);

require __DIR__ . '/includes/header.php';
?>
<style>
    .project-explorer { padding-block: 20px 35px; }
    .project-explorer-shell .explorer-controls { padding: 0; border: 0; background: transparent; margin-top: 16px; }
    .project-explorer-shell .explorer-top { margin-bottom: 10px; }
    .project-explorer-shell .explorer-purpose { display: flex; gap: 6px; }
    .project-explorer-shell .explorer-purpose a { color: white; border: 1px solid rgba(255,255,255,.65); background: transparent; }
    .project-explorer-shell .explorer-purpose a[aria-current="true"] { background: var(--green); border-color: var(--gold); }
    .project-explorer-shell .explorer-badges { display: flex; flex-wrap: wrap; gap: 7px; }
    .project-explorer-shell .explorer-option { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 36px; max-width: 100%; padding: 7px 13px; border: 1px solid rgba(255,255,255,.75); border-radius: 999px; color: white; background: transparent; text-align: center; text-decoration: none; overflow-wrap: anywhere; transition: background-color 200ms ease, border-color 200ms ease; }
    .project-explorer-shell .explorer-option small { font-size: 12px; line-height: 1.35; font-weight: 600; }
    .project-explorer-shell .explorer-option[aria-current="true"] { background: var(--green); border-color: var(--gold); }
    .project-explorer-shell .explorer-option:hover { background: rgba(255,255,255,.16); border-color: var(--gold); }
    .project-explorer-shell .explorer-option:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }
    .project-explorer-shell .explorer-breadcrumb { margin: 0 0 10px; font-size: 11px; color: rgba(255,255,255,.9); overflow-wrap: anywhere; }
    .project-explorer-shell .explorer-field-label { margin: 0 0 7px; font-size: 12px; line-height: 1.4; font-weight: 600; color: white; overflow-wrap: anywhere; }
    .project-explorer-shell .page-hero .explorer-status { margin-top: 10px; color: rgba(255,255,255,.9); }
    .project-explorer-shell .listing-grid { transition: opacity 200ms ease, transform 200ms ease; }
    .project-explorer-shell.is-loading .listing-grid { opacity: .35; transform: translateY(4px); }
    .project-explorer-shell .explorer-results { animation: explorerEnter 220ms ease both; }
    .project-explorer-shell .explorer-status { font-size: 12px; color: var(--muted); margin: 0 0 12px; }
    @keyframes explorerEnter { from { opacity: .35; transform: translateY(5px); } to { opacity: 1; transform: none; } }
    @media (max-width: 767px) {
        .project-explorer-shell .explorer-badges { gap: 5px; }
        .project-explorer-shell .explorer-option { min-width: 34px; min-height: 32px; padding: 4px 9px; }
        .project-explorer-shell .explorer-option small { font-size: 11px; line-height: 1.25; }
        .project-explorer-shell .explorer-top { margin-bottom: 8px; }
        .project-explorer-shell .explorer-purpose a { min-height: 32px; padding: 5px 12px; font-size: 12px; }
        .project-explorer-shell .explorer-breadcrumb { margin-bottom: 8px; }
        .project-explorer-shell .explorer-field-label { font-size: 11px; margin-bottom: 5px; }
    }
    @media (prefers-reduced-motion: reduce) { .project-explorer-shell .explorer-results { animation: none; } .project-explorer-shell .listing-grid, .project-explorer-shell .explorer-option { transition: none; transform: none !important; } }
</style>
<main id="main" style="min-height:60vh">
<div class="project-explorer-shell" id="projectExplorer" aria-label="<?= e($heading) ?> properties">
    <section class="page-hero">
        <div class="wrap">
            <h1><?= e($heading) ?></h1>
            <?php if ($selectedProject !== null): ?>
        <div class="explorer-controls">
            <div class="explorer-top">
                <div class="explorer-purpose" aria-label="Listing purpose">
                    <?php foreach (['sale' => 'Buy', 'rent' => 'Rent'] as $key => $label): ?><a data-explorer-link class="btn btn-sm <?= $purpose === $key ? 'btn-solid' : 'btn-ghost' ?>" href="<?= e($explorerUrl('', [], 1, $key)) ?>" <?= $purpose === $key ? 'aria-current="true"' : '' ?>><?= $label ?></a><?php endforeach ?>
                </div>
            </div>
            <p class="explorer-breadcrumb" aria-label="Filter path"><?= e(implode(' › ', $breadcrumb)) ?><?= $chosen === null ? ' › All' : '' ?></p>
            <?php if ($childFilterLabel !== ''): ?><p class="explorer-field-label"><?= e($filterRowLabel) ?></p><?php endif ?>
            <nav class="explorer-badges" aria-label="<?= e($filterRowLabel) ?>">
                <a data-explorer-link class="explorer-option" href="<?= e($explorerUrl()) ?>" <?= $activeRootKey === '' && $selectionError === '' ? 'aria-current="true"' : '' ?>><small>All</small></a>
                <?php if ($isRootView): ?>
                    <?php foreach ($state['roots'] as $option): ?><a data-explorer-link class="explorer-option" href="<?= e($explorerUrl($option['key'])) ?>"><small><?= e($option['label']) ?></small></a><?php endforeach ?>
                    <a class="explorer-option explorer-back" href="home#hero" aria-label="Back to projects"><small>Projects</small></a>
                <?php else: ?>
                    <?php foreach ($rowOptions as $option): $nextTree = $treeBase; $nextTree[] = $option['id']; ?><a data-explorer-link class="explorer-option" href="<?= e($explorerUrl($activeRootKey, $nextTree)) ?>"><small><?= e($option['label']) ?></small></a><?php endforeach ?>
                    <?php if ($backUrl !== ''): ?><a data-explorer-link class="explorer-option explorer-back" href="<?= e($backUrl) ?>" aria-label="Back to previous filter"><small>Back</small></a><?php endif ?>
                <?php endif ?>
            </nav>
            <?php if (!$isRootView && !$rowOptions): ?><p class="explorer-status" role="status"><?= !empty($state['unsupported']) ? 'More filters are configured here, but their exact values are not saved with public listings yet.' : 'No more filters are configured for this selection.' ?></p><?php endif ?>
        </div>
            <?php endif ?>
        </div>
    </section>
    <?php if ($selectedProject !== null): ?>
    <section class="project-explorer wrap">
        <?php if ($metadataError !== ''): ?><p class="explorer-status" role="status"><?= e($metadataError) ?></p><?php endif ?>
        <?php if ($selectionError !== ''): ?><p role="alert"><?= e($selectionError) ?></p><?php endif ?>
        <div class="explorer-results">
            <p class="explorer-status" role="status"><?= number_format($result['total']) ?> <?= $purpose === 'rent' ? 'rental listings' : 'sale listings' ?><?= $chosen !== null ? ' · ' . e($chosen['label']) : '' ?></p>
            <?php if ($result['rows']): ?><div class="listing-grid"><?php foreach ($result['rows'] as $listing) echo render_card($listing); ?></div>
            <?php else: ?><div class="empty-note" role="status">No approved public <?= $purpose === 'rent' ? 'rental' : 'sale' ?> listings match this selection. <a data-explorer-link href="<?= e($explorerUrl()) ?>">Browse All</a>.</div><?php endif ?>
            <?php if ($result['pages'] > 1): ?><nav class="public-pagination" aria-label="Project listing pages">
                <?php if ($result['page'] > 1): ?><a data-explorer-link class="btn btn-ghost" href="<?= e($explorerUrl($activeRootKey, $activeTree, $result['page'] - 1)) ?>">Previous</a><?php endif ?>
                <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                <?php if ($result['page'] < $result['pages']): ?><a data-explorer-link class="btn btn-ghost" href="<?= e($explorerUrl($activeRootKey, $activeTree, $result['page'] + 1)) ?>">Next</a><?php endif ?>
            </nav><?php endif ?>
        </div>
    </section>
    <?php endif ?>
</div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
(() => {
    let controller;
    const reduce = matchMedia('(prefers-reduced-motion: reduce)');
    async function load(url, push) {
        const panel = document.getElementById('projectExplorer');
        if (!panel) return;
        controller?.abort();
        const current = controller = new AbortController();
        const focus = document.activeElement?.closest('[data-explorer-link]')?.getAttribute('href');
        panel.classList.add('is-loading');
        panel.setAttribute('aria-busy', 'true');
        try {
            const [response] = await Promise.all([
                fetch(url, { signal: current.signal, credentials: 'same-origin' }),
                new Promise(resolve => setTimeout(resolve, reduce.matches ? 0 : 200))
            ]);
            const html = await response.text();
            if (current.signal.aborted) return;
            const documentCopy = new DOMParser().parseFromString(html, 'text/html');
            const replacement = documentCopy.getElementById('projectExplorer');
            if (!response.ok || !replacement) throw new Error('Project results unavailable');
            panel.innerHTML = replacement.innerHTML;
            // Existing shared reveal observer does not observe newly inserted cards.
            panel.querySelectorAll('.rv').forEach(card => card.classList.add('in'));
            if (push) history.pushState(null, '', url);
            if (focus) [...panel.querySelectorAll('[data-explorer-link]')].find(link => link.getAttribute('href') === focus)?.focus({ preventScroll: true });
        } catch (error) {
            if (error.name !== 'AbortError') location.assign(url);
        } finally {
            if (controller === current) {
                panel.classList.remove('is-loading');
                panel.removeAttribute('aria-busy');
            }
        }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('#projectExplorer a[data-explorer-link]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href, true);
    }, true); // Claim Explorer clicks before the shared site's page-transition listener.
    addEventListener('popstate', () => load(location.href, false));
})();
</script>
</body>
</html>
