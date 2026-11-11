<?php
/* ============================================================================
 * PROJECT > CATEGORY > SUB-CATEGORY  —  the one file to edit.
 *
 *  'project-slug' => [
 *      'Category key' => [                       // key is stored on listings as "sector"
 *          'label' => 'Shown name',  'icon' => 'fa-…',
 *          'types' => ['plot','house',…],        // property types that exist in this category
 *          'url'   => 'official page (optional)',
 *          'subs'  => [ 'Sub key' => ['label' => 'Shown name', 'url' => '', 'confirm' => true], … ],
 *      ], …
 *  ]
 *  Keys must stay as they are once listings use them (labels can change freely).
 *  'confirm' => true marks an entry still to be confirmed by Shalimar Group.
 *  Categories / sub-categories added in Admin > Projects are merged in automatically.
 *
 *  Sources (checked Oct 2026):
 *   shalimarsmartcity.com — menu: Smart City (Sector-I), Golf Avenue (Sector-II), Business District
 *     Overview, Business Bay Tower (/sbd/bbt), Food Court (/sbd/fct); business-district.php lists
 *     Food Court, Shopping Bay, Realtors Bay, Business Bay.
 *   gulbergcity.com + jaageer.com (PRO-5, PRO-9) — Gulberg Apartment Homes (1 & 2 bed), Liberty Market
 *     (Gulberg Avenue), Mini Market (M1, M2), residential plots, villas.
 *   zalmidevelopments.com/zalmi-x — Flagship Commercial Outlets 4,000–5,400 sq ft,
 *     Future Founder Offices 729–1,265 sq ft.
 * ========================================================================== */

const SUBPROJECTS = [
    'shalimar-smart-city' => [
        'Residential' => [
            'label' => 'Residential', 'icon' => 'fa-house', 'types' => ['plot', 'house'],
            'url' => 'https://www.shalimarsmartcity.com/ssc_sector1.php',
            'subs' => [
                'Block A' => ['label' => 'Block A', 'confirm' => true],
                'Block B' => ['label' => 'Block B', 'confirm' => true],
                'Block C' => ['label' => 'Block C', 'confirm' => true],
                'Block D' => ['label' => 'Block D', 'confirm' => true],
                'Block E' => ['label' => 'Block E', 'confirm' => true],
                'Golf Avenue' => ['label' => 'Golf Avenue (Sector II)', 'url' => 'https://www.shalimarsmartcity.com/ssc_sector2.php'],
            ],
        ],
        // Voice note: "Smart City ke andar Business hai ... us ke blocks: Food Court hai, CBT hai, BBT hai"
        'Business District' => [
            'label' => 'Business', 'icon' => 'fa-briefcase', 'types' => ['commercial', 'office'],
            'url' => 'https://www.shalimarsmartcity.com/business-district.php',
            'subs' => [
                'Food Court' => ['label' => 'Food Court', 'url' => 'https://www.shalimarsmartcity.com/sbd/fct'],
                'CBD' => ['label' => 'CBT'],
                'Business Bay' => ['label' => 'BBT', 'url' => 'https://www.shalimarsmartcity.com/sbd/bbt'],
            ],
        ],
    ],
    'gulberg-city' => [
        // Voice note: "Gulberg City ke andar teen-chaar categories hain: Gulberg Apartment Homes, Liberty Market, Mini Market"
        'Apartment Homes' => [
            'label' => 'Gulberg Apartment Homes', 'icon' => 'fa-building', 'types' => ['apartment'],
            'url' => 'https://www.jaageer.com/project/9/gulberg-apartment-homes.html',
            'subs' => ['Apartment Homes' => ['label' => '1 & 2 Bed Apartments']],
        ],
        'Liberty Market' => [
            'label' => 'Liberty Market', 'icon' => 'fa-store', 'types' => ['commercial', 'office'],
            'subs' => ['Shops' => ['label' => 'Shops'], 'Offices' => ['label' => 'Offices']],
        ],
        'Mini Market' => [
            'label' => 'Mini Market', 'icon' => 'fa-shop', 'types' => ['commercial', 'apartment'],
            'subs' => ['Plots' => ['label' => 'Commercial plots'], 'Flats' => ['label' => 'Flats']],
        ],
        // not in the voice note; kept because existing listings/records use it
        'Residential' => [
            'label' => 'Residential', 'icon' => 'fa-house', 'types' => ['plot', 'house'], 'url' => 'https://gulbergcity.com/',
            'subs' => ['Awan Chowk' => ['label' => 'Awan Chowk'], 'Lari Adda Road' => ['label' => 'Lari Adda Road']],
        ],
        'Gulberg Villas' => [
            'label' => 'Gulberg Villas', 'icon' => 'fa-house-chimney', 'types' => ['house'],
            'subs' => ['Gulberg Villas' => ['label' => 'Villas']],
        ],
    ],
    'zalmi-x' => [
        'Ground & First Floor' => [
            'label' => 'Flagship Commercial Outlets', 'icon' => 'fa-store', 'types' => ['commercial'],
            'url' => 'https://zalmidevelopments.com/zalmi-x',
            'subs' => ['Flagship Outlets' => ['label' => 'Outlets · 4,000–5,400 sq ft']],
        ],
        'Floors 2–9' => [
            'label' => 'Future Founder Offices', 'icon' => 'fa-briefcase', 'types' => ['office'],
            'url' => 'https://zalmidevelopments.com/zalmi-x',
            'subs' => ['Founder Offices' => ['label' => 'Offices · 729–1,265 sq ft']],
        ],
    ],
];

