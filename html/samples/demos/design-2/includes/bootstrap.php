<?php
/**
 * Shared bootstrap: config, session, helpers and the small file-based data store.
 * Data lives in /data/*.json.php (each file starts with a PHP exit guard so it
 * can never be downloaded directly, even on servers that ignore .htaccess).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('shalimar_demo_2');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ------------------------------------------------------------------ helpers */

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals(csrf_token(), $_POST['csrf']);
}

/** Set a flash message (with args) or read+clear it (without args). */
function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function post_str(string $key, int $max = 500): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function get_str(string $key, int $max = 120): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

/** PKR formatter: 1.25 cr / 85 lac / 95,000 */
function fmt_pkr($n, bool $prefix = true): string
{
    $n = (float)$n;
    if ($n >= 1e7) {
        $s = rtrim(rtrim(number_format($n / 1e7, 2), '0'), '.') . ' Crore';
    } elseif ($n >= 1e5) {
        $s = rtrim(rtrim(number_format($n / 1e5, 2), '0'), '.') . ' Lac';
    } else {
        $s = number_format($n);
    }
    return ($prefix ? 'PKR ' : '') . $s;
}

function size_label($marla): string
{
    $m = (float)$marla;
    if ($m <= 0) return '—';
    if ($m >= 20 && fmod($m, 20) == 0.0) {
        return (int)($m / 20) . ' Kanal';
    }
    return rtrim(rtrim(number_format($m, 2), '0'), '.') . ' Marla';
}

function size_text($size, string $unit = 'marla'): string
{
    if ((float)$size <= 0) return '—';
    return $unit === 'sqft' ? number_format((float)$size) . ' sq ft' : size_label($size);
}

/* ------------------------------------------------------------ imagery
 * Photography & logos come from the client's own project websites
 * (shalimarsmartcity.com, gulbergcity.com, zalmidevelopments.com and the
 * group portal jaageer.com), resized to WebP in assets/images/photos.
 */
const PHOTO_DIR = 'assets/images/photos/';

/** Path of a bundled photo; $large picks the ~1600px version when it exists. */
function photo(string $name, bool $large = false): string
{
    $big = PHOTO_DIR . $name . '.webp';
    if ($large && is_file(__DIR__ . '/../' . $big)) return $big;
    return PHOTO_DIR . $name . '-sm.webp';
}

/** Old illustration paths (v1 data) → new photography / official logos. */
const LEGACY_ART = [
    'assets/images/projects/smart-city.svg'   => 'assets/images/photos/ssc-aerial.webp',
    'assets/images/projects/gulberg-city.svg' => 'assets/images/photos/gc-apartments.webp',
    'assets/images/projects/zalmi-x.svg'      => 'assets/images/photos/zx-night.webp',
    'assets/images/hero-skyline.svg'          => 'assets/images/photos/ssc-gate-aerial.webp',
    'assets/images/logos/shalimar-smart-city.svg' => 'assets/images/logos/ssc-logo.svg',
    'assets/images/logos/gulberg-city.svg'    => 'assets/images/logos/gulberg-city-logo.svg',
    'assets/images/logos/zalmi-x.svg'         => 'assets/images/logos/zalmi-x.png',
];

function img_url(string $img): string
{
    if ($img === '') return photo('gc-homes');
    if (isset(LEGACY_ART[$img])) return LEGACY_ART[$img];
    if (strpos($img, 'assets/') === 0) return $img;           // bundled artwork
    return UPLOAD_URL . '/' . rawurlencode(basename($img));  // user uploads
}

/** Placeholder marker stored when a listing has no photos (kept for v1 compatibility). */
function category_art(string $cat): string
{
    return 'assets/images/listing-' . preg_replace('/[^a-z]/', '', $cat) . '.svg';
}

function is_placeholder(string $img): bool
{
    return $img === '' || strpos($img, 'assets/images/listing-') === 0;
}

