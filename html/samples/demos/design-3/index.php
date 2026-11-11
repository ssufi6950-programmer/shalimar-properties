<?php
$active = 'home';
$bodyClass = 'home3 cD';
$heroPhoto = '';
$pageTitle = 'Shalimar Properties — The official property portal of Shalimar Group';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/home/common.php';
$H = ['kicker' => 'The official Shalimar Group portal', 'l1' => 'Buy, rent &amp; invest', 'l2' => 'across <em>Shalimar Group</em>.', 'lede' => 'One place for every Shalimar community — verified plots, homes and offices, transparent prices and authorized realtors only.'];
?>
<main id="main">
<?php $heroMode = 'd'; require __DIR__ . '/includes/home/hero.php'; ?>

<?php sec('tri', ['kicker' => 'The Shalimar Group', 'title_h' => 'Three communities, <em>one promise</em>', 'lede' => 'Every project is planned, built and managed by Shalimar Group.']); ?>
<?php sec('types', []); ?>
<?php sec('featured', ['kicker' => 'Verified today', 'title_h' => 'Latest listings']); ?>
<?php sec('stories', []); ?>
<?php sec('market_band', ['title_h' => 'Real prices from <em>real deals.</em>']); ?>
<?php sec('cta_green', ['title_h' => 'Ready to list? <em>Go live in two minutes.</em>']); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/home.js?v=1"></script>
</body>
</html>
