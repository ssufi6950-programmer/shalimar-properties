<?php
/**
 * First-run demo data. Creates any missing data file in /data.
 * Listing titles, sizes, prices and localities marked "jaageer.com #ID" were taken
 * from public listings on https://www.jaageer.com (fetched 9 Oct 2026).
 * Person/agency names and phone numbers are FICTIONAL demo values.
 * Transactions are SAMPLE data generated around jaageer.com price levels.
 */
declare(strict_types=1);

function seed_missing(bool $force = false): void
{
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    $sets = ['projects' => 'seed_projects', 'settings' => 'seed_settings', 'parties' => 'seed_parties',
             'listings' => 'seed_listings', 'transactions' => 'seed_transactions', 'records' => 'seed_records'];
    foreach ($sets as $name => $fn) {
        if ($force || !is_file(store_path($name))) {
            store_write($name, $fn());
        }
    }
}

function seed_projects(): array
{
    return [
        'shalimar-smart-city' => [
            'slug' => 'shalimar-smart-city', 'order' => 1, 'active' => true,
            'name' => 'Shalimar Smart City', 'city' => 'Sargodha', 'location' => '4-KM, Main Mall Road, Sargodha',
            'developer' => 'Shalimar Developers', 'kind' => 'Residential plots', 'status' => 'Under development',
            'progress' => 55, 'unit' => 'marla', 'price_from' => 'PKR 32 Lac (5 Marla, Sector II)',
            'summary' => 'Master-planned smart community on Mall Road with planning support from Singapore-based Meinhardt — smart school, women university, health facilities and farm-house enclave.',
            'logo' => 'assets/images/logos/ssc-logo.svg', 'cover' => 'assets/images/photos/ssc-aerial.webp',
            'sectors' => ['Sector I' => ['Block A', 'Block B', 'Block C'], 'Sector II' => ['Golf Avenue', 'Block D', 'Block E']],
            'source' => 'Project facts: jaageer.com (PRO-26). Block names A–E are demo.',
        ],
        'gulberg-city' => [
            'slug' => 'gulberg-city', 'order' => 2, 'active' => true,
            'name' => 'Gulberg City', 'city' => 'Sargodha', 'location' => '1-KM Qenchi Morr, Lari Adda Road, Sargodha',
            'developer' => 'Shalimar Developers', 'kind' => 'Plots, villas & apartments', 'status' => 'Approved · 300+ families living',
            'progress' => 100, 'unit' => 'marla', 'price_from' => 'PKR 90 Lac (2-bed apartment)',
            'summary' => 'Metropolitan Corporation approved community with Grand Jamia Masjid, community centre, commercial area, parks, 24/7 CCTV and a boundary wall.',
            'logo' => 'assets/images/logos/gulberg-city-logo.svg', 'cover' => 'assets/images/photos/gc-apartments.webp',
            'sectors' => ['Residential' => ['Awan Chowk', 'Lari Adda Road', 'Mini Market'], 'Villas & Apartments' => ['Gulberg Villas', 'Apartment Homes']],
            'source' => 'Project facts: jaageer.com (PRO-5).',
        ],
        'zalmi-x' => [
            'slug' => 'zalmi-x', 'order' => 3, 'active' => true,
            'name' => 'Zalmi X', 'city' => 'Lahore', 'location' => 'Main Pine Avenue Road, Lahore',
            'developer' => 'Zalmi Developments (JV: JW-Corporation & Shalimar Group)', 'kind' => 'Commercial tower', 'status' => 'LDA approved',
            'progress' => 35, 'unit' => 'sqft', 'price_from' => 'PKR 30,000 / sq ft',
            'summary' => 'Premium high-rise with flagship commercial outlets (4,000–5,400 sq ft) and Future Founder Offices (729–1,265 sq ft) up to the 9th floor.',
            'logo' => 'assets/images/logos/zalmi-x.png', 'cover' => 'assets/images/photos/zx-night.webp',
            'sectors' => ['Ground & First Floor' => ['Flagship Outlets'], 'Floors 2–9' => ['Founder Offices']],
            'source' => 'Project facts: jaageer.com (PRO-112).',
        ],
    ];
}