/** Photo pool for listings without own photos — chosen by project + category. */
function listing_photo_pool(array $l): array
{
    $pools = [
        'shalimar-smart-city' => ['plot' => ['ssc-aerial-live', 'ssc-interchange', 'ssc-aerial', 'ssc-gate-aerial', 'ssc-entrance'],
                                  'house' => ['ssc-aerial', 'ssc-park', 'ssc-aerial-live'],
                                  'commercial' => ['ssc-commercial', 'ssc-business-bay', 'ssc-boulevard'],
                                  'office' => ['ssc-business-district', 'ssc-business-bay']],
        'gulberg-city'        => ['plot' => ['gc-facade', 'gc-villas-aerial', 'gc-aerial', 'gc-park'],
                                  'house' => ['gc-aerial', 'gc-homes', 'gc-villas'],
                                  'apartment' => ['gc-apartments', 'gc-entrance'],
                                  'commercial' => ['gc-boulevard', 'gc-entrance']],
        'zalmi-x'             => ['office' => ['zx-office', 'zx-lifts', 'zx-building', 'zx-parking'],
                                  'commercial' => ['zx-tower', 'zx-night', 'zx-terrace']],
        ''                    => ['plot' => ['ssc-aerial-live', 'gc-villas-aerial', 'ssc-interchange'],
                                  'house' => ['gc-aerial', 'gc-homes', 'gc-villas'],
                                  'apartment' => ['gc-apartments'], 'commercial' => ['ssc-commercial', 'gc-boulevard'],
                                  'office' => ['zx-office'], 'farmhouse' => ['ssc-park', 'gc-park']],
    ];
    $proj = isset($pools[$l['project'] ?? '']) ? ($l['project'] ?? '') : '';
    $cat = $l['category'] ?? 'house';
    $pool = $pools[$proj][$cat] ?? ($pools[''][$cat] ?? ['gc-homes']);
    $off = (int)($l['id'] ?? 0) % count($pool);
    return array_merge(array_slice($pool, $off), array_slice($pool, 0, $off));
}

function listing_image(array $l): string
{
    $img = $l['images'][0] ?? '';
    if (is_placeholder($img)) return photo(listing_photo_pool($l)[0]);
    return img_url($img);
}

/** Image paths for the listing detail gallery. */
function listing_gallery(array $l): array
{
    $own = array_values(array_filter($l['images'] ?? [], fn($i) => !is_placeholder((string)$i)));
    if ($own) return array_map('img_url', $own);
    return array_map(fn($n) => photo($n, true), array_slice(listing_photo_pool($l), 0, 4));
}

/** Extra media & facts for the flagship projects (projects added by admin fall back gracefully). */
function project_media(array $p): array
{
    $m = [
        'shalimar-smart-city' => [
            'tagline' => 'A smart, sustainable city on Mall Road',
            'gallery' => ['ssc-aerial', 'ssc-gate-render', 'ssc-mosque', 'ssc-business-district', 'ssc-boulevard', 'ssc-park', 'ssc-business-bay', 'ssc-food-court'],
            'features' => ['Underground electricity', 'Smart security system', 'EV charging points', 'Rainwater harvesting', 'Smart schools & hospitals', 'Grand Jamia Masjid', 'Food court & shopping bay', 'Planning by Meinhardt (Singapore)'],
            'source' => 'shalimarsmartcity.com / jaageer.com',
        ],
        'gulberg-city' => [
            'tagline' => 'An approved, lived-in community in Sargodha',
            'gallery' => ['gc-apartments', 'gc-aerial', 'gc-homes', 'gc-villas', 'gc-boulevard', 'gc-park', 'gc-facade', 'gc-entrance'],
            'features' => ['Approved society (Form-E)', 'Easy 1.5-year instalments', 'Main Jamia Masjid', 'Community centre', 'Mini market', 'Parks & play areas', '24/7 CCTV & boundary wall', 'Villas & apartment homes'],
            'source' => 'gulbergcity.com / jaageer.com',
        ],
        'zalmi-x' => [
            'tagline' => 'The new landmark of Pine Avenue, Lahore',
            'gallery' => ['zx-night', 'zx-building', 'zx-office', 'zx-lifts', 'zx-terrace', 'zx-parking', 'zx-tower'],
            'features' => ['LDA approved', 'Flagship outlets 4,000–5,400 sq ft', 'Founder offices 729–1,265 sq ft', 'High-speed lifts', 'Valet & basement parking', 'AI fire-fighting system', '24/7 power backup', 'EV charging stations'],
            'source' => 'zalmidevelopments.com / jaageer.com',
        ],
    ];
    $d = $m[$p['slug'] ?? ''] ?? ['tagline' => $p['kind'] ?? '', 'gallery' => [], 'features' => [], 'source' => ''];
    $d['cover_lg'] = img_url((string)($p['cover'] ?? ''));
    $sm = preg_replace('/(?<!-sm)\.webp$/', '-sm.webp', $d['cover_lg']);
    $d['cover'] = ($sm !== $d['cover_lg'] && is_file(__DIR__ . '/../' . $sm)) ? $sm : $d['cover_lg'];
    return $d;
}

