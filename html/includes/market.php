<?php
/** Authenticated ERP aggregate snapshot, plus legacy helpers for unrelated old modules. */
declare(strict_types=1);

/** The public homepage/report use this DB read model; legacy admin helpers below stay intact. */
function market_snapshot(array $filters = []): array
{
    static $snapshots = [];
    $key = json_encode($filters, JSON_THROW_ON_ERROR);
    if (isset($snapshots[$key])) return $snapshots[$key];
    try {
        return $snapshots[$key] = \App\Libraries\PortalCatalogStore::connect()->marketSnapshot($filters);
    } catch (\Throwable $error) {
        error_log('Market Intelligence aggregate read unavailable: '.get_class($error));
        return $snapshots[$key] = \App\Services\ErpMarketIntelligenceService::unavailable();
    }
}

function market_count(?int $value): string { return $value === null ? 'N/A' : number_format($value); }
function market_change(?float $value): string { return \App\Services\ErpMarketPeriods::change($value); }
function market_json($value): string { return json_encode($value,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); }

/** Public table projection: combine sources and kinds under the same displayed segment. */
function market_sale_rent_rows(array $rows): array
{
    $groups=[];
    foreach($rows as$row){
        $key=json_encode([$row['project_name'],$row['subhead_name'],$row['category']],JSON_THROW_ON_ERROR);
        if(!isset($groups[$key]))$groups[$key]=['project_name'=>$row['project_name'],'subhead_name'=>$row['subhead_name'],'category'=>$row['category'],'sale_deals'=>0,'rent_deals'=>0,'count'=>0];
        $column=match($row['deal_kind']){'sale','resale'=>'sale_deals','check_in'=>'rent_deals',default=>throw new \UnexpectedValueException('Unsupported market deal kind.')};
        $groups[$key][$column]+=$row['count'];$groups[$key]['count']+=$row['count'];
    }
    $groups=array_values($groups);
    usort($groups,static fn($a,$b)=>[$a['project_name'],$a['subhead_name'],$a['category']]<=>[$b['project_name'],$b['subhead_name'],$b['category']]);
    return $groups;
}

function market_tx(array $f = []): array
{
    return array_values(array_filter(store_read('transactions'), function ($t) use ($f) {
        if (!empty($f['project']) && $t['project'] !== $f['project']) return false;
        if (!empty($f['sector']) && $t['sector'] !== $f['sector']) return false;
        if (!empty($f['block']) && $t['block'] !== $f['block']) return false;
        if (!empty($f['type']) && $t['type'] !== $f['type']) return false;
        return true;
    }));
}

/** Transactions usable for price-per-unit stats (no rents; plots only for marla projects). */
function price_rows(array $tx): array
{
    return array_values(array_filter($tx, fn($t) => $t['type'] !== 'rent' && (float)$t['size'] > 0
        && ($t['unit'] === 'sqft' || $t['category'] === 'plot')));
}

function avg(array $a): float
{
    return $a ? array_sum($a) / count($a) : 0.0;
}

function pct_change(float $now, float $then): ?float
{
    return $then > 0 ? ($now - $then) / $then * 100 : null;
}

function market_daily(array $tx, int $days = 30): array
{
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    foreach ($tx as $t) if (isset($out[$t['date']])) $out[$t['date']]++;
    return $out;
}

function market_monthly(array $tx, int $months = 12): array
{
    $out = [];
    $base = new DateTimeImmutable('first day of this month');
    for ($i = $months - 1; $i >= 0; $i--) {
        $k = $base->modify("-{$i} months")->format('Y-m');
        $out[$k] = array_fill_keys(array_keys(TX_TYPES), 0);
    }
    foreach ($tx as $t) {
        $k = substr($t['date'], 0, 7);
        if (isset($out[$k][$t['type']])) $out[$k][$t['type']]++;
    }
    return $out;
}

function market_sizes(array $tx, int $limit = 8): array
{
    $c = [];
    foreach ($tx as $t) {
        if ($t['type'] === 'rent') continue;
        $label = size_text($t['size'], $t['unit']);
        $c[$label] = ($c[$label] ?? 0) + 1;
    }
    arsort($c);
    return array_slice($c, 0, $limit, true);
}

/** Current (last 90 days) vs ~1 year ago (320–410 days ago) average rate per unit, per project/sector/block. */
function market_prices(array $tx): array
{
    $now = time();
    $groups = [];
    foreach (price_rows($tx) as $t) {
        $age = (int)(($now - strtotime($t['date'])) / 86400);
        $k = $t['project'] . '|' . $t['sector'] . '|' . $t['block'];
        $rate = $t['price'] / (float)$t['size'];
        if (!isset($groups[$k])) $groups[$k] = ['project' => $t['project'], 'sector' => $t['sector'], 'block' => $t['block'], 'unit' => $t['unit'], 'now' => [], 'then' => [], 'n' => 0];
        if ($age <= 90) { $groups[$k]['now'][] = $rate; $groups[$k]['n']++; }
        elseif ($age >= 320 && $age <= 410) $groups[$k]['then'][] = $rate;
    }
    $rows = [];
    foreach ($groups as $g) {
        $n = avg($g['now']); $th = avg($g['then']);
        $rows[] = ['project' => $g['project'], 'sector' => $g['sector'], 'block' => $g['block'], 'unit' => $g['unit'],
                   'now' => $n, 'then' => $th, 'change' => pct_change($n, $th), 'deals' => $g['n']];
    }
    usort($rows, fn($a, $b) => [$a['project'], $a['sector'], $a['block']] <=> [$b['project'], $b['sector'], $b['block']]);
    return $rows;
}

function market_last_month(array $tx): array
{
    $from = date('Y-m-01', strtotime('first day of last month'));
    $to = date('Y-m-t', strtotime('first day of last month'));
    $rows = array_values(array_filter($tx, fn($t) => $t['date'] >= $from && $t['date'] <= $to));
    usort($rows, fn($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    return $rows;
}

function market_kpis(array $tx): array
{
    $today = date('Y-m-d');
    $m = date('Y-m');
    $lm = date('Y-m', strtotime('first day of last month'));
    $dayOfMonth = (int)date('j');
    $k = ['today' => 0, 'month' => 0, 'last_month' => 0, 'last_month_same' => 0, 'value30' => 0];
    $cut30 = date('Y-m-d', strtotime('-29 days'));
    foreach ($tx as $t) {
        if ($t['date'] === $today) $k['today']++;
        $tm = substr($t['date'], 0, 7);
        if ($tm === $m) $k['month']++;
        if ($tm === $lm) {
            $k['last_month']++;
            if ((int)substr($t['date'], 8, 2) <= $dayOfMonth) $k['last_month_same']++;
        }
        if ($t['date'] >= $cut30 && $t['type'] !== 'rent') $k['value30'] += $t['price'];
    }
    $changes = array_filter(array_column(market_prices($tx), 'change'), fn($v) => $v !== null);
    $k['yoy'] = $changes ? avg($changes) : null;
    $k['mtd_change'] = pct_change((float)$k['month'], (float)$k['last_month_same']);
    return $k;
}

function fmt_rate(float $rate, string $unit): string
{
    return $unit === 'sqft' ? 'PKR ' . number_format($rate) . ' / sq ft' : fmt_pkr($rate) . ' / marla';
}
