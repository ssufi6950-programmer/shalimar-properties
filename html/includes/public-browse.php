<?php
declare(strict_types=1);

// Shared by the established buy.php and rent.php URLs.
require_once __DIR__ . '/bootstrap.php';

$isAll = $browsePurpose === 'all';
$active = $browsePurpose === 'rent' ? 'rent' : ($isAll ? '' : 'buy');
$isRent = $browsePurpose === 'rent';
$route = $isAll ? 'properties.php' : $active . '.php';
$projects = projects();
$projectInput = $_GET['project'] ?? '';
$slug = is_string($projectInput) ? trim($projectInput) : '';
$selectedProject = $slug !== '' ? ($projects[$slug] ?? null) : null;
$optionKey = $_GET['layer2'] ?? '';
$treeInput = $_GET['tree'] ?? '';
$selectionError = '';
$metadataError = '';
$chosen = null;
$chosenExplorer = null;
$state = ['roots'=>[], 'row'=>[], 'path'=>[], 'root'=>null, 'chosen'=>null, 'tree'=>[], 'unsupported'=>false];

if ($slug !== '' && $selectedProject === null) {
    $selectionError = 'Choose an available project.';
    http_response_code(400);
}
if (!is_string($optionKey) || !is_string($treeInput)) {
    $selectionError = 'Choose a valid property filter.';
    $optionKey = '';
    $treeInput = '';
    http_response_code(400);
}

$store = \App\Libraries\PortalCatalogStore::connect();
if ($selectedProject !== null) {
    try {
        $state = $store->publicExplorerState($slug, $optionKey, $treeInput);
        $chosen = $state['chosen'];
        if ($state['path']) {
            $chosenExplorer = [
                'project' => $slug,
                'source_id' => $state['root']['source_id'],
                'head_id' => $state['root']['head_id'],
                'path' => array_map(static fn(array $node): array => [
                    'id' => (string) $node['id'],
                    'source_field' => (string) $node['source_field'],
                    'source_value' => (string) $node['source_value'],
                ], $state['path']),
            ];
        }
    } catch (\Throwable $exception) {
        if ($exception instanceof \InvalidArgumentException) {
            $selectionError = 'This filter path is invalid or no longer available.';
            http_response_code(400);
        } elseif ($optionKey !== '' || $treeInput !== '') {
            error_log('[Public Property Explorer] Selected metadata path unavailable: ' . get_class($exception));
            $selectionError = 'This filter path is temporarily unavailable.';
            http_response_code(503);
        } else {
            error_log('[Public Property Explorer] Local metadata unavailable: ' . get_class($exception));
            $metadataError = 'Filter options are temporarily unavailable. Project listings are still shown.';
        }
        try {
            $state = $store->publicExplorerState($slug, '', '');
        } catch (\Throwable $ignored) {
            $state = ['roots'=>[], 'row'=>[], 'path'=>[], 'root'=>null, 'chosen'=>null, 'tree'=>[], 'unsupported'=>false];
        }
        $chosen = null;
        $chosenExplorer = null;
    }
}

$searchInput = [
    'project' => $selectedProject !== null ? $slug : '',
    'type' => is_string($_GET['type'] ?? null) ? trim($_GET['type']) : '',
    'page' => is_string($_GET['page'] ?? null) ? $_GET['page'] : '1',
    'per_page' => '12',
];
$filters = \App\Services\PublicListingSearch::filters($searchInput, $isAll ? '' : $browsePurpose);
if ($selectionError !== '') $filters['errors'][] = $selectionError;
$result = $store->publicSearch($filters, $isAll ? ['sale', 'rent'] : [], true, $selectionError === '' ? $chosenExplorer : null);

$activeRootKey = $selectionError === '' ? $optionKey : '';
$activeTree = $selectionError === '' ? ($state['tree'] ?? []) : [];
$isProjectStep = $selectedProject === null;
$isRootView = !$isProjectStep && $activeRootKey === '' && !$activeTree;
$rowOptions = $state['row'] ?? [];
$treeBase = $activeTree;