function purpose_label(string $p): string
{
    return ['sale' => 'For sale', 'resale' => 'Resale', 'rent' => 'For rent', 'project' => 'Project unit'][$p] ?? ucfirst($p);
}

function category_label(string $c): string
{
    return ['house' => 'House', 'apartment' => 'Apartment', 'plot' => 'Plot', 'commercial' => 'Commercial / Shop',
            'office' => 'Office', 'farmhouse' => 'Farmhouse'][$c] ?? ucfirst($c);
}

const CATEGORIES = ['house', 'apartment', 'plot', 'commercial', 'office', 'farmhouse'];
const TX_TYPES   = ['sale' => 'New sale / booking', 'resale' => 'Resale', 'transfer' => 'Transfer', 'rent' => 'Rental agreement'];

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

/* --------------------------------------------------------------- data store */

const STORE_GUARD = "<?php http_response_code(404); exit; ?>\n";

function store_path(string $name): string
{
    return DATA_DIR . '/' . preg_replace('/[^a-z_]/', '', $name) . '.json.php';
}

function store_read(string $name): array
{
    static $seeded = false;
    $cache = &$GLOBALS['__store_cache'];
    $file = store_path($name);
    if (!is_file($file) && !$seeded) {
        $seeded = true;
        require_once __DIR__ . '/seed.php';
        seed_missing();
    }
    if (!is_file($file)) return [];
    $mtime = filemtime($file) . ':' . filesize($file);
    if (isset($cache[$name]) && $cache[$name][0] === $mtime) return $cache[$name][1];
    $raw = (string)file_get_contents($file);
    $pos = strpos($raw, "\n");
    $json = ($pos !== false && strpos($raw, '<?php') === 0) ? substr($raw, $pos + 1) : $raw;
    $data = json_decode($json, true);
    $data = is_array($data) ? $data : [];
    $cache[$name] = [$mtime, $data];
    return $data;
}

function store_write(string $name, array $data): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    $file = store_path($name);
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($name === 'transactions' ? 0 : JSON_PRETTY_PRINT);
    $json = json_encode($data, $flags);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, STORE_GUARD . $json, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write to the "data" folder. Please make it writable by PHP.');
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        if (@file_put_contents($file, STORE_GUARD . $json, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write to the "data" folder.');
        }
    }
    clearstatcache(true, $file);
    unset($GLOBALS['__store_cache'][$name]);
}

/** Read-modify-write a collection under an exclusive lock. $fn receives the data by reference. */
function store_update(string $name, callable $fn)
{
    store_read($name); // make sure seed exists before locking
    $lock = @fopen(DATA_DIR . '/.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    try {
        clearstatcache();
        $data = store_read($name);
        $result = $fn($data);
        store_write($name, $data);
        return $result;
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

function next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $r) $max = max($max, (int)($r['id'] ?? 0));
    return $max + 1;
}

function add_record(string $action, string $details, ?int $listingId = null, string $actor = 'system'): void
{
    store_update('records', function (array &$rows) use ($action, $details, $listingId, $actor) {
        array_unshift($rows, [
            'id' => next_id($rows), 'ts' => date('Y-m-d H:i'), 'actor' => $actor,
            'action' => $action, 'listing_id' => $listingId, 'details' => $details,
        ]);
        $rows = array_slice($rows, 0, 1500);
    });
}

