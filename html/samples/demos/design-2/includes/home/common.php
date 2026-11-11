<?php
require_once __DIR__ . '/../market.php';

$live = filter_listings(['sale', 'resale', 'project', 'rent'], ['available' => 1]);
$featured = array_slice($live, 0, 8);
$projects = array_values(projects());
$tx = market_tx();
$kpi = market_kpis($tx);
$byProject = [];
$liveByProject = [];
foreach ($tx as $t) if (substr($t['date'], 0, 7) === date('Y-m')) $byProject[$t['project']] = ($byProject[$t['project']] ?? 0) + 1;
foreach ($live as $l) if (!empty($l['project']) && is_string($l['project'])) $liveByProject[$l['project']] = ($liveByProject[$l['project']] ?? 0) + 1;
$agencyCount = count(array_filter(store_read('parties'), fn($p) => $p['role'] === 'agency' && !empty($p['active'])));
$catCount = function (string $c) use ($live) { return count(array_filter($live, fn($l) => $l['category'] === $c)); };
$cities = array_values(array_unique(array_filter(array_map(fn($p) => $p['city'] ?? '', $projects))));

/* daily deals, last 30 days → sparkline */
$days = [];
for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach ($tx as $t) if (isset($days[$t['date']])) $days[$t['date']]++;
$maxDay = max(1, max($days));
$pts = [];
$n = 0;
foreach ($days as $c) { $pts[] = round($n * (300 / 29), 1) . ',' . round(86 - ($c / $maxDay) * 74, 1); $n++; }
$spark = 'M' . implode(' L', $pts);
$sparkArea = $spark . ' L300,96 L0,96 Z';

/* stories: illustrative owner & buyer stories (initials only, no personal photos) */
$stories = [
  ['Hamza Tariq', 'Bought a plot', 'shalimar-smart-city', 'ssc-gate-render', 'The plot file, price and block were exactly as listed. Transfer at the Shalimar office was done the same week.', ['10 Marla plot', 'Sector II · Golf Avenue', 'Transferred in 6 days'], '2 weeks ago'],
  ['Rabia Anwar', 'Bought a house', 'gulberg-city', 'gc-villas', 'I could see that the agency was authorized before I even called. That alone gave my family confidence.', ['5 Marla house', 'Gulberg City, Sargodha', 'Authorized agency'], '3 weeks ago'],
  ['Faisal Nadeem', 'Investor', 'shalimar-smart-city', 'ssc-business-district', 'The market report showed me last month’s actual resale prices in Sector II. I negotiated with real numbers.', ['2 commercial plots', 'Smart Business District', 'Priced from live data'], '1 month ago'],
  ['Ayesha Khan', 'Rented out her home', 'gulberg-city', 'gc-homes', 'Our rental was marked “Rented” the day the agreement was signed — no more calls for a house already taken.', ['3-bed home', 'Gulberg City', 'Rented in 9 days'], '1 month ago'],
  ['Usman Ghani', 'Booked an office', 'zalmi-x', 'zx-office', 'Floor plan, size and payment plan matched the listing exactly. Booking a founder office in Lahore took one visit.', ['Founder office · 865 sq ft', 'Zalmi X, Pine Avenue', 'LDA approved'], '6 weeks ago'],
  ['Sana Iqbal', 'Bought a plot', 'shalimar-smart-city', 'ssc-mosque', 'We wanted to live close to the Grand Jamia Masjid. Filtering by block showed us three verified plots in minutes.', ['7 Marla plot', 'Near Grand Jamia Masjid', 'Verified owner'], '2 months ago'],
];
$projName = [];
foreach ($projects as $p) $projName[$p['slug']] = $p['name'];
$storyJs = [];
foreach ($stories as $s) {
  $storyJs[] = ['name' => $s[0], 'ini' => project_initials($s[0]), 'role' => $s[1], 'project' => $projName[$s[2]] ?? '', 'slug' => $s[2],
                'img' => photo($s[3], true), 'quote' => $s[4], 'facts' => $s[5], 'when' => $s[6]];
}
$typeTiles = [
  ['plot', 'Plots', 'fa-solid fa-map-location-dot', 'buy.php?type=plot', 'Residential & commercial'],
  ['house', 'Houses & villas', 'fa-solid fa-house-chimney', 'buy.php?type=house', 'Ready to move in'],
  ['apartment', 'Apartments', 'fa-solid fa-building', 'buy.php?type=apartment', 'Gulberg City homes'],
  ['office', 'Offices & shops', 'fa-solid fa-briefcase', 'projects.php?project=zalmi-x#units', 'Zalmi X, Lahore'],
];
$cardPos = ['c1', 'c2', 'c3', 'c4'];

/* section renderer: sec('name', [copy overrides]) */
function sec(string $name, array $o = []): void
{
    extract($GLOBALS, EXTR_SKIP);
    $T = function (string $k, string $def) use ($o) { return $o[$k] ?? $def; };
    include __DIR__ . '/sections/' . $name . '.php';
}
?>
