<?php
$active = 'home';
$bodyClass = 'home3';
$heroPhoto = '';
$pageTitle = 'Shalimar Properties — The official property portal of Shalimar Group';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/home/common.php';
$H = ['kicker' => 'One portal · every Shalimar community', 'l1' => 'The home of', 'l2' => '<em>Shalimar Group</em> real estate.', 'lede' => 'Plots, villas, apartments and offices across Shalimar Smart City, Gulberg City and Zalmi X — verified, priced in public, on record.'];
?>
<main id="main">
<?php require __DIR__ . '/includes/home/hero-b.php'; ?>

<?php sec('spotlight', ['kicker' => 'Under the Shalimar umbrella', 'title_h' => 'Signature communities']); ?>
<?php sec('types', []); ?>
<?php sec('market_band', ['title_h' => 'Negotiate with real numbers, <em>not rumours.</em>']); ?>
<?php sec('featured', ['kicker' => 'Fresh & verified', 'title_h' => 'Latest listings']); ?>
<?php sec('stories', []); ?>
<?php sec('steps', ['kicker' => 'The Shalimar standard', 'title_h' => 'A portal built on verification']); ?>
<?php sec('cta_green', ['title_h' => 'Own a Shalimar plot or home? <em>List it in two minutes.</em>']); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/home.js?v=1"></script>
</body>
</html>