/** Merge SUBPROJECTS into project rows: fills ['sectors'] (keys) and ['tree'] (labels, links). */
function apply_subprojects(array $rows): array
{
    foreach ($rows as $slug => &$p) {
        $data = is_array($p['sectors'] ?? null) ? $p['sectors'] : [];
        $def = SUBPROJECTS[$slug] ?? [];
        $tree = [];
        foreach ($def as $cat => $c) {
            $subs = [];
            foreach ($c['subs'] ?? [] as $s => $sd) $subs[$s] = $sd + ['label' => $s];
            foreach ($data[$cat] ?? [] as $s) if (!isset($subs[$s])) $subs[$s] = ['label' => $s];
            $tree[$cat] = ['label' => $c['label'] ?? $cat, 'icon' => $c['icon'] ?? 'fa-layer-group', 'types' => $c['types'] ?? [], 'url' => $c['url'] ?? '', 'subs' => $subs];
        }
        foreach ($data as $cat => $bl) {
            if (isset($tree[$cat])) continue;
            $subs = [];
            foreach ((array)$bl as $s) $subs[$s] = ['label' => $s];
            $tree[$cat] = ['label' => $cat, 'icon' => 'fa-layer-group', 'types' => [], 'url' => '', 'subs' => $subs];
        }
        $p['tree'] = $tree;
        $p['sectors'] = array_map(fn($c) => array_keys($c['subs']), $tree);
    }
    unset($p);
    return $rows;
}

function project_tree(?string $slug): array { $p = $slug ? (projects(true)[$slug] ?? null) : null; return $p['tree'] ?? []; }
function subproject_types(string $slug, string $cat): array { return SUBPROJECTS[$slug][$cat]['types'] ?? []; }
function cat_label(?string $slug, ?string $cat): string { return (string)(project_tree($slug)[$cat]['label'] ?? $cat); }
function sub_label(?string $slug, ?string $cat, ?string $sub): string { return (string)(project_tree($slug)[$cat]['subs'][$sub]['label'] ?? $sub); }
function sub_link(string $slug, string $cat = '', string $sub = ''): string
{
    return 'buy.php?' . http_build_query(array_filter(['project' => $slug, 'sector' => $cat, 'block' => $sub], fn($v) => $v !== ''));
}

/** Compact "sub-projects" block. $mode: 'full' (categories + sub links) or 'mini' (category links only). */
function subprojects_html(array $p, string $mode = 'full'): string
{
    $tree = $p['tree'] ?? [];
    if (!$tree) return '';
    $slug = (string)$p['slug'];
    $h = '<div class="subp subp-' . e($mode) . '">';
    if ($mode === 'mini') {
        foreach ($tree as $cat => $c) {
            $h .= '<a class="subp-cat" href="' . e(sub_link($slug, $cat)) . '"><i class="fa-solid ' . e($c['icon']) . '"></i>' . e($c['label']) . '<em>' . count($c['subs']) . '</em></a>';
        }
        return $h . '</div>';
    }
    foreach ($tree as $cat => $c) {
        $h .= '<div class="subp-row"><a class="subp-cat" href="' . e(sub_link($slug, $cat)) . '"><i class="fa-solid ' . e($c['icon']) . '"></i>' . e($c['label']) . '</a><span class="subp-subs">';
        foreach ($c['subs'] as $s => $sd) {
            $h .= '<a href="' . e(sub_link($slug, $cat, (string)$s)) . '"' . (!empty($sd['confirm']) ? ' class="tbc" title="To be confirmed"' : '') . '>' . e($sd['label']) . '</a>';
        }
        $h .= '</span>';
        if ($c['url'] !== '') $h .= '<a class="subp-off" href="' . e($c['url']) . '" target="_blank" rel="noopener" title="Official page"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
        $h .= '</div>';
    }
    return $h . '</div>';
}
