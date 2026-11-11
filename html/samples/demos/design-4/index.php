<?php
$active = 'home';
$bodyClass = 'home3 cD cD2';
$heroPhoto = '';
$pageTitle = 'Shalimar Properties — The official property portal of Shalimar Group';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/home/common.php';
$H = ['kicker' => 'Shalimar Group real estate', 'l1' => 'Your next address,', 'l2' => 'from the people who <em>built the city.</em>', 'lede' => 'Verified property in Shalimar Smart City, Gulberg City and Zalmi X — listed by owners and authorized realtors, with every deal on record.'];
?>
<main id="main">
<?php require __DIR__ . '/includes/home/hero-d2.php'; ?>

<?php sec('featured', ['kicker' => 'Live right now', 'title_h' => 'Fresh on Shalimar', 'lede' => 'Posted by verified owners and authorized agencies — updated the moment a deal closes.']); ?>
<?php sec('rows', ['kicker' => 'Our communities', 'title_h' => 'Where Shalimar builds']); ?>
<?php sec('market_min', ['title_h' => 'Market numbers, <em>updated daily.</em>']); ?>
<?php sec('stories', []); ?>
<?php sec('steps', ['kicker' => 'Why Shalimar Properties', 'title_h' => 'Built on verification']); ?>
<?php sec('cta_grid', ['title_h' => 'Own a Shalimar property?<br><mark>Put it in front of buyers.</mark>']); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/home.js?v=1"></script>
</body>
</html>
