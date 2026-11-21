<?php
require_once __DIR__ . '/../market.php';

$live = filter_listings(['sale', 'resale', 'project', 'rent'], ['available' => 1]);
$featured = array_merge(array_slice(array_values(array_filter($live,fn($l)=>$l['_listing_purpose']==='sale')),0,4),array_slice(array_values(array_filter($live,fn($l)=>$l['_listing_purpose']==='rent')),0,4));
$projects = array_values(projects());
$market = market_snapshot();
$kpi = $market;
$byProject = $market['project_month_counts'] ?? [];
$liveByProject = [];
foreach ($live as $l) if (!empty($l['project']) && is_string($l['project'])) $liveByProject[$l['project']] = ($liveByProject[$l['project']] ?? 0) + 1;
$agencyCount = \App\Libraries\PortalCatalogStore::connect()->authorizedAgencyCount();
$explorerFiltersByProject = [];
try {
  $explorerStore = \App\Libraries\PortalCatalogStore::connect();
  foreach ($projects as $project) {
    $slug = (string)($project['slug'] ?? '');
    if ($slug === '') continue;
    $state = $explorerStore->publicExplorerState($slug, '', '');
    $items = [];
    foreach (($state['roots'] ?? []) as $root) {
      if ($slug === 'zalmi-x') {
        $childState = $explorerStore->publicExplorerState($slug, (string)$root['key'], '');
        foreach (($childState['row'] ?? []) as $child) {
          $filter = [
            'project' => $slug,
            'source_id' => (string)$root['source_id'],
            'head_id' => (string)$root['head_id'],
            'path' => [
              ['id' => (string)$root['filter_id'], 'source_field' => (string)$root['source_field'], 'source_value' => (string)$root['value']],
              ['id' => (string)$child['id'], 'source_field' => (string)$child['source_field'], 'source_value' => (string)$child['value']],
            ],
          ];
          $search = \App\Services\PublicListingSearch::filters(['project' => $slug, 'page' => '1', 'per_page' => '12'], 'sale');
          $count = $explorerStore->publicSearch($search, [], false, $filter)['total'];
          $items[] = [
            'label' => (string)$child['label'],
            'logo' => (string)($child['logo'] ?? ''),
            'count' => (int)$count,
            'url' => 'project.php?'.http_build_query(['project' => $slug, 'layer2' => (string)$root['key'], 'tree' => (string)$child['id']], '', '&', PHP_QUERY_RFC3986),
          ];
        }
        continue;
      }
      $filter = [
        'project' => $slug,
        'source_id' => (string)$root['source_id'],
        'head_id' => (string)$root['head_id'],
        'kind' => (string)$root['kind'],
        'value' => (string)$root['value'],
      ];
      $search = \App\Services\PublicListingSearch::filters(['project' => $slug, 'page' => '1', 'per_page' => '12'], 'sale');
      $count = $explorerStore->publicSearch($search, [], false, $filter)['total'];
      $items[] = ['label' => (string)$root['label'], 'logo' => (string)($root['logo'] ?? ''), 'count' => (int)$count, 'url' => 'project.php?'.http_build_query(['project'=>$slug,'layer2'=>$root['key']],'','&',PHP_QUERY_RFC3986)];
    }
    $explorerFiltersByProject[$slug] = $items;
  }
} catch (\Throwable $error) {
  error_log('Homepage Explorer filter summary unavailable: '.get_class($error));
}
$catCount = function (string $c) use ($live) { return count(array_filter($live, fn($l) => $l['category'] === $c)); };
$cities = array_values(array_unique(array_filter(array_map(fn($p) => $p['city'] ?? '', $projects))));

/* ERP deals, last 30 Pakistan-local calendar days → existing sparkline. */
$days = $market['daily'];
$maxDay = max(1, max($days ?: [0]));
$pts = [];
$n = 0;
foreach ($days as $c) { $pts[] = round($n * (300 / 29), 1) . ',' . round(86 - ($c / $maxDay) * 74, 1); $n++; }
$spark = 'M' . implode(' L', $pts);
$sparkArea = $spark . ' L300,96 L0,96 Z';