function seed_settings(): array
{
    return ['roles' => [
        'developer' => ['label' => 'Shalimar Group (official)', 'mode' => 'live', 'badge' => 'Official Shalimar', 'system' => false],
        'owner'     => ['label' => 'Shalimar Group customer (plot / file owner)', 'mode' => 'live', 'badge' => 'Verified Owner', 'system' => true],
        'agency'    => ['label' => 'Authorized realtor / agency', 'mode' => 'live_logged', 'badge' => 'Authorized Agency', 'system' => true],
        'public'    => ['label' => 'Other / third party', 'mode' => 'review', 'badge' => '', 'system' => true],
    ]];
}

function seed_parties(): array
{
    $now = date('Y-m-d');
    $rows = [
        ['developer', 'Shalimar Developers (Official)', 'SG-DEV', '0304-1112627', 'Sargodha', 'All Shalimar projects', 0, 0],
        ['owner', 'Ahmad Raza', 'SG-1001', '0300-1112233', 'Sargodha', 'Shalimar Smart City · Sector I', 0, 0],
        ['owner', 'Sana Iqbal', 'SG-1002', '0301-2223344', 'Sargodha', 'Gulberg City · Awan Chowk', 0, 0],
        ['owner', 'Bilal Awan', 'SG-1003', '0302-3334455', 'Lahore', 'Zalmi X · Founder Offices', 0, 0],
        ['owner', 'Hira Malik', 'SG-1004', '0303-4445566', 'Sargodha', 'Shalimar Smart City · Sector II', 0, 0],
        ['agency', 'Mall Road Estate Advisors', 'AG-201', '0311-1000201', 'Sargodha', 'Mall Road & Shalimar Smart City', 4.9, 128],
        ['agency', 'Smart City Realtors', 'AG-202', '0311-1000202', 'Sargodha', 'Shalimar Smart City · Sector I & II', 4.8, 96],
        ['agency', 'Gulberg Property Hub', 'AG-203', '0311-1000203', 'Sargodha', 'Gulberg City & Lari Adda Road', 4.8, 112],
        ['agency', 'Pine Avenue Associates', 'AG-204', '0311-1000204', 'Lahore', 'Zalmi X & Pine Avenue', 4.7, 61],
        ['agency', 'Golf Avenue Estates', 'AG-205', '0311-1000205', 'Sargodha', 'Golf Avenue · Sector II', 4.6, 54],
        ['agency', 'Sargodha Land Links', 'AG-206', '0311-1000206', 'Sargodha', 'Rentals across Sargodha', 4.7, 89],
    ];
    $out = [];
    foreach ($rows as $i => $r) {
        $out[] = ['id' => $i + 1, 'role' => $r[0], 'name' => $r[1], 'code' => $r[2], 'phone' => $r[3], 'city' => $r[4],
                  'area' => $r[5], 'rating' => $r[6], 'deals' => $r[7], 'active' => true, 'created_at' => $now, 'demo' => true];
    }
    return $out;
}

