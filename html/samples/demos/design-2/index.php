<?php
$active = 'home';
$bodyClass = 'home3 cD cG';
$heroPhoto = '';
$pageTitle = 'Shalimar Properties — The official property portal of Shalimar Group';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/home/common.php';
$H = ['kicker' => 'Shalimar Properties', 'l1' => 'Homes, plots &amp; offices —', 'l2' => 'one <em>trusted</em> portal.', 'lede' => 'Search verified property across Shalimar Smart City, Gulberg City and Zalmi X, with real market prices behind every listing.'];
?>
<main id="main">
<?php $heroMode = 'g'; require __DIR__ . '/includes/home/hero.php'; ?>

<?php sec('accordion', ['title_h' => '<h2>Explore the communities</h2>', 'lede' => 'Hover — or tap — a community to open it.']); ?>
<?php sec('featured', ['kicker' => 'Just listed', 'title_h' => 'Fresh on the market', 'lede' => 'Every ad here is from a verified owner or an authorized agency.']); ?>
<?php sec('market_bars', ['title_h' => 'Prices, <mark>in public.</mark>']); ?>
<?php sec('stories', []); ?>
<?php sec('steps', ['kicker' => 'How it works', 'title_h' => 'Four rules that keep every listing honest']); ?>
<?php sec('cta_grid', ['title_h' => 'Sell or rent with Shalimar.<br><mark>Live in two minutes.</mark>']); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/home.js?v=1"></script>
</body>
</html>
