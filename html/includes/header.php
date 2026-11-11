<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/design.php';
require_once dirname(__DIR__, 2) . '/app/Libraries/PortalLinks.php';
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
];
$portalLinks = [
  'customer' => ['Customer Portal', \App\Libraries\PortalLinks::portal('customer/login'), 'fa-user'],
  'agency' => ['Agency Portal', \App\Libraries\PortalLinks::portal('agency/login'), 'fa-briefcase'],
];
$moreActive = isset($more[$active]);
// Photo banner behind the compact page header (set $heroPhoto = '' in a page to disable)
if (!isset($heroPhoto)) {
  $heroPhoto = [
    'buy' => 'gc-aerial', 'rent' => 'gc-homes', 'projects' => 'ssc-aerial', 'insights' => 'ssc-interchange',
    'agencies' => 'ssc-business-bay', 'blog' => 'ssc-boulevard', 'events' => 'ssc-gate-render', 'wanted' => 'gc-boulevard',
    'registry' => 'ssc-gate-aerial', 'post' => 'ssc-entrance',
  ][$active] ?? '';
}
if (!DESIGN_BANNER) $heroPhoto = '';
$bodyClasses = 'dz dz-' . DESIGN_ID . ' ' . trim(($bodyClass ?? '') . ($heroPhoto !== '' ? ' photo-hero' : ''));
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
$bodyStyle = $heroPhoto !== '' ? ' style="--hero-img:url(\'' . e($__base . photo($heroPhoto, true)) . '\')"' : '';
if(!isset($canonical)&&in_array($active,['home','buy','rent','projects','insights'],true))$canonical=\App\Libraries\PublicWebsiteUrl::make($active==='home'?'index.php':$active.'.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#0E4B31">
<?php if(isset($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif ?>
<?php if(isset($robots)): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif ?>
<?php if(isset($social)): ?><meta property="og:type" content="website"><meta property="og:title" content="<?= e($social['title']) ?>"><meta property="og:description" content="<?= e($social['description']) ?>"><meta property="og:url" content="<?= e($social['url']) ?>"><meta property="og:image" content="<?= e(\App\Libraries\PublicWebsiteUrl::image($social['image'])) ?>"><?php endif ?>
<?php if(isset($seo)): ?><script type="application/ld+json"><?= json_encode($seo,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script><?php endif ?>
<link rel="icon" href="assets/images/logos/favicon.ico?v=8" sizes="any">
<link rel="icon" href="assets/images/logos/favicon.svg?v=8" type="image/svg+xml">
<link rel="icon" href="assets/images/logos/favicon-32.png?v=8" sizes="32x32" type="image/png">
<link rel="icon" href="assets/images/logos/favicon-16.png?v=8" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="assets/images/logos/apple-touch-icon.png?v=8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/site.css?v=8">
<?php if ($active === 'home'): ?><link rel="stylesheet" href="assets/css/home-base.css?v=erp-market-20261110"><?php endif; ?>
<link rel="stylesheet" href="assets/css/design.css?v=9">
<link rel="stylesheet" href="assets/css/subprojects.css?v=4">
<link rel="stylesheet" href="assets/css/public-listings.css?v=<?= filemtime(__DIR__.'/../assets/css/public-listings.css') ?>">
<script src="assets/js/public-listings.js?v=<?= filemtime(__DIR__.'/../assets/js/public-listings.js') ?>" defer></script>
</head>
<body class="<?= e($bodyClasses) ?>"<?= $bodyStyle ?>>
<a href="#main" class="sr-only">Skip to content</a>

<header class="hdr" id="hdr">
  <div class="wrap hdr-in">
    <a href="index.php" class="brand" aria-label="Shalimar Properties — home">
      <img class="logo logo-c" src="assets/images/logos/sp-logo.svg?v=7" alt="Shalimar Properties" width="189" height="46">
      <img class="logo logo-w" src="assets/images/logos/sp-logo-white.svg?v=7" alt="" width="189" height="46">
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
      <?php foreach ($portalLinks as $item): ?>
        <a href="<?= e($item[1]) ?>" class="btn btn-ghost btn-sm"><i class="fa-solid <?= e($item[2]) ?>" aria-hidden="true"></i> <?= e($item[0]) ?></a>
      <?php endforeach; ?>
      <a href="post-property.php" class="btn btn-solid btn-sm"><i class="fa-solid fa-plus"></i> Post property</a>
      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="drawer"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<div class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Menu">
  <div class="drawer-top">
    <a href="index.php" class="brand" aria-label="Shalimar Properties — home"><img class="logo" src="assets/images/logos/sp-logo.svg?v=7" alt="Shalimar Properties" width="165" height="40"></a>
    <button class="drawer-close" id="drawerClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <a href="post-property.php" class="btn btn-solid btn-block"><i class="fa-solid fa-plus"></i> Post a property</a>
  <p class="drawer-label">Portals</p>
  <nav class="drawer-nav" aria-label="Portals">
    <?php foreach ($portalLinks as $item): ?>
      <a href="<?= e($item[1]) ?>"><span class="d-ico"><i class="fa-solid <?= e($item[2]) ?>" aria-hidden="true"></i></span><span><?= e($item[0]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <p class="drawer-label">Explore</p>
  <nav class="drawer-nav">
    <?php foreach (array_merge($nav, $more) as $key => $item): ?>
      <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>">
        <span class="d-ico"><i class="fa-solid <?= $item[2] ?>"></i></span><span><?= e($item[0]) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="drawer-contact"><i class="fa-solid fa-headset"></i> Help line: <a href="tel:<?= e(SITE_TEL) ?>"><?= e(SITE_PHONE) ?></a></div>
</div>
<div class="mobile-menu-overlay" id="drawerOverlay"></div>

<nav class="tabbar" aria-label="App navigation">
  <a href="index.php" class="<?= $active === 'home' ? 'on' : '' ?>"><i class="fa-solid fa-house"></i>Home</a>
  <a href="buy.php" class="<?= $active === 'buy' ? 'on' : '' ?>"><i class="fa-solid fa-key"></i>Buy</a>
  <a href="rent.php" class="<?= $active === 'rent' ? 'on' : '' ?>"><i class="fa-solid fa-building"></i>Rent</a>
  <a href="projects.php" class="<?= $active === 'projects' ? 'on' : '' ?>"><i class="fa-solid fa-city"></i>Projects</a>
  <button type="button" id="tabMore" class="<?= !in_array($active, ['home', 'buy', 'rent', 'projects'], true) ? 'on' : '' ?>" aria-controls="drawer"><i class="fa-solid fa-grip"></i>More</button>
</nav>
