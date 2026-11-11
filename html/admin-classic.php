<?php
if (!defined('PORTAL_CLASSIC_AUTHORIZED')) { require __DIR__.'/includes/admin-entry.php'; }
function classic_redirect(string $url): void { header('Location: '.$url); exit; }

/**
 * Shalimar Properties — admin panel (single file).
 * Authentication and permissions are enforced by the CI4 admin route.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/upload.php';

$tab = get_str('tab') ?: 'dashboard';
$self = site_url('admin/classic');

/* ------------------------------------------------------------- actions */
function find_index(array $rows, int $id): ?int
{
    foreach ($rows as $i => $r) if ((int)$r['id'] === $id) return $i;
    return null;
}

/** Record a transaction (and update the linked listing's deal status). */
function record_transaction(array $t, ?int $listingId): void
{
    if ($t['project']) {
        store_update('transactions', function (array &$rows) use (&$t) {
            $t['id'] = next_id($rows);
            $rows[] = $t;
        });
    }
    if ($listingId) {
        $deal = $t['type'] === 'rent' ? 'rented' : 'sold';
        store_update('listings', function (array &$rows) use ($listingId, $deal) {
            $i = find_index($rows, $listingId);
            if ($i !== null) { $rows[$i]['deal'] = $deal; $rows[$i]['deal_at'] = date('Y-m-d H:i'); }
        });
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post_str('do', 30);
    $back = $self . '?tab=' . urlencode(post_str('back', 30) ?: $tab);
    if (!csrf_ok()) { flash('Session expired — please retry.', 'err'); classic_redirect($back); }
    $id = (int)post_str('id', 10);
    try {
        switch ($do) {
            case 'logout':
                session_destroy();
                classic_redirect('index.php');
                break;

            case 'approve':
            case 'reopen':
                throw new InvalidArgumentException(\App\Services\LegacyListingPolicy::MESSAGE);
            case 'reject':
            case 'unpublish':
            case 'remove':
                $note = post_str('note', 300);
                $l = store_update('listings', function (array &$rows) use ($id, $do, $note) {
                    $i = find_index($rows, $id);
                    if ($i === null) return null;
                    $now = date('Y-m-d H:i');
                    if ($do === 'reject') { $rows[$i]['status'] = 'rejected'; }
                    if ($do === 'unpublish') { $rows[$i]['status'] = 'unlisted'; }
                    if ($do === 'remove') { $rows[$i]['status'] = 'removed'; }
                    if ($note !== '') $rows[$i]['review_note'] = $note;
                    return $rows[$i];
                });
                if ($l) {
                    $labels = ['reject' => 'Listing rejected', 'unpublish' => 'Listing unpublished', 'remove' => 'Listing removed'];
                    add_record($labels[$do], $l['ref'] . ' · ' . $l['title'] . ($note ? ' · Note: ' . $note : ''), $id, 'admin');
                    flash($labels[$do] . ': ' . $l['ref']);
                }
                break;

            case 'img_review': // approve / reject uploaded photos and pick the thumbnail
                $decision = post_str('decision', 10);
                $keep = array_map('intval', (array)($_POST['keep'] ?? []));
                $cover = (int)post_str('cover', 3);
                $l = store_update('listings', function (array &$rows) use ($id, $decision, $keep, $cover) {
                    $i = find_index($rows, $id);
                    if ($i === null) return null;
                    $imgs = array_values(array_filter($rows[$i]['images'] ?? [], fn($x) => !is_placeholder((string)$x)));
                    if ($decision === 'approve') {
                        $kept = [];
                        foreach ($imgs as $k => $img) if (in_array($k, $keep, true)) $kept[$k] = $img;
                        if (isset($kept[$cover])) { $c = $kept[$cover]; unset($kept[$cover]); array_unshift($kept, $c); }
                        $kept = array_values($kept);
                        $rows[$i]['images'] = $kept ?: [category_art($rows[$i]['category'] ?? 'house')];
                        $rows[$i]['images_status'] = $kept ? 'approved' : 'rejected';
                    } else {
                        $rows[$i]['images'] = [category_art($rows[$i]['category'] ?? 'house')];
                        $rows[$i]['images_status'] = 'rejected';
                    }
                    return $rows[$i];
                });
                if ($l) {
                    $msg = $l['images_status'] === 'approved' ? 'Photos approved (' . count($l['images']) . ', thumbnail set)' : 'Photos rejected — project photo shown';
                    add_record($msg, $l['ref'] . ' · ' . $l['title'], $id, 'admin');
                    flash($msg . ': ' . $l['ref']);
                }
                break;

            case 'deal': // mark listing sold / rented → creates transaction
                $l = find_listing($id);
                if (!$l) { flash('Listing not found.', 'err'); break; }
                $type = $l['purpose'] === 'rent' ? 'rent' : post_str('type', 10);
                if (!isset(TX_TYPES[$type])) $type = 'resale';
                $price = (int)digits(post_str('price', 15)) ?: (int)$l['price'];
                $date = post_str('date', 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
                record_transaction([
                    'date' => $date, 'project' => $l['project'], 'sector' => $l['sector'], 'block' => $l['block'],
                    'size' => (float)$l['size'], 'unit' => $l['unit'], 'category' => $l['category'], 'type' => $type, 'price' => $price,
                    'listing_id' => $id, 'note' => post_str('note', 200), 'source' => 'admin',
                ], $id);
                $status = $type === 'rent' ? 'Rented' : 'Sold';
                add_record(($type === 'rent' ? 'Rental agreement recorded' : TX_TYPES[$type] . ' recorded') . ' → listing marked ' . $status,
                    $l['ref'] . ' · ' . $l['title'] . ' · ' . fmt_pkr($price) . ' on ' . $date, $id, 'admin');
                flash($l['ref'] . ' is now marked “' . $status . '” and the transaction feeds the market report.');
                break;

            case 'add_tx':
                $pk = post_str('project', 60);
                $pr = project($pk);
                if (!$pr) { flash('Choose a project.', 'err'); break; }
                $sector = post_str('sector', 60);
                $block = post_str('block', 60);
                if (!isset($pr['sectors'][$sector]) || !in_array($block, $pr['sectors'][$sector], true)) { flash('Choose a valid sector and block.', 'err'); break; }
                $type = post_str('type', 10);
                if (!isset(TX_TYPES[$type])) $type = 'resale';
                $size = (float)post_str('size', 10);
                $price = (int)digits(post_str('price', 15));
                if ($size <= 0 || $price <= 0) { flash('Enter a valid size and price.', 'err'); break; }
                $date = post_str('date', 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
                $cat = post_str('category', 20);
                if (!in_array($cat, CATEGORIES, true)) $cat = 'plot';
                $ref = strtoupper(post_str('ref', 20));
                $lid = null;
                if ($ref !== '') {
                    foreach (store_read('listings') as $x) if (strtoupper($x['ref']) === $ref) $lid = (int)$x['id'];
                    if (!$lid) { flash('Listing reference ' . $ref . ' not found.', 'err'); break; }
                }
                record_transaction(['date' => $date, 'project' => $pk, 'sector' => $sector, 'block' => $block, 'size' => $size, 'unit' => $pr['unit'],
                    'category' => $cat, 'type' => $type, 'price' => $price, 'listing_id' => $lid, 'note' => post_str('note', 200), 'source' => 'admin'], $lid);
                add_record(TX_TYPES[$type] . ' recorded', $pr['name'] . ' · ' . $sector . ' · ' . $block . ' · ' . size_text($size, $pr['unit']) . ' · ' . fmt_pkr($price) . ($ref ? ' · listing ' . $ref . ' updated' : ''), $lid, 'admin');
                flash('Transaction recorded' . ($ref ? ' and listing ' . $ref . ' status updated.' : '.'));
                break;

            case 'del_tx':
                $gone = store_update('transactions', function (array &$rows) use ($id) {
                    $i = find_index($rows, $id);
                    if ($i === null || ($rows[$i]['source'] ?? '') !== 'admin') return null;
                    return array_splice($rows, $i, 1)[0];
                });
                if ($gone) {
                    add_record('Transaction deleted', project_name($gone['project']) . ' · ' . $gone['block'] . ' · ' . fmt_pkr($gone['price']) . ' on ' . $gone['date'], null, 'admin');
                    flash('Transaction deleted.');
                } else {
                    flash('Only transactions recorded by admin can be deleted.', 'err');
                }
                break;

            case 'party_add':
                $role = post_str('role', 30);
                $roles = roles();
                if (!isset($roles[$role]) || $role === 'public') { flash('Choose a valid role.', 'err'); break; }
                $code = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', post_str('code', 20)));
                $name = post_str('name', 80);
                $phone = post_str('phone', 20);
                if ($code === '' || $name === '' || strlen(digits($phone)) < 10) { flash('Name, code and a valid mobile are required.', 'err'); break; }
                $ok = store_update('parties', function (array &$rows) use ($role, $code, $name, $phone) {
                    foreach ($rows as $r) if (strtoupper($r['code']) === $code) return false;
                    $rows[] = ['id' => next_id($rows), 'role' => $role, 'name' => $name, 'code' => $code, 'phone' => $phone,
                        'city' => post_str('city', 40) ?: 'Sargodha', 'area' => post_str('area', 80), 'rating' => 0, 'deals' => 0,
                        'active' => true, 'created_at' => date('Y-m-d')];
                    return true;
                });
                if (!$ok) { flash('Code ' . $code . ' already exists.', 'err'); break; }
                add_record('Upload rights granted', $name . ' (' . $code . ') as ' . ($roles[$role]['label'] ?? $role), null, 'admin');
                flash($name . ' can now post using code ' . $code . '.');
                break;

            case 'party_toggle':
                $p = store_update('parties', function (array &$rows) use ($id) {
                    $i = find_index($rows, $id);
                    if ($i === null) return null;
                    $rows[$i]['active'] = empty($rows[$i]['active']);
                    return $rows[$i];
                });
                if ($p) {
                    add_record($p['active'] ? 'Upload rights restored' : 'Upload rights revoked', $p['name'] . ' (' . $p['code'] . ')', null, 'admin');
                    flash($p['name'] . ($p['active'] ? ' re-activated.' : ' deactivated — can no longer publish.'));
                }
                break;

            case 'roles_save':
                $modes = $_POST['mode'] ?? [];
                $badges = $_POST['badge'] ?? [];
                $labels = $_POST['label'] ?? [];
                store_update('settings', function (array &$s) use ($modes, $badges, $labels) {
                    foreach ($s['roles'] as $k => &$r) {
                        if (isset($modes[$k]) && is_string($modes[$k]) && isset(PUBLISH_MODES[$modes[$k]])) {
                            $r['mode'] = ($k === 'public' && in_array($modes[$k], ['live', 'live_logged'], true)) ? 'review' : $modes[$k];
                        }
                        if (isset($badges[$k]) && is_string($badges[$k]) && $k !== 'public') $r['badge'] = mb_substr(trim($badges[$k]), 0, 40);
                        if (isset($labels[$k]) && is_string($labels[$k]) && trim($labels[$k]) !== '') $r['label'] = mb_substr(trim($labels[$k]), 0, 60);
                    }
                });
                add_record('Upload rules updated', 'Publishing modes / badges changed', null, 'admin');
                flash('Upload rules saved.');
                break;

            case 'role_add':
                $label = post_str('label', 60);
                $key = trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
                $mode = post_str('mode', 20);
                if ($key === '' || !isset(PUBLISH_MODES[$mode])) { flash('Enter a role name and mode.', 'err'); break; }
                $ok = store_update('settings', function (array &$s) use ($key, $label, $mode) {
                    if (isset($s['roles'][$key])) return false;
                    $s['roles'][$key] = ['label' => $label, 'mode' => $mode, 'badge' => post_str('badge', 40), 'system' => false];
                    return true;
                });
                if (!$ok) { flash('That role already exists.', 'err'); break; }
                add_record('Upload role added', $label . ' · ' . PUBLISH_MODES[$mode], null, 'admin');
                flash('Role “' . $label . '” added. Add people to it under “Owners & agencies”.');
                break;

            case 'role_delete':
                $key = post_str('key', 40);
                $inUse = count(array_filter(store_read('parties'), fn($p) => $p['role'] === $key));
                $ok = !$inUse && store_update('settings', function (array &$s) use ($key) {
                    if (!isset($s['roles'][$key]) || !empty($s['roles'][$key]['system'])) return false;
                    unset($s['roles'][$key]);
                    return true;
                });
                flash($ok ? 'Role deleted.' : 'Role cannot be deleted (built-in or still assigned to people).', $ok ? 'ok' : 'err');
                if ($ok) add_record('Upload role deleted', $key, null, 'admin');
                break;

            case 'project_save':
                $name = post_str('name', 80);
                $slug = post_str('slug', 60) ?: trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
                if ($name === '' || $slug === '') { flash('Project name is required.', 'err'); break; }
                $sectors = [];
                foreach (preg_split('/\R/', post_str('sectors', 3000)) ?: [] as $line) {
                    if (trim($line) === '') continue;
                    [$sec, $bl] = array_pad(explode(':', $line, 2), 2, '');
                    $sec = trim($sec);
                    $blocks = array_values(array_filter(array_map('trim', explode(',', $bl))));
                    if ($sec !== '') $sectors[$sec] = $blocks ?: ['Main'];
                }
                if (!$sectors) $sectors = ['Main' => ['Main']];
                [$imgs, $imgErr] = save_uploaded_images('logo');
                [$covers, $covErr] = save_uploaded_images('cover');
                $unit = post_str('unit', 10) === 'sqft' ? 'sqft' : 'marla';
                store_update('projects', function (array &$rows) use ($slug, $name, $sectors, $imgs, $covers, $unit) {
                    $cur = $rows[$slug] ?? ['slug' => $slug, 'order' => count($rows) + 1, 'active' => true, 'logo' => '', 'cover' => 'assets/images/photos/ssc-gate-aerial.webp', 'source' => 'Added by admin'];
                    $cur = array_merge($cur, [
                        'name' => $name, 'city' => post_str('city', 40), 'location' => post_str('location', 120), 'developer' => post_str('developer', 120),
                        'kind' => post_str('kind', 60), 'status' => post_str('status', 60), 'progress' => max(0, min(100, (int)post_str('progress', 3))),
                        'unit' => $unit, 'price_from' => post_str('price_from', 60), 'summary' => post_str('summary', 600), 'sectors' => $sectors,
                    ]);
                    if ($imgs) $cur['logo'] = $imgs[0];
                    if ($covers) $cur['cover'] = $covers[0];
                    $rows[$slug] = $cur;
                });
                add_record('Project saved', $name . ' (' . count($sectors) . ' sectors)', null, 'admin');
                flash('Project “' . $name . '” saved.' . (($imgErr || $covErr) ? ' Image note: ' . implode(' ', array_merge($imgErr, $covErr)) : ''));
                $back = $self . '?tab=projects';
                break;

            case 'project_toggle':
                $slug = post_str('slug', 60);
                store_update('projects', function (array &$rows) use ($slug) {
                    if (isset($rows[$slug])) $rows[$slug]['active'] = empty($rows[$slug]['active']);
                });
                flash('Project visibility updated.');
                break;

            case 'reset_demo':
                require_once __DIR__ . '/includes/seed.php';
                seed_missing(true);
                flash('Demo data has been reset.');
                break;
        }
    } catch (Throwable $ex) {
        flash('Could not save this change. Please contact your administrator.', 'err');
    }
    classic_redirect($back);
}

/* ------------------------------------------------------------- view */
$listings = store_read('listings');
$pendingCount = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));
$imgPendingCount = count(array_filter($listings, fn($l) => ($l['images_status'] ?? '') === 'pending' && !in_array($l['status'], ['removed', 'rejected'], true)));
$roles = roles();
$projects = projects(true);
$tabs = [
    'dashboard' => ['Dashboard', 'fa-gauge'], 'pending' => ['Legacy pending', 'fa-hourglass-half'], 'images' => ['Image review', 'fa-images'], 'listings' => ['All listings', 'fa-list'],
    'transactions' => ['Transactions', 'fa-handshake'], 'parties' => ['Owners & agencies', 'fa-id-badge'], 'roles' => ['Upload rules', 'fa-sliders'],
    'projects' => ['Projects', 'fa-city'], 'records' => ['Records log', 'fa-clock-rotate-left'],
];
$classicTabPermissions=['dashboard'=>'dashboard.view','pending'=>'resale_listings.view','images'=>'resale_listings.view','listings'=>'resale_listings.view','transactions'=>'transactions.view','parties'=>'agencies.view','roles'=>'settings.view','projects'=>'projects.view','records'=>'audit_logs.view'];
$tabs=array_filter($tabs,fn($item,$key)=>can($classicTabPermissions[$key]),ARRAY_FILTER_USE_BOTH);
if (!isset($tabs[$tab])) $tab = 'dashboard';

$active = 'admin';
$pageTitle = 'Admin · ' . $tabs[$tab][0] . ' — Shalimar Properties';
$bodyClass = 'admin-body';
require __DIR__ . '/includes/header.php';
$fl = flash();

function status_pill(string $s): string
{
    $map = ['live' => ['Live', ''], 'pending' => ['Pending', 'warn'], 'rejected' => ['Rejected', 'red'], 'unlisted' => ['Unlisted', 'muted'], 'removed' => ['Removed', 'muted']];
    [$t, $c] = $map[$s] ?? [ucfirst($s), 'muted'];
    return '<span class="pill ' . $c . '">' . e($t) . '</span>';
}
function deal_pill(string $d): string
{
    return $d === 'available' ? '<span class="pill muted">Available</span>' : '<span class="pill ' . ($d === 'rented' ? 'blue' : 'red') . '">' . e(deal_label($d)) . '</span>';
}
function act_btn(string $do, int $id, string $label, string $cls = 'btn-ghost', string $confirm = '', string $back = ''): string
{
    return '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="' . e($do) . '"><input type="hidden" name="id" value="' . $id . '">'
        . ($back ? '<input type="hidden" name="back" value="' . e($back) . '">' : '')
        . '<button class="btn btn-sm ' . e($cls) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . $label . '</button></form>';
}
/** Inline "mark sold/rented" form */
function deal_form(array $l, string $back): string
{
    $rent = $l['purpose'] === 'rent';
    ob_start(); ?>
<details><summary class="btn btn-sm btn-solid" style="list-style:none"><i class="fa-solid fa-file-signature"></i> <?= $rent ? 'Mark rented' : 'Mark sold' ?></summary>
  <form method="post" class="inline-form" style="margin-top:8px;padding:10px;background:var(--paper);border-radius:12px">
    <?= csrf_field() ?><input type="hidden" name="do" value="deal"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <?php if (!$rent): ?><select name="type" aria-label="Transaction type"><option value="resale">Resale</option><option value="sale">New sale</option><option value="transfer">Transfer</option></select><?php endif; ?>
    <input name="price" inputmode="numeric" value="<?= (int)$l['price'] ?>" aria-label="Final price" style="width:120px">
    <input name="date" type="date" value="<?= date('Y-m-d') ?>" aria-label="Date">
    <button class="btn btn-sm btn-solid"><?= $rent ? 'Agreement done' : 'Deal done' ?></button>
  </form>
</details>
<?php return (string)ob_get_clean();
}
function listing_row(array $l, string $back): string
{
    ob_start(); ?>
<tr>
  <td style="min-width:240px"><a href="listing.php?id=<?= (int)$l['id'] ?>" target="_blank" rel="noopener"><b><?= e($l['title']) ?></b></a><br>
    <span class="muted" style="font-size:12px"><?= e($l['ref']) ?> · <?= e(purpose_label($l['purpose'])) ?> · <?= e(listing_location($l)) ?></span>
    <?php if (!empty($l['review_note'])): ?><br><span style="font-size:12px;color:var(--warn)"><i class="fa-solid fa-circle-info"></i> <?= e($l['review_note']) ?></span><?php endif; ?></td>
  <td class="num"><?= listing_price($l) ?></td>
  <td><?= e($l['poster']['name']) ?><br><span class="muted" style="font-size:12px"><?= e(roles()[$l['poster']['verified'] ? $l['poster']['role'] : 'public']['label'] ?? '') ?> · <?= e($l['poster']['phone']) ?></span>
    <?= listing_badge($l) ? '<br><span class="vbadge" style="margin-top:4px"><i class="fa-solid fa-circle-check"></i> ' . e(listing_badge($l)) . '</span>' : '' ?></td>
  <td><?= status_pill($l['status']) ?> <?= deal_pill($l['deal']) ?></td>
  <td><div class="admin-actions">
    <?php if ($l['status'] === 'pending'): ?>
      <span class="muted">Customer OTP publication required</span>
      <?= act_btn('reject', (int)$l['id'], '<i class="fa-solid fa-xmark"></i> Reject', 'btn-danger', 'Reject this listing?', $back) ?>
    <?php elseif ($l['status'] === 'live'): ?>
      <?= $l['deal'] === 'available' ? deal_form($l, $back) : '<span class="muted">Reactivation unavailable</span>' ?>
      <?= act_btn('unpublish', (int)$l['id'], 'Unpublish', 'btn-ghost', 'Hide this listing from the website?', $back) ?>
    <?php else: ?>
      <span class="muted">Customer verification required to publish</span>
    <?php endif; ?>
    <?php if ($l['status'] !== 'removed'): ?><?= act_btn('remove', (int)$l['id'], '<i class="fa-solid fa-trash"></i>', 'btn-danger', 'Remove this listing?', $back) ?><?php endif; ?>
  </div></td>
</tr>
<?php return (string)ob_get_clean();
}
?>
<div class="wrap admin-shell">
  <aside class="admin-nav">
    <?php foreach ($tabs as $k => $t): ?>
      <a href="admin-classic.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><i class="fa-solid <?= $t[1] ?>"></i><?= e($t[0]) ?><?= $k === 'pending' && $pendingCount ? '<span class="cnt">' . $pendingCount . '</span>' : '' ?><?= $k === 'images' && $imgPendingCount ? '<span class="cnt">' . $imgPendingCount . '</span>' : '' ?></a>
    <?php endforeach; ?>
    <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="btn btn-ghost btn-sm btn-block"><i class="fa-solid fa-right-from-bracket"></i> Sign out</button></form>
  </aside>

  <div class="admin-main">
    <?php if ($fl): ?><div class="alert alert-<?= $fl['type'] === 'err' ? 'err' : 'ok' ?>"><i class="fa-solid <?= $fl['type'] === 'err' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i> <?= e($fl['msg']) ?></div><?php endif; ?>

<?php if ($tab === 'dashboard'):
    require_once __DIR__ . '/includes/market.php';
    $k = market_kpis(market_tx());
    $live = count(array_filter($listings, fn($l) => $l['status'] === 'live'));
    $closed = count(array_filter($listings, fn($l) => $l['deal'] !== 'available'));
    $recent = array_slice(store_read('records'), 0, 8); ?>
    <h1>Dashboard</h1><p class="muted" style="margin:0 0 14px">Welcome back. Here is what needs your attention.</p>
    <div class="kpis">
      <a class="kpi" href="admin-classic.php?tab=pending"><small>Legacy pending</small><b><?= $pendingCount ?></b><span>historical unpublished records</span></a>
      <a class="kpi" href="admin-classic.php?tab=listings"><small>Live listings</small><b><?= $live ?></b><span><?= $closed ?> sold / rented</span></a>
      <a class="kpi" href="admin-classic.php?tab=transactions"><small>Deals this month</small><b><?= (int)$k['month'] ?></b><span><?= (int)$k['today'] ?> today</span></a>
      <a class="kpi" href="admin-classic.php?tab=parties"><small>Authorized posters</small><b><?= count(array_filter(store_read('parties'), fn($p) => !empty($p['active']))) ?></b><span>owners &amp; agencies</span></a>
    </div>
    <div class="chart-grid cols-2">
      <div class="panel"><h3 style="font-size:17px">Quick actions</h3>
        <div style="display:grid;gap:8px;margin-top:10px">
          <a class="btn btn-solid" href="admin-classic.php?tab=pending"><i class="fa-solid fa-hourglass-half"></i> View legacy pending records</a>
          <a class="btn btn-ghost" href="admin-classic.php?tab=transactions"><i class="fa-solid fa-handshake"></i> Record a transaction</a>
          <a class="btn btn-ghost" href="admin-classic.php?tab=parties"><i class="fa-solid fa-user-plus"></i> Add owner / agency</a>
          <a class="btn btn-ghost" href="insights.php" target="_blank"><i class="fa-solid fa-chart-line"></i> Open market report</a>
        </div></div>
      <div class="panel"><h3 style="font-size:17px">Recent activity</h3>
        <?php foreach ($recent as $r): ?><div style="padding:8px 0;border-top:1px solid var(--line);font-size:13px"><b><?= e($r['action']) ?></b><br><span class="muted"><?= e($r['ts']) ?> · <?= e(mb_strimwidth($r['details'], 0, 110, '…')) ?></span></div><?php endforeach; ?>
        <a class="link-more" href="admin-classic.php?tab=records" style="margin-top:8px">Full records <i class="fa-solid fa-arrow-right"></i></a></div>
    </div>

<?php elseif ($tab === 'pending'):
    $rows = array_values(array_filter($listings, fn($l) => $l['status'] === 'pending'));
    usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at'])); ?>
    <h1>Legacy pending records</h1><p class="muted" style="margin:0 0 14px">These historical records are preserved. Admin publication is disabled; customers publish directly through current-booking OTP verification.</p>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="tbl stack"><thead><tr><th>Listing</th><th class="num">Price</th><th>Posted by</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $l) echo listing_row($l, 'pending'); ?>
    </tbody></table></div>
    <?php else: ?><div class="empty-note"><i class="fa-solid fa-circle-check"></i>Nothing waiting for review. 🎉</div><?php endif; ?>