$browseType = (string)($filters['type'] ?? '');
$browseUrl = static function (string $project = '', string $root = '', array $tree = [], int $page = 1) use ($route, $browseType): string {
    $query = [];
    if ($browseType !== '') $query['type'] = $browseType;
    if ($project !== '') $query['project'] = $project;
    if ($root !== '') $query['layer2'] = $root;
    if ($tree) $query['tree'] = implode('.', $tree);
    if ($page > 1) $query['page'] = $page;
    return $route . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
};

$backUrl = '';
if (!$isProjectStep) {
    if ($activeTree) {
        $backTree = $activeTree;
        array_pop($backTree);
        $backUrl = $browseUrl($slug, $activeRootKey, $backTree);
    } elseif ($activeRootKey !== '') {
        $backUrl = $browseUrl($slug);
    } else {
        $backUrl = $browseUrl();
    }
}

$projectName = $selectedProject['name'] ?? '';
$pathLabels = array_map(static fn(array $node): string => (string) ($node['display_name'] ?? ''), $state['path'] ?? []);
$breadcrumb = array_values(array_filter([$projectName, ...$pathLabels], static fn(string $label): bool => $label !== ''));
$filterRowLabel = $isProjectStep ? 'Filter by Project' : 'Property filters';
if (!$isProjectStep && !$isRootView) {
    $fieldLabels = [];
    foreach ($rowOptions as $option) {
        $field = (string) ($option['source_field'] ?? '');
        if ($field === '') continue;
        $fieldLabels[$field] = match ($field) {
            'pro_inventory.floor_no' => 'Floor',
            'pro_inventory.aprt_catagory' => 'Apartment Category',
            'pro_inventory.no_of_floor' => 'Number of Floors',
            default => \App\Services\ExplorerChildBuilder::fieldLabel($field),
        };
    }
    if ($fieldLabels) $filterRowLabel = 'Filter by: ' . implode(' / ', array_values($fieldLabels));
}

$typeTitle = match ($filters['type'] ?? '') {
    'plot' => 'Plots', 'house' => 'Houses & Villas', 'apartment' => 'Apartments', 'commercial' => 'Commercial Properties', default => ''
};
$pageHeading = $isAll ? trim($typeTitle . ' for Sale & Rent') : ($isRent ? 'Properties for rent' : 'Properties for sale');
$pageTitle = ($pageHeading ?: 'Properties') . ' — Shalimar Properties';
$metaDesc = $isAll
    ? 'Browse approved properties for sale and rent by property type and project.'
    : ($isRent
    ? 'Explore approved rental properties by project and configured property filters.'
    : 'Explore approved properties for sale by project and configured property filters.');
$canonical = \App\Libraries\PublicWebsiteUrl::make($route, $result['page'] > 1 ? ['page' => $result['page']] : []);
$robots = ($_GET || $filters['errors']) ? 'noindex,follow' : 'index,follow';

