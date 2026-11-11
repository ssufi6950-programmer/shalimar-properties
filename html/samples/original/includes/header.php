<?php
if (!isset($active)) $active = '';
if (!isset($pageTitle)) $pageTitle = 'Shalimar Properties';
$nav = [
  'home' => ['Home', 'index.php', 'fa-house'],
  'buy' => ['Buy', 'buy.php', 'fa-key'],
  'rent' => ['Rent', 'rent.php', 'fa-building'],
  'projects' => ['Projects', 'projects.php', 'fa-city'],
  'agencies' => ['Agencies', 'agencies.php', 'fa-briefcase'],
  'blog' => ['Blog', 'blog.php', 'fa-newspaper'],
  'events' => ['Events', 'events.php', 'fa-calendar-days'],
  'wanted' => ['Wanted', 'wanted.php', 'fa-bullhorn'],
  'registry' => ['Registry', 'registry-calculator.php', 'fa-calculator'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,700;12..96,800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/site.css">
</head>
<body>

<header class="hdr" id="hdr">
  <div class="wrap hdr-in">
    <a href="index.php" class="brand">
      <span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.7V21h13V9.7"/><path d="M10 21v-6h4v6"/></svg></span>
      <span class="brand-text">Shalimar Properties</span>
    </a>

    <nav class="nav" aria-label="Main">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $item[0] ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="hdr-act">
      <a href="#" class="btn btn-ghost">Sign in</a>
      <a href="index.php#list" class="btn btn-solid">List a property</a>
      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<div class="drawer" id="drawer">
  <div class="drawer-top">
    <a href="index.php" class="brand">
      <span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.7V21h13V9.7"/><path d="M10 21v-6h4v6"/></svg></span>
      <span class="brand-text">Shalimar Properties</span>
    </a>
    <button class="drawer-close" id="drawerClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <p class="drawer-label">Browse</p>
  <nav class="drawer-nav">
    <?php foreach ($nav as $key => $item): ?>
      <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>">
        <span class="d-ico"><i class="fa-solid <?= $item[2] ?>"></i></span>
        <span class="d-txt"><?= $item[0] === 'Registry' ? 'Registry Calculator' : $item[0] ?></span>
        <?php if ($active === $key): ?><span class="d-now">Now</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="drawer-actions">
    <a href="#" class="btn btn-ghost">Sign in</a>
    <a href="index.php#list" class="btn btn-solid">List a property</a>
  </div>
</div>
<div class="mobile-menu-overlay" id="drawerOverlay"></div>