<?php elseif ($tab === 'images'):
    $rows = array_values(array_filter($listings, fn($l) => ($l['images_status'] ?? '') === 'pending' && !in_array($l['status'], ['removed', 'rejected'], true)));
    usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at'])); ?>
    <h1>Image review</h1><p class="muted" style="margin:0 0 14px">Every uploaded photo is checked here before it appears on the website. Keep only good-quality photos and pick the best one as the thumbnail. Until approved, a project photo is shown.</p>
    <?php if ($rows): foreach ($rows as $l): $imgs = array_values(array_filter($l['images'] ?? [], fn($x) => !is_placeholder((string)$x))); ?>
    <form method="post" class="panel" style="margin-bottom:14px">
      <?= csrf_field() ?><input type="hidden" name="do" value="img_review"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="images">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">
        <div><b><?= e($l['ref']) ?> · <?= e($l['title']) ?></b><div class="muted" style="font-size:12.5px"><?= e($l['poster']['name'] ?? '') ?> · <?= status_pill($l['status']) ?> · <?= e($l['created_at']) ?></div></div>
        <?php if ($l['status'] === 'live'): ?><a class="link-more" href="listing.php?id=<?= (int)$l['id'] ?>" target="_blank">View listing <i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:12px 0">
        <?php foreach ($imgs as $k => $img): ?>
        <div style="border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff">
          <a href="<?= e(img_url($img)) ?>" target="_blank"><img src="<?= e(img_url($img)) ?>" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block"></a>
          <div style="display:flex;justify-content:space-between;gap:6px;padding:8px 10px;font-size:12px">
            <label><input type="checkbox" name="keep[]" value="<?= $k ?>" checked> Keep</label>
            <label><input type="radio" name="cover" value="<?= $k ?>"<?= $k === 0 ? ' checked' : '' ?>> Thumbnail</label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-solid btn-sm" name="decision" value="approve"><i class="fa-solid fa-check"></i> Approve selected</button>
        <button class="btn btn-ghost btn-sm" name="decision" value="reject" onclick="return confirm('Reject all photos of this listing?')"><i class="fa-solid fa-xmark"></i> Reject all photos</button>
      </div>
    </form>
    <?php endforeach; else: ?><div class="empty-note"><i class="fa-solid fa-circle-check"></i>No photos waiting for review.</div><?php endif; ?>