/* ---------------------------------------------------------------- projects */

/** All projects keyed by slug (only active ones unless $all). */
function projects(bool $all = false): array
{
    $rows = store_read('projects');
    uasort($rows, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
    return $all ? $rows : array_filter($rows, fn($p) => !empty($p['active']));
}

function project(?string $key): ?array
{
    if (!$key) return null;
    $all = projects(true);
    return $all[$key] ?? null;
}

function project_name(?string $key): string
{
    $p = project($key);
    return $p ? (string)$p['name'] : '';
}

function project_unit(?string $key): string
{
    $p = project($key);
    return $p['unit'] ?? 'marla';
}

function project_initials(string $name): string
{
    $i = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $w) {
        if ($w !== '' && ctype_alpha($w[0])) $i .= strtoupper($w[0]);
    }
    return substr($i, 0, 2) ?: 'SP';
}

/** <img> for a project logo, or an elegant monogram when none uploaded. */
function project_logo_html(array $p, string $cls = 'plogo'): string
{
    if (!empty($p['logo'])) {
        return '<img class="' . e($cls) . '" src="' . e(img_url($p['logo'])) . '" alt="' . e($p['name']) . ' logo">';
    }
    return '<span class="' . e($cls) . ' plogo-mono" aria-hidden="true">' . e(project_initials($p['name'])) . '</span>';
}

/* ----------------------------------------------------------- roles/parties */

function roles(): array
{
    $s = store_read('settings');
    return $s['roles'] ?? [];
}

function role_of(array $listing): array
{
    $roles = roles();
    $key = !empty($listing['poster']['verified']) ? ($listing['poster']['role'] ?? 'public') : 'public';
    return $roles[$key] ?? ['label' => 'Other', 'mode' => 'review', 'badge' => ''];
}

/** Badge text for a listing ("Verified Owner", "Authorized Agency"...) or '' */
function listing_badge(array $l): string
{
    if (empty($l['poster']['verified'])) return '';
    return (string)(role_of($l)['badge'] ?? '');
}

/** Find an active owner/agency by membership code + registered mobile. */
function find_party(string $code, string $phone): ?array
{
    $code = strtoupper(trim($code));
    $ph = substr(digits($phone), -10);
    if ($code === '' || strlen($ph) < 10) return null;
    foreach (store_read('parties') as $p) {
        if (!empty($p['active']) && strtoupper((string)$p['code']) === $code && substr(digits((string)$p['phone']), -10) === $ph) {
            return $p;
        }
    }
    return null;
}

function party(int $id): ?array
{
    foreach (store_read('parties') as $p) if ((int)$p['id'] === $id) return $p;
    return null;
}

/* ---------------------------------------------------------------- listings */

function live_listings(): array
{
    return array_values(array_filter(store_read('listings'), fn($l) => ($l['status'] ?? '') === 'live'));
}

function find_listing(int $id): ?array
{
    foreach (store_read('listings') as $l) {
        if ((int)$l['id'] === $id) return $l;
    }
    return null;
}

function listing_price(array $l): string
{
    $p = e(fmt_pkr($l['price'] ?? 0));
    return ($l['purpose'] ?? '') === 'rent' ? $p . ' <b>/ month</b>' : $p;
}

function listing_location(array $l): string
{
    $parts = [];
    if (!empty($l['project'])) {
        $parts[] = project_name($l['project']);
        if (!empty($l['block'])) $parts[] = $l['block'];
        elseif (!empty($l['sector'])) $parts[] = $l['sector'];
    } elseif (!empty($l['location'])) {
        $parts[] = $l['location'];
    }
    if (!empty($l['city'])) $parts[] = $l['city'];
    return implode(', ', array_filter($parts));
}

function deal_label(string $deal): string
{
    return $deal === 'rented' ? 'Rented' : ($deal === 'sold' ? 'Sold' : 'Available');
}

