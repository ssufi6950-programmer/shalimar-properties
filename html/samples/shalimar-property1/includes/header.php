<?php
require_once __DIR__ . '/bootstrap.php';
if (!isset($active)) $active = '';
if (!isset($pageTitle)) $pageTitle = 'Shalimar Properties';
if (!isset($metaDesc)) $metaDesc = 'Shalimar Properties — verified plots, homes, rentals and project units in Shalimar Smart City, Gulberg City and Zalmi X.';
$nav = [
  'home'     => ['Home', 'index.php', 'fa-house'],
  'buy'      => ['Buy', 'buy.php', 'fa-key'],
  'rent'     => ['Rent', 'rent.php', 'fa-building'],
  'projects' => ['Projects', 'projects.php', 'fa-city'],
  'insights' => ['Market Insights', 'insights.php', 'fa-chart-line'],
  'agencies' => ['Agencies', 'agencies.php', 'fa-briefcase'],
];
$more = [
  'blog'     => ['Blog', 'blog.php', 'fa-newspaper'],
  'events'   => ['Events', 'events.php', 'fa-calendar-days'],
  'wanted'   => ['Wanted', 'wanted.php', 'fa-bullhorn'],
  'registry' => ['Registry Calculator', 'registry-calculator.php', 'fa-calculator'],
  'admin'    => ['Admin Panel', 'admin.php', 'fa-user-shield'],
];
$moreActive = isset($more[$active]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#0E4B31">
<link rel="icon" href="assets/images/logos/shalimar-mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/site.css?v=2">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a href="#main" class="sr-only">Skip to content</a>

<header class="hdr" id="hdr">
  <div class="wrap hdr-in">
    <a href="index.php" class="brand" aria-label="Shalimar Properties — home">
      <img src="assets/images/logos/shalimar-mark.svg" alt="" width="38" height="38">
      <span class="brand-text"><b>Shalimar</b><small>PROPERTIES</small></span>
    </a>

    <nav class="nav" aria-label="Main">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($item[0]) ?><?= $key === 'insights' ? ' <span class="new">NEW</span>' : '' ?></a>
      <?php endforeach; ?>
      <div class="nav-more<?= $moreActive ? ' active' : '' ?>">
        <button type="button" aria-haspopup="true">More <i class="fa-solid fa-chevron-down"></i></button>
        <div class="nav-drop">
          <?php foreach ($more as $key => $item): ?>
            <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>"><i class="fa-solid <?= $item[2] ?>"></i><?= e($item[0]) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </nav>

    <div class="hdr-act">
      <a href="admin.php" class="btn btn-ghost btn-sm"><?php if (is_admin()): ?><i class="fa-solid fa-gauge"></i> Admin<?php else: ?><i class="fa-regular fa-user"></i> Sign in<?php endif; ?></a>
      <a href="post-property.php" class="btn btn-solid btn-sm"><i class="fa-solid fa-plus"></i> Post property</a>
      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="drawer"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<div class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Menu">
  <div class="drawer-top">
    <a href="index.php" class="brand"><img src="assets/images/logos/shalimar-mark.svg" alt="" width="34" height="34"><span class="brand-text"><b>Shalimar</b><small>PROPERTIES</small></span></a>
    <button class="drawer-close" id="drawerClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <a href="post-property.php" class="btn btn-solid btn-block"><i class="fa-solid fa-plus"></i> Post a property</a>
  <p class="drawer-label">Explore</p>
  <nav class="drawer-nav">
    <?php foreach (array_merge($nav, $more) as $key => $item): ?>
      <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>">
        <span class="d-ico"><i class="fa-solid <?= $item[2] ?>"></i></span><span><?= e($item[0]) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="drawer-contact"><i class="fa-solid fa-headset"></i> Help line: <a href="tel:<?= e(digits(SITE_PHONE)) ?>"><?= e(SITE_PHONE) ?></a></div>
</div>
<div class="mobile-menu-overlay" id="drawerOverlay"></div>

<nav class="tabbar" aria-label="App navigation">
  <a href="index.php" class="<?= $active === 'home' ? 'on' : '' ?>"><i class="fa-solid fa-house"></i>Home</a>
  <a href="buy.php" class="<?= $active === 'buy' ? 'on' : '' ?>"><i class="fa-solid fa-key"></i>Buy</a>
  <a href="rent.php" class="<?= $active === 'rent' ? 'on' : '' ?>"><i class="fa-solid fa-building"></i>Rent</a>
  <a href="projects.php" class="<?= $active === 'projects' ? 'on' : '' ?>"><i class="fa-solid fa-city"></i>Projects</a>
  <button type="button" id="tabMore" class="<?= !in_array($active, ['home', 'buy', 'rent', 'projects'], true) ? 'on' : '' ?>" aria-controls="drawer"><i class="fa-solid fa-grip"></i>More</button>
</nav>