<?php elseif ($tab === 'listings'):
    $q = get_str('q');
    $st = get_str('status');
    $rows = array_values(array_filter($listings, function ($l) use ($q, $st) {
        if ($st !== '' && $l['status'] !== $st && $l['deal'] !== $st) return false;
        if ($q !== '' && mb_stripos($l['ref'] . ' ' . $l['title'] . ' ' . $l['poster']['name'], $q) === false) return false;
        return true;
    }));
    usort($rows, fn($a, $b) => $b['id'] <=> $a['id']); ?>
    <h1>All listings</h1>
    <form class="inline-form" method="get" style="margin:6px 0 14px"><input type="hidden" name="tab" value="listings">
      <input name="q" value="<?= e($q) ?>" placeholder="Ref, title or poster" aria-label="Search">
      <select name="status" aria-label="Status"><option value="">All statuses</option><?php foreach (['live' => 'Live', 'pending' => 'Pending', 'rejected' => 'Rejected', 'unlisted' => 'Unlisted', 'removed' => 'Removed', 'sold' => 'Sold', 'rented' => 'Rented'] as $k2 => $v2): ?><option value="<?= $k2 ?>"<?= $st === $k2 ? ' selected' : '' ?>><?= $v2 ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-solid">Filter</button></form>
    <div class="table-wrap"><table class="tbl stack"><thead><tr><th>Listing</th><th class="num">Price</th><th>Posted by</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $l) echo listing_row($l, 'listings'); if (!$rows): ?><tr><td colspan="5" class="muted">No listings found.</td></tr><?php endif; ?>
    </tbody></table></div>