function seed_listings(): array
{
    $parties = [];
    foreach (seed_parties() as $p) $parties[$p['code']] = $p;
    // [source, title, purpose, category, project, sector, block, city, location, price, size, unit, beds, baths, deal, poster code|'' for public, status]
    $L = [
        ['64328', '5 Marla Residential Plot File – Phase 1, Sector I', 'resale', 'plot', 'shalimar-smart-city', 'Sector I', 'Block A', 'Sargodha', '', 5250000, 5, 'marla', 0, 0, 'available', 'AG-202', 'live'],
        ['64329', '7 Marla Residential Plot File, Mall Road – Sector I', 'resale', 'plot', 'shalimar-smart-city', 'Sector I', 'Block B', 'Sargodha', '', 8050000, 7, 'marla', 0, 0, 'available', 'AG-202', 'live'],
        ['64330', '10 Marla Residential Plot File – Phase 1, Sector I', 'resale', 'plot', 'shalimar-smart-city', 'Sector I', 'Block C', 'Sargodha', '', 12000000, 10, 'marla', 0, 0, 'available', 'SG-1001', 'live'],
        ['64331', '1 Kanal Residential Plot – Sector I', 'resale', 'plot', 'shalimar-smart-city', 'Sector I', 'Block A', 'Sargodha', '', 25000000, 20, 'marla', 0, 0, 'available', 'AG-201', 'live'],
        ['66046', '5 Marla Residential Plot – Sector II, Golf Avenue', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Golf Avenue', 'Sargodha', '', 3200000, 5, 'marla', 0, 0, 'available', 'AG-205', 'live'],
        ['66047', '7 Marla Residential Plot – Sector II, Golf Avenue', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Golf Avenue', 'Sargodha', '', 4500000, 7, 'marla', 0, 0, 'available', 'SG-1004', 'live'],
        ['66048', '10 Marla Residential Plot – Sector II, Golf Avenue', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Golf Avenue', 'Sargodha', '', 6000000, 10, 'marla', 0, 0, 'available', 'AG-205', 'live'],
        ['66049', '1 Kanal Residential Plot – Sector II', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Block D', 'Sargodha', '', 11800000, 20, 'marla', 0, 0, 'available', 'AG-201', 'live'],
        ['66736', '7 Marla Residential Plot – Sector I', 'resale', 'plot', 'shalimar-smart-city', 'Sector I', 'Block B', 'Sargodha', '', 8000000, 7, 'marla', 0, 0, 'sold', 'AG-202', 'live'],
        ['66437', '1 Kanal Residential Plot – Sector II', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Block E', 'Sargodha', '', 12000000, 20, 'marla', 0, 0, 'available', 'SG-1004', 'live'],
        ['66878', '8.84 Marla Residential Plot in Gulberg City', 'resale', 'plot', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 17500000, 8.84, 'marla', 0, 0, 'available', 'AG-203', 'live'],
        ['66879', '11.58 Marla Residential Plot in Gulberg City', 'resale', 'plot', 'gulberg-city', 'Residential', 'Lari Adda Road', 'Sargodha', '', 21700000, 11.58, 'marla', 0, 0, 'available', 'AG-203', 'live'],
        ['66880', '6.84 Marla Residential Plot in Gulberg City', 'resale', 'plot', 'gulberg-city', 'Residential', 'Mini Market', 'Sargodha', '', 12100000, 6.84, 'marla', 0, 0, 'available', 'SG-1002', 'live'],
        ['66881', '7.28 Marla Residential Plot in Gulberg City', 'resale', 'plot', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 13600000, 7.28, 'marla', 0, 0, 'sold', 'AG-203', 'live'],
        ['65838', '5 Marla Double Storey House in Gulberg City', 'sale', 'house', 'gulberg-city', 'Villas & Apartments', 'Gulberg Villas', 'Sargodha', '', 26500000, 5, 'marla', 4, 4, 'available', 'SG-DEV', 'live'],
        ['62832', '3.5 Marla Villa House in Gulberg City', 'sale', 'house', 'gulberg-city', 'Villas & Apartments', 'Gulberg Villas', 'Sargodha', '', 11000000, 3.5, 'marla', 2, 3, 'available', 'SG-DEV', 'live'],
        ['62662', 'Two Bed Semi-Furnished Apartment in Gulberg City', 'sale', 'apartment', 'gulberg-city', 'Villas & Apartments', 'Apartment Homes', 'Sargodha', '', 9200000, 4.25, 'marla', 2, 2, 'available', 'SG-DEV', 'live'],
        ['66997', '8 Marla Double Storey House in Gulberg City', 'resale', 'house', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 39000000, 8, 'marla', 5, 5, 'available', 'AG-203', 'live'],
        ['66917', '3.68 Marla Corner House, Awan Chowk', 'resale', 'house', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 12500000, 3.68, 'marla', 2, 2, 'available', 'SG-1002', 'live'],
        ['66864', '7 Marla House in Gulberg City, Awan Chowk', 'resale', 'house', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 32500000, 7, 'marla', 5, 5, 'sold', 'AG-203', 'live'],
        ['67143', '7.25 Marla House for Rent, Lari Adda Road', 'rent', 'house', 'gulberg-city', 'Residential', 'Lari Adda Road', 'Sargodha', '', 130000, 7.25, 'marla', 6, 6, 'available', 'AG-206', 'live'],
        ['67097', '300 Sq ft Flat for Rent, Mini Market', 'rent', 'apartment', 'gulberg-city', 'Residential', 'Mini Market', 'Sargodha', '', 16000, 1.33, 'marla', 1, 1, 'rented', 'AG-206', 'live'],
        ['66282', '5 Marla Upper Portion for Rent in Gulberg City', 'rent', 'house', 'gulberg-city', 'Residential', 'Awan Chowk', 'Sargodha', '', 45000, 5, 'marla', 2, 2, 'available', 'SG-1002', 'live'],
        ['67155', '7 Marla House for Rent in Cantt Villas', 'rent', 'house', null, '', '', 'Sargodha', 'Cantt Villas', 90000, 7, 'marla', 6, 6, 'available', 'AG-206', 'live'],
        ['67168', '5.5 Marla House for Rent in Chatha Town', 'rent', 'house', null, '', '', 'Sargodha', 'Chatha Town', 55000, 5.5, 'marla', 4, 5, 'available', 'AG-206', 'live'],
        ['67169', '6 Marla Furnished Lower Portion, Waqar Town', 'rent', 'house', null, '', '', 'Sargodha', 'Waqar Town', 55000, 6, 'marla', 2, 3, 'available', 'AG-201', 'live'],
        ['67115', '13 Marla Upper Portion for Rent, Shareef Garden', 'rent', 'house', null, '', '', 'Sargodha', 'Shareef Garden', 68000, 13, 'marla', 3, 3, 'rented', 'AG-206', 'live'],
        ['demo', 'Future Founder Office – 729 sq ft', 'project', 'office', 'zalmi-x', 'Floors 2–9', 'Founder Offices', 'Lahore', '', 21870000, 729, 'sqft', 0, 1, 'available', 'SG-DEV', 'live'],
        ['demo', 'Future Founder Office – 1,265 sq ft', 'project', 'office', 'zalmi-x', 'Floors 2–9', 'Founder Offices', 'Lahore', '', 37950000, 1265, 'sqft', 0, 2, 'available', 'SG-DEV', 'live'],
        ['demo', 'Flagship Commercial Outlet – 4,000 sq ft', 'project', 'commercial', 'zalmi-x', 'Ground & First Floor', 'Flagship Outlets', 'Lahore', '', 120000000, 4000, 'sqft', 0, 2, 'available', 'SG-DEV', 'live'],
        ['demo', 'Founder Office – 980 sq ft (resale)', 'resale', 'office', 'zalmi-x', 'Floors 2–9', 'Founder Offices', 'Lahore', '', 30400000, 980, 'sqft', 0, 1, 'sold', 'SG-1003', 'live'],
        ['demo', '5 Marla Plot – New Booking, Sector II', 'project', 'plot', 'shalimar-smart-city', 'Sector II', 'Block D', 'Sargodha', '', 3200000, 5, 'marla', 0, 0, 'available', 'SG-DEV', 'live'],
        ['demo', '10 Marla Plot – New Booking, Sector II', 'project', 'plot', 'shalimar-smart-city', 'Sector II', 'Block E', 'Sargodha', '', 6000000, 10, 'marla', 0, 0, 'available', 'SG-DEV', 'live'],
        ['63015', '2 Bed Apartment – Gulberg Apartment Homes', 'project', 'apartment', 'gulberg-city', 'Villas & Apartments', 'Apartment Homes', 'Sargodha', '', 10000000, 3.47, 'marla', 2, 2, 'available', 'SG-DEV', 'live'],
        // Pending submissions from unverified third parties (waiting for admin approval)
        ['demo', '10 Marla Plot, Smart City Sector II (third-party)', 'resale', 'plot', 'shalimar-smart-city', 'Sector II', 'Block D', 'Sargodha', '', 6200000, 10, 'marla', 0, 0, 'available', '', 'pending'],
        ['67088', '5 Marla House for Rent in Roshaan Homes', 'rent', 'house', null, '', '', 'Sargodha', 'Roshaan Homes', 55000, 5, 'marla', 5, 6, 'available', '', 'pending'],
        ['66924', '6.84 Marla Plot in Gulberg City (third-party)', 'resale', 'plot', 'gulberg-city', 'Residential', 'Mini Market', 'Sargodha', '', 12000000, 6.84, 'marla', 0, 0, 'available', '', 'pending'],
    ];
    $publicNames = ['Imran Saleem', 'Kashif Mehmood', 'Nadia Pervaiz'];
    $out = [];
    $n = count($L);
    foreach ($L as $i => $r) {
        $id = $i + 1;
        $code = $r[15];
        $p = $code !== '' ? ($parties[$code] ?? null) : null;
        $poster = $p
            ? ['role' => $p['role'], 'party_id' => $p['id'], 'name' => $p['name'], 'phone' => $p['phone'], 'verified' => true]
            : ['role' => 'public', 'party_id' => null, 'name' => $publicNames[$i % 3], 'phone' => '0345-55500' . str_pad((string)$id, 2, '0', STR_PAD_LEFT), 'verified' => false];
        $daysAgo = (int)round(($n - $i) * 0.9);
        $ts = date('Y-m-d H:i', strtotime("-{$daysAgo} days 10:00") + $i * 1700);
        $variant = ($id % 2) ? '' : '-2';
        $art = ['plot' => "assets/images/listing-plot{$variant}.svg", 'house' => "assets/images/listing-house{$variant}.svg",
                'apartment' => 'assets/images/listing-apartment.svg', 'office' => 'assets/images/listing-office.svg',
                'commercial' => 'assets/images/listing-commercial.svg'][$r[3]] ?? 'assets/images/listing-house.svg';
        $out[] = [
            'id' => $id, 'ref' => 'SP-' . (1000 + $id), 'title' => $r[1], 'purpose' => $r[2], 'category' => $r[3],
            'project' => $r[4], 'sector' => $r[5], 'block' => $r[6], 'city' => $r[7], 'location' => $r[8],
            'price' => $r[9], 'size' => $r[10], 'unit' => $r[11], 'beds' => $r[12], 'baths' => $r[13],
            'description' => seed_desc($r), 'images' => [$art],
            'poster' => $poster, 'status' => $r[16], 'deal' => $r[14],
            'created_at' => $ts, 'published_at' => $r[16] === 'live' ? $ts : null, 'review_note' => '',
            'source' => $r[0] === 'demo' ? 'Demo listing' : 'jaageer.com #' . $r[0],
        ];
    }
    return $out;
}

function seed_desc(array $r): string
{
    if ($r[3] === 'plot') {
        return 'Well-located plot with clear documentation. Electricity, double-sided sewerage, 24/7 sweet water, parks, masjid and market nearby. Contact for site visit and transfer details.';
    }
    if ($r[3] === 'office' || $r[3] === 'commercial') {
        return 'Premium commercial space in an LDA-approved tower on Main Pine Avenue Road with flexible payment plans and strong rental potential.';
    }
    if ($r[2] === 'rent') {
        return 'Neat and clean property available for rent with electricity, gas and water connections. Suitable for families. Advance and security as per agreement.';
    }
    return 'Beautifully built home with modern finishes, car porch, TV lounge and drawing room. Located in a secure, gated community with parks and a masjid within walking distance.';
}

/**
 * ~14 months of SAMPLE transactions for the market-insights page.
 * Price levels are anchored to jaageer.com asking prices (Oct 2026).
 */
function seed_transactions(): array
{
    mt_srand(20261009);
    $proj = [
        'shalimar-smart-city' => [
            'unit' => 'marla', 'daily' => [1, 4],
            'types' => ['sale' => 25, 'resale' => 35, 'transfer' => 40],
            'blocks' => [ // [sector, block, current rate per marla, yearly growth]
                ['Sector I', 'Block A', 1150000, .20], ['Sector I', 'Block B', 1130000, .22], ['Sector I', 'Block C', 1180000, .18],
                ['Sector II', 'Golf Avenue', 640000, .16], ['Sector II', 'Block D', 600000, .14], ['Sector II', 'Block E', 590000, .12],
            ],
            'sizes' => [5 => 35, 7 => 20, 10 => 28, 20 => 17], 'cats' => ['plot' => 100],
        ],
        'gulberg-city' => [
            'unit' => 'marla', 'daily' => [1, 3],
            'types' => ['resale' => 45, 'transfer' => 30, 'rent' => 25],
            'blocks' => [
                ['Residential', 'Awan Chowk', 1880000, .11], ['Residential', 'Lari Adda Road', 1860000, .10], ['Residential', 'Mini Market', 1770000, .09],
                ['Villas & Apartments', 'Gulberg Villas', 1900000, .08], ['Villas & Apartments', 'Apartment Homes', 2600000, .07],
            ],
            'sizes' => ['3.5' => 12, 5 => 25, '6.84' => 18, '7.28' => 18, '8.84' => 15, '11.58' => 12], 'cats' => ['plot' => 60, 'house' => 40],
        ],
        'zalmi-x' => [
            'unit' => 'sqft', 'daily' => [0, 1], 'prob' => .35,
            'types' => ['sale' => 70, 'resale' => 20, 'rent' => 10],
            'blocks' => [['Floors 2–9', 'Founder Offices', 30000, .18], ['Ground & First Floor', 'Flagship Outlets', 34000, .15]],
            'sizes' => [729 => 25, 850 => 20, 980 => 20, 1120 => 15, 1265 => 12, 4000 => 5, 5400 => 3], 'cats' => ['office' => 100],
        ],
    ];
    $pick = function (array $weights) {
        $sum = array_sum($weights);
        $r = mt_rand(1, (int)$sum);
        foreach ($weights as $k => $w) {
            $r -= $w;
            if ($r <= 0) return $k;
        }
        return array_key_first($weights);
    };
    $rows = [];
    $id = 1;
    $today = new DateTimeImmutable('today');
    for ($d = 420; $d >= 0; $d--) {
        $date = $today->modify("-{$d} days");
        $dow = (int)$date->format('N');
        foreach ($proj as $slug => $cfg) {
            if (isset($cfg['prob']) && mt_rand() / mt_getrandmax() > $cfg['prob']) continue;
            [$lo, $hi] = $cfg['daily'];
            $count = mt_rand($lo, $hi) + ($d < 120 && mt_rand(0, 3) === 0 ? 1 : 0);
            if ($dow === 7) $count = (int)floor($count / 2);
            for ($i = 0; $i < $count; $i++) {
                $type = $pick($cfg['types']);
                $size = (float)$pick($cfg['sizes']);
                if ($slug === 'zalmi-x') {
                    $b = $size >= 4000 ? $cfg['blocks'][1] : $cfg['blocks'][0];
                    $cat = $size >= 4000 ? 'commercial' : 'office';
                } else {
                    $b = $cfg['blocks'][mt_rand(0, count($cfg['blocks']) - 1)];
                    $cat = $pick($cfg['cats']);
                    if ($b[1] === 'Apartment Homes') { $cat = 'apartment'; $size = [3.47, 4.25, 4.5][mt_rand(0, 2)]; }
                    elseif ($b[1] === 'Gulberg Villas') { $cat = 'house'; }
                    if ($type === 'rent' && $cat === 'plot') $cat = 'house';
                }
                $years = -$d / 365;
                $rate = $b[2] * pow(1 + $b[3], $years) * (1 + (mt_rand(-50, 50) / 1000));
                if ($type === 'rent') {
                    $price = $slug === 'zalmi-x' ? $size * mt_rand(170, 210) : $size * mt_rand(9000, 18000);
                    $price = round($price / 1000) * 1000;
                } else {
                    $mult = $cat === 'house' ? 1.65 : 1.0;
                    $price = round($rate * $size * $mult / 10000) * 10000;
                }
                $rows[] = [
                    'id' => $id++, 'date' => $date->format('Y-m-d'), 'project' => $slug, 'sector' => $b[0], 'block' => $b[1],
                    'size' => $size, 'unit' => $cfg['unit'], 'category' => $cat, 'type' => $type, 'price' => (int)$price,
                    'listing_id' => null, 'note' => '', 'source' => 'sample',
                ];
            }
        }
    }
    return $rows;
}

function seed_records(): array
{
    return [[
        'id' => 1, 'ts' => date('Y-m-d H:i'), 'actor' => 'system', 'action' => 'Demo data created',
        'listing_id' => null, 'details' => 'Sample listings (from jaageer.com), demo owners/agencies and sample transactions were generated.',
    ]];
}