/* stories: illustrative owner & buyer stories (initials only, no personal photos) */
$stories = [
  ['Hamza Tariq', 'Bought a plot', 'shalimar-smart-city', 'ssc-gate-render', 'The plot file, price and block were exactly as listed. The deal at the Shalimar office was completed the same week.', ['10 Marla plot', 'Sector II · Golf Avenue', 'Deal completed in 6 days'], '2 weeks ago'],
  ['Rabia Anwar', 'Bought a house', 'gulberg-city', 'gc-villas', 'I could see that the agency was authorized before I even called. That alone gave my family confidence.', ['5 Marla house', 'Gulberg City, Sargodha', 'Authorized agency'], '3 weeks ago'],
  ['Faisal Nadeem', 'Investor', 'shalimar-smart-city', 'ssc-business-district', 'The market report showed me last month’s actual resale prices in Sector II. I negotiated with real numbers.', ['2 commercial plots', 'Smart Business District', 'Priced from live data'], '1 month ago'],
  ['Ayesha Khan', 'Rented out her home', 'gulberg-city', 'gc-homes', 'Our rental was marked “Rented” the day the agreement was signed — no more calls for a house already taken.', ['3-bed home', 'Gulberg City', 'Rental listing example'], '1 month ago'],
  ['Usman Ghani', 'Booked an office', 'zalmi-x', 'zx-office', 'Floor plan, size and payment plan matched the listing exactly. Booking a founder office in Lahore took one visit.', ['Founder office · 865 sq ft', 'Zalmi X, Pine Avenue', 'LDA approved'], '6 weeks ago'],
  ['Sana Iqbal', 'Bought a plot', 'shalimar-smart-city', 'ssc-mosque', 'We wanted to live close to the Grand Jamia Masjid. Filtering by block showed us three verified plots in minutes.', ['7 Marla plot', 'Near Grand Jamia Masjid', 'Verified owner'], '2 months ago'],
];
$projName = [];
foreach ($projects as $p) $projName[$p['slug']] = $p['name'];
$storyJs = [];
foreach ($stories as $s) {
  $storyJs[] = ['name' => $s[0], 'ini' => project_initials($s[0]), 'role' => $s[1], 'project' => $projName[$s[2]] ?? '', 'slug' => $s[2],
                'img' => photo($s[3], true), 'quote' => $s[4], 'facts' => $s[5], 'when' => 'Illustrative example'];
}
$typeTiles = [
  ['plot', 'Plots', 'fa-solid fa-map-location-dot', 'properties.php?type=plot', 'Residential & commercial'],
  ['house', 'Houses & villas', 'fa-solid fa-house-chimney', 'properties.php?type=house', 'Ready to move in'],
  ['apartment', 'Apartments', 'fa-solid fa-building', 'properties.php?type=apartment', 'Gulberg City homes'],
  ['commercial', 'Commercial', 'fa-solid fa-briefcase', 'properties.php?type=commercial', 'Sale & rent listings'],
];
$commercialCount = count(array_filter($live, static function ($listing): bool {
  $category = strtolower(trim((string)($listing['category'] ?? '')));
  return in_array($category, ['commercial', 'office', 'shop', 'plaza', 'tower'], true)
    // Zalmi X corporate offices use the existing Apartment classification.
    || (($listing['project'] ?? '') === 'zalmi-x' && $category === 'apartment');
}));
$cardPos = ['c1', 'c2', 'c3', 'c4'];

/* section renderer: sec('name', [copy overrides]) */
function sec(string $name, array $o = []): void
{
    extract($GLOBALS, EXTR_SKIP);
    $T = function (string $k, string $def) use ($o) { return $o[$k] ?? $def; };
    include __DIR__ . '/sections/' . $name . '.php';
}
?>