require __DIR__ . '/header.php';
?>
<style>
    .browse-explorer-shell .explorer-controls{padding:0;border:0;background:transparent;margin-top:16px}
    .browse-explorer-shell .explorer-breadcrumb{margin:0 0 8px;font-size:12px;color:rgba(255,255,255,.9);overflow-wrap:anywhere}
    .browse-explorer-shell .explorer-field-label{margin:0 0 7px;color:#fff;font-size:12px;font-weight:700}
    .browse-explorer-shell .explorer-badges{display:flex;flex-wrap:wrap;gap:7px}
    .browse-explorer-shell .explorer-option{display:inline-flex;align-items:center;justify-content:center;min-height:36px;max-width:100%;padding:7px 13px;border:1px solid rgba(255,255,255,.75);border-radius:999px;color:#fff;background:transparent;text-align:center;text-decoration:none;overflow-wrap:anywhere;transition:background-color 200ms ease,border-color 200ms ease}
    .browse-explorer-shell .explorer-option small{font-size:12px;line-height:1.35;font-weight:600}
    .browse-explorer-shell .explorer-project-option{gap:7px;padding-left:7px}
    .browse-explorer-shell .explorer-project-logo{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;width:28px;height:24px;padding:2px 4px;border-radius:7px;background:rgba(255,255,255,.96);overflow:hidden}
    .browse-explorer-shell .explorer-project-logo .plogo{display:block;width:100%;height:100%;object-fit:contain}
    .browse-explorer-shell .explorer-project-logo .plogo-mono{width:22px;height:20px;border-radius:5px;font-size:8px}
    .browse-explorer-shell .explorer-option[aria-current="true"]{background:var(--green);border-color:var(--gold)}
    .browse-explorer-shell .explorer-option:hover{background:rgba(255,255,255,.16);border-color:var(--gold)}
    .browse-explorer-shell .explorer-option:focus-visible{outline:2px solid var(--gold);outline-offset:3px}
    .browse-explorer-shell .explorer-note{margin:10px 0 0;color:rgba(255,255,255,.88);font-size:12px}
    .browse-all-types .explorer-breadcrumb{color:#68776f}
    .browse-all-types .explorer-field-label{color:#344c41}
    .browse-all-types .explorer-option{color:#214b3b;border-color:#c9d8cf;background:rgba(255,255,255,.92)}
    .browse-all-types .explorer-option[aria-current="true"]{color:#fff;background:var(--green);border-color:var(--gold)}
    .browse-all-types .explorer-option:hover{color:#174b39;background:#eaf3ed;border-color:var(--green)}
    .browse-all-types .explorer-option[aria-current="true"]:hover{color:#fff;background:var(--green)}
    .browse-all-types .explorer-note{color:#52685d}
    .browse-explorer-shell .browse-results{transition:opacity 200ms ease,transform 200ms ease}
    .browse-explorer-shell.is-loading .browse-results{opacity:.35;transform:translateY(4px)}
    @media(max-width:767px){.browse-explorer-shell .explorer-badges{gap:5px}.browse-explorer-shell .explorer-option{min-height:32px;padding:4px 9px}.browse-explorer-shell .explorer-project-option{gap:5px;padding-left:5px}.browse-explorer-shell .explorer-project-logo{width:25px;height:22px;padding:2px 3px}.browse-explorer-shell .explorer-option small{font-size:11px}.browse-explorer-shell .explorer-field-label{font-size:11px;margin-bottom:5px}}
    @media(prefers-reduced-motion:reduce){.browse-explorer-shell .browse-results,.browse-explorer-shell .explorer-option{transition:none;transform:none!important}}
</style>
<main id="main" class="public-discovery browse-explorer-shell<?= $isAll ? ' browse-all-types' : '' ?>" data-browse-explorer>
<div id="browseExplorerPanel">
<section class="page-hero"><div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span><?= $isAll ? 'Properties' : ($isRent ? 'Rent' : 'Buy') ?></span></div>
    <h1><?= e($pageHeading ?: 'Properties for sale & rent') ?></h1>
    <p class="lede"><?= $isAll ? 'Browse approved sale and rental properties. Rental prices are monthly.' : ($isRent ? 'Browse approved rental properties. Every price is monthly rent.' : 'Browse approved properties for sale with clear asking prices.') ?></p>
    <div class="explorer-controls">
        <?php if ($breadcrumb): ?><p class="explorer-breadcrumb" aria-label="Filter path"><?= e(implode(' › ', $breadcrumb)) ?><?= $chosen === null && !$isProjectStep ? ' › All' : '' ?></p><?php endif ?>
        <p class="explorer-field-label"><?= e($filterRowLabel) ?></p>
        <nav class="explorer-badges" aria-label="<?= e($filterRowLabel) ?>">
            <?php if ($isProjectStep): ?>
                <a data-explorer-link class="explorer-option" href="<?= e($browseUrl()) ?>" aria-current="true"><small>All</small></a>
                <?php foreach ($projects as $project): ?><a data-explorer-link class="explorer-option explorer-project-option" href="<?= e($browseUrl((string) $project['slug'])) ?>"><span class="explorer-project-logo"><?= project_logo_html($project, 'plogo') ?></span><small><?= e($project['name']) ?></small></a><?php endforeach ?>
            <?php else: ?>
                <a data-explorer-link class="explorer-option" href="<?= e($browseUrl($slug)) ?>" <?= $activeRootKey === '' ? 'aria-current="true"' : '' ?>><small>All</small></a>
                <?php if ($isRootView): ?>
                    <?php foreach ($state['roots'] as $option): ?><a data-explorer-link class="explorer-option" href="<?= e($browseUrl($slug, $option['key'])) ?>"><small><?= e($option['label']) ?></small></a><?php endforeach ?>
                <?php else: ?>
                    <?php foreach ($rowOptions as $option): $nextTree = $treeBase; $nextTree[] = $option['id']; ?><a data-explorer-link class="explorer-option" href="<?= e($browseUrl($slug, $activeRootKey, $nextTree)) ?>"><small><?= e($option['label']) ?></small></a><?php endforeach ?>
                <?php endif ?>
                <a data-explorer-link class="explorer-option explorer-back" href="<?= e($backUrl) ?>"><small>Back</small></a>
            <?php endif ?>
        </nav>
        <?php if ($metadataError !== ''): ?><p class="explorer-note" role="status"><?= e($metadataError) ?></p><?php endif ?>
        <?php if ($selectionError !== ''): ?><p class="explorer-note" role="alert"><?= e($selectionError) ?></p><?php endif ?>
        <?php if (!$isProjectStep && !$isRootView && !$rowOptions): ?><p class="explorer-note" role="status"><?= !empty($state['unsupported']) ? 'More filters are configured here, but exact values are not available with public listings yet.' : 'No more filters are configured for this selection.' ?></p><?php endif ?>
    </div>
</div></section>
<section class="sec-tight browse-results"><div class="wrap">
    <div class="result-bar"><div><h2><?= number_format($result['total']) ?> <?= $isAll ? 'properties' : ($isRent ? 'rental properties' : 'properties for sale') ?></h2><p><?= $selectedProject !== null ? e($projectName) . ' · ' : '' ?>Admin-approved, currently published listings<?= $isAll ? ' for Sale and Rent' : '' ?>.</p></div><a href="post-property.php<?= $isRent ? '?purpose=rent' : '' ?>" class="btn btn-ghost btn-sm"><?= $isAll ? 'Post a property' : ($isRent ? 'Rent out' : 'Sell') . ' your property' ?></a></div>
    <?php if ($result['rows']): ?><div class="listing-grid"><?php foreach ($result['rows'] as $i => $listing) echo render_card($listing, ($i % 3) * 60); ?></div><?php else: ?><div class="empty-note" role="status">No approved <?= $isAll ? 'properties' : ($isRent ? 'rentals' : 'sale properties') ?> match this selection. <a data-explorer-link href="<?= e($browseUrl($slug)) ?>">Browse All</a>.</div><?php endif ?>
    <?php if ($result['pages'] > 1): ?><nav class="public-pagination" aria-label="Property result pages"><?php if ($result['page'] > 1): ?><a data-explorer-link class="btn btn-ghost" rel="prev" href="<?= e($browseUrl($slug, $activeRootKey, $activeTree, $result['page'] - 1)) ?>">Previous</a><?php endif ?><span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span><?php if ($result['page'] < $result['pages']): ?><a data-explorer-link class="btn btn-ghost" rel="next" href="<?= e($browseUrl($slug, $activeRootKey, $activeTree, $result['page'] + 1)) ?>">Next</a><?php endif ?></nav><?php endif ?>
</div></section>
</div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
<script>
(() => {
    let controller;
    const reduce = matchMedia('(prefers-reduced-motion: reduce)');
    async function load(url, push) {
        const panel = document.getElementById('browseExplorerPanel');
        if (!panel) return;
        controller?.abort();
        const current = controller = new AbortController();
        panel.closest('.browse-explorer-shell')?.classList.add('is-loading');
        panel.setAttribute('aria-busy', 'true');
        try {
            const [response] = await Promise.all([fetch(url, {signal: current.signal, credentials: 'same-origin'}), new Promise(resolve => setTimeout(resolve, reduce.matches ? 0 : 200))]);
            const html = await response.text();
            if (current.signal.aborted) return;
            const copy = new DOMParser().parseFromString(html, 'text/html');
            const replacement = copy.getElementById('browseExplorerPanel');
            if (!response.ok || !replacement) throw new Error('Browse results unavailable');
            panel.innerHTML = replacement.innerHTML;
            panel.querySelectorAll('.rv').forEach(card => card.classList.add('in'));
            if (push) history.pushState(null, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') location.assign(url);
        } finally {
            if (controller === current) {
                panel.closest('.browse-explorer-shell')?.classList.remove('is-loading');
                panel.removeAttribute('aria-busy');
            }
        }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('#browseExplorerPanel a[data-explorer-link]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href, true);
    }, true);
    addEventListener('popstate', () => load(location.href, false));
})();
</script>
</body></html>
