<?php
$active = 'home';
$bodyClass = 'home3 cD cH cH2';
$heroPhoto = '';
$pageTitle = 'Shalimar Properties — The official property portal of Shalimar Group';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/home/common.php';
$H = ['kicker' => 'Shalimar Group · Official property portal', 'l1' => 'Find your place', 'l2' => 'in the <em>Shalimar</em> family.', 'lede' => 'Plots, homes, apartments and offices across every Shalimar Group community — posted only by verified owners and authorized realtors.'];
?>
<main id="main">
<?php $heroMode = 'h2'; require __DIR__ . '/includes/home/hero.php'; ?>

<?php sec('chapters', ['num' => 'I.', 'kicker' => 'The communities', 'title_h' => 'Three addresses, one standard']); ?>
<?php sec('featured', ['kicker' => 'Fresh & verified', 'title_h' => 'New this week', 'lede' => 'Hand-checked listings from Shalimar owners and authorized realtors.']); ?>
<?php sec('stories', []); ?>
<?php sec('types', []); ?>
<?php sec('market_min', ['title_h' => 'Know the market <em>before you sign.</em>']); ?>
<?php sec('cta_arch', ['title_h' => 'Own a Shalimar plot or home? <em>List it in two minutes.</em>']); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/home.js?v=1"></script>
</body>
</html>