<?php elseif ($tab === 'transactions'):
    $tx = store_read('transactions');
    usort($tx, fn($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    $tx = array_slice($tx, 0, 60); ?>
    <h1>Transactions &amp; agreements</h1><p class="muted" style="margin:0 0 14px">Every recorded deal feeds the public market report. Linking a listing reference marks that listing “Sold” or “Rented” automatically.</p>
    <form method="post" class="panel form-grid cols-4">
      <?= csrf_field() ?><input type="hidden" name="do" value="add_tx">
      <div class="fld"><label for="tp">Project</label><select id="tp" name="project" required><option value="">Select</option><?php foreach ($projects as $p): ?><option value="<?= e($p['slug']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="ts">Sector</label><select id="ts" name="sector" data-depends="tp" required><option value="">Select</option><?php foreach ($projects as $p) foreach ($p['sectors'] as $sec => $bl): ?><option value="<?= e($sec) ?>" data-project="<?= e($p['slug']) ?>"><?= e($sec) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="tb">Block</label><select id="tb" name="block" data-depends="ts" data-attr="sector" required><option value="">Select</option><?php foreach ($projects as $p) foreach ($p['sectors'] as $sec => $bl) foreach ($bl as $b): ?><option value="<?= e($b) ?>" data-sector="<?= e($sec) ?>"><?= e($b) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="tt">Type</label><select id="tt" name="type"><?php foreach (TX_TYPES as $k2 => $v2): ?><option value="<?= $k2 ?>"><?= e($v2) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="tc">Property type</label><select id="tc" name="category"><?php foreach (CATEGORIES as $c): ?><option value="<?= $c ?>"<?= $c === 'plot' ? ' selected' : '' ?>><?= e(category_label($c)) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="tz">Size (marla / sq ft)</label><input id="tz" name="size" inputmode="decimal" required placeholder="10"></div>
      <div class="fld"><label for="tpr">Price (PKR)</label><input id="tpr" name="price" inputmode="numeric" required placeholder="6000000"></div>
      <div class="fld"><label for="td">Date</label><input id="td" name="date" type="date" value="<?= date('Y-m-d') ?>"></div>
      <div class="fld"><label for="tr">Listing ref (optional)</label><input id="tr" name="ref" placeholder="SP-1007"></div>
      <div class="fld" style="grid-column:span 2"><label for="tn">Note (optional)</label><input id="tn" name="note" placeholder="Buyer / file no. / remarks"></div>
      <div class="fld" style="align-self:end"><button class="btn btn-solid btn-block"><i class="fa-solid fa-plus"></i> Record</button></div>
    </form>
    <h3 style="margin:18px 0 8px;font-size:18px">Latest 60 transactions</h3>
    <div class="table-wrap tall"><table class="tbl"><thead><tr><th>Date</th><th>Project · Sector · Block</th><th>Size</th><th>Type</th><th class="num">Price</th><th>Source</th><th></th></tr></thead><tbody>
      <?php foreach ($tx as $t): ?><tr>
        <td style="white-space:nowrap"><?= e(date('d M Y', strtotime($t['date']))) ?></td><td><?= e(project_name($t['project'])) ?> · <?= e($t['sector']) ?> · <?= e($t['block']) ?></td>
        <td><?= e(size_text($t['size'], $t['unit'])) ?></td><td><?= e(TX_TYPES[$t['type']] ?? $t['type']) ?></td>
        <td class="num"><?= e(fmt_pkr($t['price'])) ?></td>
        <td><?= ($t['source'] ?? '') === 'admin' ? '<span class="pill">Admin</span>' : '<span class="pill muted">Sample</span>' ?><?= !empty($t['listing_id']) ? ' <a class="pill gold" href="listing.php?id=' . (int)$t['listing_id'] . '" target="_blank">Listing</a>' : '' ?></td>
        <td><?= ($t['source'] ?? '') === 'admin' ? act_btn('del_tx', (int)$t['id'], '<i class="fa-solid fa-trash"></i>', 'btn-danger', 'Delete this transaction?', 'transactions') : '' ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>

<?php elseif ($tab === 'parties'):
    $parties = store_read('parties'); ?>
    <h1>Owners &amp; agencies</h1><p class="muted" style="margin:0 0 14px">People listed here can verify on “Post a property” with their code + registered mobile. Their publishing rights come from their role (see Upload rules).</p>
    <form method="post" class="panel form-grid cols-3">
      <?= csrf_field() ?><input type="hidden" name="do" value="party_add">
      <div class="fld"><label for="pr">Role</label><select id="pr" name="role"><?php foreach ($roles as $rk => $r): if ($rk === 'public') continue; ?><option value="<?= e($rk) ?>"><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="pn">Name</label><input id="pn" name="name" required placeholder="Person or agency name"></div>
      <div class="fld"><label for="pc">Code (unique)</label><input id="pc" name="code" required placeholder="SG-1006 / AG-207"></div>
      <div class="fld"><label for="pp">Registered mobile</label><input id="pp" name="phone" required placeholder="03xx-xxxxxxx"></div>
      <div class="fld"><label for="pci">City</label><input id="pci" name="city" value="Sargodha"></div>
      <div class="fld"><label for="pa">Area / project</label><input id="pa" name="area" placeholder="e.g. Sector II, Golf Avenue"></div>
      <div class="full"><button class="btn btn-solid"><i class="fa-solid fa-user-plus"></i> Grant upload rights</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px"><table class="tbl stack"><thead><tr><th>Name</th><th>Code</th><th>Role</th><th>Mobile</th><th>Area</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($parties as $p): ?><tr>
        <td><b><?= e($p['name']) ?></b></td><td><code><?= e($p['code']) ?></code></td><td><?= e($roles[$p['role']]['label'] ?? $p['role']) ?></td>
        <td><?= e($p['phone']) ?></td><td><?= e($p['area']) ?> · <?= e($p['city']) ?></td>
        <td><?= !empty($p['active']) ? '<span class="pill">Active</span>' : '<span class="pill red">Revoked</span>' ?></td>
        <td><?= act_btn('party_toggle', (int)$p['id'], !empty($p['active']) ? 'Revoke' : 'Restore', !empty($p['active']) ? 'btn-danger' : 'btn-ghost', !empty($p['active']) ? 'Revoke posting rights for ' . $p['name'] . '?' : '', 'parties') ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>

<?php elseif ($tab === 'roles'): ?>
    <h1>Upload rules</h1><p class="muted" style="margin:0 0 14px">Decide which roles can post and how their listings are published. “Other / third party” covers anyone who is not verified — it can only be reviewed or blocked.</p>
    <form method="post" class="table-wrap">
      <?= csrf_field() ?><input type="hidden" name="do" value="roles_save">
      <table class="tbl"><thead><tr><th>Role</th><th>When they post…</th><th>Badge on listing</th><th></th></tr></thead><tbody>
      <?php foreach ($roles as $rk => $r): ?><tr>
        <td><input name="label[<?= e($rk) ?>]" value="<?= e($r['label']) ?>" style="width:100%;min-width:200px;padding:7px 10px;border-radius:10px;border:1px solid var(--line-2)"><br><span class="muted" style="font-size:11.5px">key: <?= e($rk) ?><?= !empty($r['system']) ? ' · built-in' : '' ?></span></td>
        <td><select name="mode[<?= e($rk) ?>]" style="padding:7px 10px;border-radius:10px;border:1px solid var(--line-2);min-width:240px">
          <?php foreach (PUBLISH_MODES as $mk => $mv): if ($rk === 'public' && in_array($mk, ['live', 'live_logged'], true)) continue; ?><option value="<?= $mk ?>"<?= $r['mode'] === $mk ? ' selected' : '' ?>><?= e($mv) ?></option><?php endforeach; ?>
        </select></td>
        <td><?php if ($rk !== 'public'): ?><input name="badge[<?= e($rk) ?>]" value="<?= e($r['badge']) ?>" placeholder="e.g. Verified Owner" style="padding:7px 10px;border-radius:10px;border:1px solid var(--line-2)"><?php else: ?><span class="muted">No badge (“Reviewed by admin”)</span><?php endif; ?></td>
        <td><?php if (empty($r['system'])): ?><button class="btn btn-sm btn-danger" form="del-<?= e($rk) ?>" data-confirm="Delete role <?= e($r['label']) ?>?"><i class="fa-solid fa-trash"></i></button><?php endif; ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
      <div style="padding:12px"><button class="btn btn-solid"><i class="fa-solid fa-floppy-disk"></i> Save rules</button></div>
    </form>
    <?php foreach ($roles as $rk => $r): if (empty($r['system'])): ?><form id="del-<?= e($rk) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="role_delete"><input type="hidden" name="key" value="<?= e($rk) ?>"></form><?php endif; endforeach; ?>
    <h3 style="margin:18px 0 8px;font-size:18px">Add a role</h3>
    <form method="post" class="panel form-grid cols-4">
      <?= csrf_field() ?><input type="hidden" name="do" value="role_add">
      <div class="fld"><label for="rl">Role name</label><input id="rl" name="label" required placeholder="e.g. Shalimar staff"></div>
      <div class="fld"><label for="rm">Publishing</label><select id="rm" name="mode"><?php foreach (PUBLISH_MODES as $mk => $mv): ?><option value="<?= $mk ?>"><?= e($mv) ?></option><?php endforeach; ?></select></div>
      <div class="fld"><label for="rb">Badge</label><input id="rb" name="badge" placeholder="e.g. Shalimar Staff"></div>
      <div class="fld" style="align-self:end"><button class="btn btn-solid btn-block"><i class="fa-solid fa-plus"></i> Add role</button></div>
    </form>

<?php elseif ($tab === 'projects'):
    $edit = get_str('edit');
    $ep = $edit !== '' ? ($projects[$edit] ?? null) : null;
    $secText = '';
    if ($ep) foreach ($ep['sectors'] as $sec => $bl) $secText .= $sec . ': ' . implode(', ', $bl) . "\n"; ?>
    <h1>Projects</h1><p class="muted" style="margin:0 0 14px">Projects drive the website, the posting form and the market report. Add new Shalimar projects here — no code changes needed.</p>
    <div class="table-wrap"><table class="tbl"><thead><tr><th>Project</th><th>Location</th><th>Unit</th><th>Sectors</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($projects as $p): ?><tr>
        <td><div style="display:flex;align-items:center;gap:10px"><?= project_logo_html($p, 'plogo') ?><b><?= e($p['name']) ?></b></div></td>
        <td><?= e($p['location']) ?></td><td><?= $p['unit'] === 'sqft' ? 'sq ft' : 'marla' ?></td>
        <td style="font-size:12px"><?php foreach ($p['sectors'] as $sec => $bl): ?><div><b><?= e($sec) ?>:</b> <?= e(implode(', ', $bl)) ?></div><?php endforeach; ?></td>
        <td><?= !empty($p['active']) ? '<span class="pill">Visible</span>' : '<span class="pill muted">Hidden</span>' ?></td>
        <td><div class="admin-actions"><a class="btn btn-sm btn-ghost" href="admin-classic.php?tab=projects&amp;edit=<?= e($p['slug']) ?>#pform">Edit</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="project_toggle"><input type="hidden" name="slug" value="<?= e($p['slug']) ?>"><input type="hidden" name="back" value="projects"><button class="btn btn-sm btn-ghost"><?= !empty($p['active']) ? 'Hide' : 'Show' ?></button></form></div></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
    <h3 id="pform" style="margin:18px 0 8px;font-size:18px"><?= $ep ? 'Edit ' . e($ep['name']) : 'Add a new project' ?></h3>
    <form method="post" enctype="multipart/form-data" class="panel form-grid cols-3">
      <?= csrf_field() ?><input type="hidden" name="do" value="project_save"><?php if ($ep): ?><input type="hidden" name="slug" value="<?= e($ep['slug']) ?>"><?php endif; ?>
      <div class="fld"><label for="pjn">Name</label><input id="pjn" name="name" required value="<?= e($ep['name'] ?? '') ?>" placeholder="e.g. Shalimar Heights"></div>
      <div class="fld"><label for="pjc">City</label><input id="pjc" name="city" value="<?= e($ep['city'] ?? 'Sargodha') ?>"></div>
      <div class="fld"><label for="pjl">Location</label><input id="pjl" name="location" value="<?= e($ep['location'] ?? '') ?>"></div>
      <div class="fld"><label for="pjd">Developer</label><input id="pjd" name="developer" value="<?= e($ep['developer'] ?? 'Shalimar Developers') ?>"></div>
      <div class="fld"><label for="pjk">Type</label><input id="pjk" name="kind" value="<?= e($ep['kind'] ?? 'Residential plots') ?>"></div>
      <div class="fld"><label for="pjs">Status</label><input id="pjs" name="status" value="<?= e($ep['status'] ?? 'Booking open') ?>"></div>
      <div class="fld"><label for="pju">Size unit</label><select id="pju" name="unit"><option value="marla">Marla / kanal (plots)</option><option value="sqft"<?= ($ep['unit'] ?? '') === 'sqft' ? ' selected' : '' ?>>Square feet (towers)</option></select></div>
      <div class="fld"><label for="pjf">Price from (display)</label><input id="pjf" name="price_from" value="<?= e($ep['price_from'] ?? '') ?>" placeholder="PKR 25 Lac (5 Marla)"></div>
      <div class="fld"><label for="pjp">Progress %</label><input id="pjp" name="progress" type="number" min="0" max="100" value="<?= (int)($ep['progress'] ?? 0) ?>"></div>
      <div class="fld full"><label for="pjsu">Summary</label><textarea id="pjsu" name="summary" style="min-height:70px"><?= e($ep['summary'] ?? '') ?></textarea></div>
      <div class="fld full"><label for="pjse">Sectors &amp; blocks (one sector per line: <code>Sector name: Block 1, Block 2</code>)</label><textarea id="pjse" name="sectors" placeholder="Sector A: Block 1, Block 2&#10;Sector B: Block 3"><?= e(trim($secText)) ?></textarea></div>
      <div class="fld"><label>Logo (PNG/JPG/WEBP, optional)</label><input type="file" name="logo[]" accept="image/png,image/jpeg,image/webp"></div>
      <div class="fld"><label>Cover image (optional)</label><input type="file" name="cover[]" accept="image/png,image/jpeg,image/webp"></div>
      <div class="fld" style="align-self:end"><button class="btn btn-solid btn-block"><i class="fa-solid fa-floppy-disk"></i> <?= $ep ? 'Save changes' : 'Add project' ?></button></div>
    </form>

<?php elseif ($tab === 'records'):
    $rec = array_slice(store_read('records'), 0, 300); ?>
    <h1>Records log</h1><p class="muted" style="margin:0 0 14px">Every agency upload, approval, transaction and rights change is logged here.</p>
    <div class="table-wrap tall"><table class="tbl"><thead><tr><th>Time</th><th>Action</th><th>Details</th><th>By</th></tr></thead><tbody>
      <?php foreach ($rec as $r): ?><tr><td style="white-space:nowrap"><?= e($r['ts']) ?></td><td><b><?= e($r['action']) ?></b></td><td><?= e($r['details']) ?><?= $r['listing_id'] ? ' <a class="pill muted" href="listing.php?id=' . (int)$r['listing_id'] . '" target="_blank">view</a>' : '' ?></td><td><?= e($r['actor']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <form method="post" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="do" value="reset_demo"><input type="hidden" name="back" value="records">
      <button class="btn btn-danger btn-sm" data-confirm="Reset ALL data to the original demo data? Your changes will be lost."><i class="fa-solid fa-rotate"></i> Reset demo data</button></form>
<?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
