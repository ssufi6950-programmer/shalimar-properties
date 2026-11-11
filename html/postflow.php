<?php
/* Post-a-property flow helpers: verified poster's own property records ("holdings").
 * Real records can be supplied later in data/holdings.json.php as
 * { "SG-1001": [ {project, sector, block, plot_no, category, size, beds, baths}, ... ], ... }.
 * Until then demo records are generated deterministically from the membership code. */

function party_holdings(array $p): array
{
    $code = strtoupper((string)($p['code'] ?? ''));
    $projects = projects();
    $file = store_path('holdings');
    if (is_file($file)) {
        $all = store_read('holdings');
        if (isset($all[$code]) && is_array($all[$code])) {
            $out = [];
            foreach (array_values($all[$code]) as $i => $h) {
                $h['hid'] = $code . '-' . ($i + 1);
                $h['unit'] = $h['unit'] ?? project_unit($h['project'] ?? null);
                $h['demo'] = false;
                $out[] = $h;
            }
            return $out;
        }
    }
    $area = strtolower((string)($p['area'] ?? ''));
    $keys = array_keys($projects);
    $pick = [];
    foreach (['smart city' => 'shalimar-smart-city', 'golf' => 'shalimar-smart-city', 'gulberg' => 'gulberg-city', 'lari adda' => 'gulberg-city', 'zalmi' => 'zalmi-x', 'pine' => 'zalmi-x'] as $needle => $slug) {
        if (strpos($area, $needle) !== false && isset($projects[$slug]) && !in_array($slug, $pick, true)) $pick[] = $slug;
    }
    if (!$pick) $pick = $keys;
    $role = $p['role'] ?? 'owner';
    $n = $role === 'owner' ? 2 + (crc32($code) % 2) : 4;
    $seed = crc32($code);
    $r = function (int $max) use (&$seed): int { $seed = ($seed * 1103515245 + 12345) & 0x7fffffff; return $max > 0 ? $seed % $max : 0; };
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $slug = $pick[$i % count($pick)];
        $pr = $projects[$slug] ?? null;
        if (!$pr) continue;
        $secs = $pr['sectors'] ?? [];
        $sector = $secs ? array_keys($secs)[$r(count($secs))] : '';
        $blocks = $sector !== '' ? array_values($secs[$sector]) : [];
        $block = $blocks ? $blocks[$r(count($blocks))] : '';
        $unit = $pr['unit'] ?? 'marla';
        if ($slug === 'zalmi-x' || $unit === 'sqft') {
            $cat = ['office', 'commercial'][$r(2)];
            $size = $cat === 'office' ? [729, 860, 1050, 1265][$r(4)] : [4000, 4600, 5400][$r(3)];
            $no = 'Unit ' . (100 + $r(900)); $beds = 0; $baths = $cat === 'office' ? 1 : 2;
        } else {
            $cat = $slug === 'gulberg-city' ? ['plot', 'house', 'apartment'][$r(3)] : ['plot', 'plot', 'house', 'commercial'][$r(4)];
            if ($cat === 'apartment') { $size = [3, 4, 5][$r(3)]; $no = 'Apt ' . (1 + $r(12)) . '0' . (1 + $r(8)); $beds = 2 + $r(2); $baths = 2; }
            elseif ($cat === 'house') { $size = [5, 7, 10][$r(3)]; $no = 'House ' . (1 + $r(400)); $beds = 3 + $r(3); $baths = 3 + $r(2); }
            elseif ($cat === 'commercial') { $size = [4, 6, 8][$r(3)]; $no = 'Shop ' . (1 + $r(120)); $beds = 0; $baths = 1; }
            else { $size = [5, 7, 10, 20][$r(4)]; $no = 'Plot ' . (1 + $r(600)); $beds = 0; $baths = 0; }
        }
        $out[] = ['hid' => $code . '-' . ($i + 1), 'project' => $slug, 'sector' => $sector, 'block' => $block, 'plot_no' => $no,
                  'category' => $cat, 'size' => $size, 'unit' => $unit, 'beds' => $beds, 'baths' => $baths, 'demo' => true];
    }
    return $out;
}

function holding_title(array $h): string
{
    $where = trim(implode(', ', array_filter([$h['sector'] ?? '', $h['block'] ?? ''])));
    return size_text($h['size'], $h['unit'] ?? 'marla') . ' ' . category_label($h['category']) . ($where !== '' ? ' · ' . $where : '') . ' — ' . project_name($h['project'] ?? null);
}

function pp_state(): array { return $_SESSION['pp'] ?? []; }
function pp_set(array $s): void { $_SESSION['pp'] = $s; }
function pp_reset(): void { unset($_SESSION['pp']); }