function render_card(array $l, int $delay = 0): string
{
    $badge = listing_badge($l);
    $deal = $l['deal'] ?? 'available';
    $purpose = $l['purpose'] ?? 'sale';
    ob_start(); ?>
<article class="card rv<?= $deal !== 'available' ? ' is-closed' : '' ?>" style="--d:<?= (int)$delay ?>ms">
  <div class="card-art">
    <img src="<?= e(listing_image($l)) ?>" alt="<?= e($l['title']) ?>" loading="lazy" width="600" height="410">
    <span class="chip<?= $purpose === 'rent' ? ' rent' : ($purpose === 'project' ? ' proj' : '') ?>"><?= e(purpose_label($purpose)) ?></span>
    <?php if ($deal !== 'available'): ?><span class="ribbon ribbon-<?= e($deal) ?>"><?= e(deal_label($deal)) ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <p class="price"><?= listing_price($l) ?></p>
    <h4><a href="listing.php?id=<?= (int)$l['id'] ?>" class="stretched"><?= e($l['title']) ?></a></h4>
    <p class="addr"><i class="fa-solid fa-location-dot"></i> <?= e(listing_location($l)) ?></p>
    <div class="feats">
      <?php if (!empty($l['beds'])): ?><span><i class="fa-solid fa-bed"></i> <?= (int)$l['beds'] ?> Bed</span><?php endif; ?>
      <?php if (!empty($l['baths'])): ?><span><i class="fa-solid fa-bath"></i> <?= (int)$l['baths'] ?> Bath</span><?php endif; ?>
      <span><i class="fa-solid fa-ruler-combined"></i> <?= e(size_text($l['size'] ?? 0, $l['unit'] ?? 'marla')) ?></span>
    </div>
    <?php if ($badge): ?><span class="vbadge"><i class="fa-solid fa-circle-check"></i> <?= e($badge) ?></span>
    <?php else: ?><span class="vbadge vbadge-muted"><i class="fa-regular fa-clock"></i> Reviewed by admin</span><?php endif; ?>
  </div>
</article>
<?php
    return (string)ob_get_clean();
}

/** Filter + sort helper shared by buy/rent/projects pages. */
function filter_listings(array $purposes, array $q): array
{
    $rows = array_filter(live_listings(), function ($l) use ($purposes, $q) {
        if (!in_array($l['purpose'], $purposes, true)) return false;
        if (!empty($q['type']) && $l['category'] !== $q['type']) return false;
        if (!empty($q['city']) && strcasecmp($l['city'] ?? '', $q['city']) !== 0) return false;
        if (!empty($q['project']) && ($l['project'] ?? '') !== $q['project']) return false;
        if (!empty($q['agency']) && (int)($l['poster']['party_id'] ?? 0) !== (int)$q['agency']) return false;
        if (!empty($q['q'])) {
            $hay = mb_strtolower(($l['title'] ?? '') . ' ' . listing_location($l) . ' ' . ($l['sector'] ?? '') . ' ' . ($l['location'] ?? ''));
            if (mb_strpos($hay, mb_strtolower($q['q'])) === false) return false;
        }
        if (!empty($q['min']) && $l['price'] < $q['min']) return false;
        if (!empty($q['max']) && $l['price'] > $q['max']) return false;
        if (!empty($q['beds']) && (int)($l['beds'] ?? 0) < (int)$q['beds']) return false;
        if (!empty($q['verified']) && listing_badge($l) === '') return false;
        if (!empty($q['available']) && ($l['deal'] ?? 'available') !== 'available') return false;
        return true;
    });
    usort($rows, function ($a, $b) {
        $da = ($a['deal'] ?? 'available') === 'available' ? 0 : 1;
        $db = ($b['deal'] ?? 'available') === 'available' ? 0 : 1;
        return [$da, $b['published_at'] ?? ''] <=> [$db, $a['published_at'] ?? ''];
    });
    return array_values($rows);
}

/** Budget dropdown presets => [min, max] */
function budget_range(string $key): array
{
    $map = [
        'u50l' => [0, 5000000], '50l-1c' => [5000000, 10000000], '1c-2c' => [10000000, 20000000],
        '2c-5c' => [20000000, 50000000], '5c+' => [50000000, 0],
        'r-u40' => [0, 40000], 'r-40-80' => [40000, 80000], 'r-80+' => [80000, 0],
    ];
    return $map[$key] ?? [0, 0];
}
