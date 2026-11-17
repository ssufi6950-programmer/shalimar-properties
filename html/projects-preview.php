<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

try {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $apiPath = \App\Libraries\PortalLinks::portal('api/projects');
    $scriptPath = \App\Libraries\PortalLinks::portal('assets/projects/projects-json-preview.js');
    $customerPath = \App\Libraries\PortalLinks::portal('customer/login');
    $agencyPath = \App\Libraries\PortalLinks::portal('agency/login');
} catch (\Throwable $error) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Project preview configuration is temporarily unavailable.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Projects Preview — Shalimar Properties</title>
<meta name="description" content="Shalimar Properties — verified plots, homes, rentals and project units in Shalimar Smart City, Gulberg City and Zalmi X.">
<meta name="theme-color" content="#0E4B31">
<meta name="robots" content="noindex,nofollow"><link rel="icon" href="assets/images/logos/favicon.ico?v=8" sizes="any">
<link rel="icon" href="assets/images/logos/favicon.svg?v=8" type="image/svg+xml">
<link rel="icon" href="assets/images/logos/favicon-32.png?v=8" sizes="32x32" type="image/png">
<link rel="icon" href="assets/images/logos/favicon-16.png?v=8" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="assets/images/logos/apple-touch-icon.png?v=8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/site.css?v=1794413341">
<link rel="stylesheet" href="assets/css/design.css?v=9">
<link rel="stylesheet" href="assets/css/subprojects.css?v=4">
<link rel="stylesheet" href="assets/css/public-listings.css?v=1794384215">
<script src="assets/js/public-listings.js?v=1794417507" defer></script>
<style>
.json-preview-tools { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:16px 0; }
#jsonProjectsStatus { color:var(--muted); font-size:13px; }
#jsonProjectsStatus[data-error="true"] { color:#a33b2e; }
.json-project-version { font-size:11px; color:var(--muted); margin-bottom:8px; }
.json-project-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:14px; }
.json-project-actions .price { font-size:17px; margin-right:auto; }
.json-project-progress-label { display:flex; justify-content:space-between; font-size:12.5px; color:var(--muted); }
.json-project-progress-label strong { color:var(--ink); }
.json-project-lightbox { max-width:94vw; max-height:94vh; padding:16px; border:0; border-radius:16px; }
.json-project-lightbox::backdrop { background:#102b20b3; }
.json-project-lightbox img { display:block; max-width:85vw; max-height:80vh; object-fit:contain; }
.json-project-lightbox button { display:block; margin:0 0 8px auto; }
[hidden] { display:none !important; }
</style>
</head>
<body class="dz dz-5 photo-hero" style="--hero-img:url('/assets/images/photos/ssc-aerial.webp')">
<a href="#main" class="sr-only">Skip to content</a>

<header class="hdr" id="hdr">
  <div class="wrap hdr-in">
    <a href="index.php" class="brand" aria-label="Shalimar Properties — home">
      <img class="logo logo-c" src="assets/images/logos/sp-logo.svg?v=7" alt="Shalimar Properties" width="189" height="46">
      <img class="logo logo-w" src="assets/images/logos/sp-logo-white.svg?v=7" alt="" width="189" height="46">
    </a>

    <nav class="nav" aria-label="Main">
              <a href="index.php" class="">Home</a>
              <a href="buy.php" class="">Buy</a>
              <a href="rent.php" class="">Rent</a>
              <a href="projects.php" class="active" aria-current="page">Projects</a>
              <a href="insights.php" class="">Market Insights <span class="new">NEW</span></a>
              <a href="agencies.php" class="">Agencies</a>
            <div class="nav-more">
        <button type="button" aria-haspopup="true">More <i class="fa-solid fa-chevron-down"></i></button>
        <div class="nav-drop">
                      <a href="blog.php" class=""><i class="fa-solid fa-newspaper"></i>Blog</a>
                      <a href="events.php" class=""><i class="fa-solid fa-calendar-days"></i>Events</a>
                      <a href="wanted.php" class=""><i class="fa-solid fa-bullhorn"></i>Wanted</a>
                  </div>
      </div>
    </nav>

    <div class="hdr-act">
              <a href="<?= htmlspecialchars($customerPath, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-user" aria-hidden="true"></i> Customer Portal</a>
              <a href="<?= htmlspecialchars($agencyPath, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Agency Portal</a>
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
          <a href="<?= htmlspecialchars($customerPath, ENT_QUOTES, 'UTF-8') ?>"><span class="d-ico"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Customer Portal</span></a>
          <a href="<?= htmlspecialchars($agencyPath, ENT_QUOTES, 'UTF-8') ?>"><span class="d-ico"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span><span>Agency Portal</span></a>
      </nav>
  <p class="drawer-label">Explore</p>
  <nav class="drawer-nav">
          <a href="index.php" class="">
        <span class="d-ico"><i class="fa-solid fa-house"></i></span><span>Home</span>
      </a>
          <a href="buy.php" class="">
        <span class="d-ico"><i class="fa-solid fa-key"></i></span><span>Buy</span>
      </a>
          <a href="rent.php" class="">
        <span class="d-ico"><i class="fa-solid fa-building"></i></span><span>Rent</span>
      </a>
          <a href="projects.php" class="active">
        <span class="d-ico"><i class="fa-solid fa-city"></i></span><span>Projects</span>
      </a>
          <a href="insights.php" class="">
        <span class="d-ico"><i class="fa-solid fa-chart-line"></i></span><span>Market Insights</span>
      </a>
          <a href="agencies.php" class="">
        <span class="d-ico"><i class="fa-solid fa-briefcase"></i></span><span>Agencies</span>
      </a>
          <a href="blog.php" class="">
        <span class="d-ico"><i class="fa-solid fa-newspaper"></i></span><span>Blog</span>
      </a>
          <a href="events.php" class="">
        <span class="d-ico"><i class="fa-solid fa-calendar-days"></i></span><span>Events</span>
      </a>
          <a href="wanted.php" class="">
        <span class="d-ico"><i class="fa-solid fa-bullhorn"></i></span><span>Wanted</span>
      </a>
      </nav>
  <div class="drawer-contact"><i class="fa-solid fa-headset"></i> Help line: <a href="tel:+9248111222084">UAN +92-48-111-222-084</a></div>
</div>
<div class="mobile-menu-overlay" id="drawerOverlay"></div>

<nav class="tabbar" aria-label="App navigation">
  <a href="index.php" class=""><i class="fa-solid fa-house"></i>Home</a>
  <a href="buy.php" class=""><i class="fa-solid fa-key"></i>Buy</a>
  <a href="rent.php" class=""><i class="fa-solid fa-building"></i>Rent</a>
  <a href="projects.php" class="on"><i class="fa-solid fa-city"></i>Projects</a>
  <button type="button" id="tabMore" class="" aria-controls="drawer"><i class="fa-solid fa-grip"></i>More</button>
</nav>
<main id="main">
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="index.php">Home</a><span>/</span><span>Projects preview</span></div>
    <h1>Shalimar Group projects</h1>
    <p class="lede">Explore each project, its categories and blocks.</p>
    <div class="json-preview-tools">
      <span class="pill gold">Separate preview</span>
      <button type="button" class="btn btn-ghost btn-sm" id="jsonProjectsRefresh">Refresh saved content</button>
      <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($apiPath . '?format=document', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View JSON</a>
    </div>
    <p id="jsonProjectsStatus" role="status">Loading saved projects…</p>
    <div class="proj-tabs chips-scroll" id="jsonProjectTabs" style="margin-top:14px"></div>
  </div>
</section>
<section class="sec-tight" style="padding-top:35px">
  <div class="wrap">
    <div class="project-list" id="jsonProjects"></div>
    <div class="empty-note" id="jsonProjectsEmpty" hidden>
      No complete saved project pages are available for this selection. Save the project in Admin and refresh this preview. The project must also be publicly eligible.
    </div>
  </div>
</section>
<section class="sec-tight">
  <div class="wrap">
    <div class="cta-box rv rv-z">
      <div><h2>Investor briefing, backed by real numbers</h2><p>Explore recorded activity and available properties in our communities.</p></div>
      <div class="cta-actions"><a href="insights.php" class="btn btn-light"><i class="fa-solid fa-chart-line"></i> Open market insights</a></div>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div class="f-grid">
      <div>
        <a href="index.php" class="f-logo" aria-label="Shalimar Properties — home"><img src="assets/images/logos/sp-logo-white.svg?v=7" alt="Shalimar Properties" width="222" height="54"></a>
        <p>The official property portal of Shalimar Group — verified plots, homes, rentals and project units, posted only by owners and authorized realtors.</p>
        <div class="f-proj" id="jsonFooterProjects"></div>
        <div class="f-subp" id="jsonFooterCategories"></div>
        <div class="socials">
          <a href="tel:+9248111222084" aria-label="Call us"><i class="fa-solid fa-phone"></i></a>
          <a href="mailto:info@shalimarco.com" aria-label="Email us"><i class="fa-solid fa-envelope"></i></a>
        </div>
      </div>
      <div class="f-col">
        <h5>Browse</h5>
        <a href="buy.php">Plots &amp; homes for sale</a>
        <a href="rent.php">Homes for rent</a>
        <a href="projects.php">Shalimar projects</a>
        <a href="insights.php">Market insights</a>
        <a href="wanted.php">Wanted ads</a>
      </div>
      <div class="f-col">
        <h5>Owners &amp; realtors</h5>
        <a href="post-property.php">Post a property</a>
        <a href="agencies.php">Authorized agencies</a>
        <a href="events.php">Events</a>
        <a href="<?= htmlspecialchars($customerPath, ENT_QUOTES, 'UTF-8') ?>">Customer Portal</a>
        <a href="<?= htmlspecialchars($agencyPath, ENT_QUOTES, 'UTF-8') ?>">Agency Portal</a>
      </div>
      <div class="f-col">
        <h5>Contact</h5>
        <a href="tel:+9248111222084"><i class="fa-solid fa-phone"></i> UAN +92-48-111-222-084</a><a href="tel:+92483722644"><i class="fa-solid fa-phone"></i> 048-3722644</a><a href="tel:+92483723326"><i class="fa-solid fa-phone"></i> 048-3723326</a>        <a href="mailto:info@shalimarco.com"><i class="fa-solid fa-envelope"></i> info@shalimarco.com</a>
        <span style="display:block;font-size:12.5px;color:rgba(221,226,222,.6);margin-top:6px"><i class="fa-solid fa-location-dot"></i> Head Office: 2km Mall Road, Sargodha</span>
      </div>
    </div>
    <div class="f-bot">
      <span>© 2026 Shalimar Properties · All rights reserved. </span>
      <span><a href="insights.php">Market report</a> · <a href="post-property.php">List with us</a></span>
    </div>
  </div>
</footer>

<button class="up" id="up" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>
<script src="assets/js/site.js?v=4"></script>
<script>
window.JsonProjectPreview = <?= json_encode(['api' => $apiPath], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8') ?>?v=1"></script>
</body>
</html>
