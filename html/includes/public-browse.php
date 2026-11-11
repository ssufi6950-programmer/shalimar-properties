<?php
// Shared by the established buy.php and rent.php URLs; no parallel application.
require_once __DIR__.'/bootstrap.php';
$active=$browsePurpose==='rent'?'rent':'buy';$isRent=$browsePurpose==='rent';$route=$active.'.php';
$filters=\App\Services\PublicListingSearch::filters($_GET,$browsePurpose);
$projects=projects();
if($filters['project']!==''&&!isset($projects[$filters['project']]))$filters['errors'][]='Choose an available project.';
if($filters['sector']!==''&&(!isset($projects[$filters['project']]['sectors'][$filters['sector']])))$filters['errors'][]='Choose a category in the selected project.';
if($filters['block']!==''&&!in_array($filters['block'],$projects[$filters['project']]['sectors'][$filters['sector']]??[],true))$filters['errors'][]='Choose a subcategory in the selected project.';
$result=\App\Libraries\PortalCatalogStore::connect()->publicSearch($filters);
if($filters['errors'])http_response_code(400);
$pageTitle=($isRent?'Properties For Rent':'Properties For Sale').' — Shalimar Properties';
$metaDesc=$isRent?'Explore approved rental properties with monthly rent, property details and the selected seller’s public contact.':'Explore approved properties for sale with asking prices, property details and the selected seller’s public contact.';
$canonical=\App\Libraries\PublicWebsiteUrl::make($route,$result['page']>1?['page'=>$result['page']]:[]);
$robots=($_GET||$filters['errors'])?'noindex,follow':'index,follow';
require __DIR__.'/header.php';
$link=static function(int $page)use($filters,$route):string{$query=$filters;unset($query['errors'],$query['available']);$query['page']=$page;return $route.'?'.http_build_query(array_filter($query,static fn($v)=>$v!==null&&$v!==''&&$v!==false),'','&',PHP_QUERY_RFC3986);};
?>
<main id="main" class="public-discovery">
<section class="page-hero"><div class="wrap">
  <div class="crumbs"><a href="index.php">Home</a><span>/</span><span><?= $isRent?'Rent':'Buy' ?></span></div>
  <h1>Properties for <?= $isRent?'rent':'sale' ?></h1>
  <p class="lede"><?= $isRent?'Explore approved homes, plots and commercial spaces. Every price is monthly rent.':'Explore approved homes, plots and commercial spaces with clear asking prices and seller details.' ?></p>
  <nav class="public-purpose" aria-label="Listing purpose"><a class="btn <?= !$isRent?'btn-solid':'btn-ghost' ?>" href="buy.php" <?= !$isRent?'aria-current="page"':'' ?>>For Sale</a><a class="btn <?= $isRent?'btn-solid':'btn-ghost' ?>" href="rent.php" <?= $isRent?'aria-current="page"':'' ?>>For Rent</a></nav>
  <?php if($filters['errors']): ?><div class="alert alert-info" role="alert"><?= e(implode(' ',array_unique($filters['errors']))) ?> <a href="<?= $route ?>">Reset filters</a></div><?php endif ?>
  <form class="find-box" method="get" action="<?= $route ?>" aria-label="Search <?= $isRent?'rental':'sale' ?> properties">
    <div class="find-grid public-filter-grid">
      <div class="find-field"><label for="browse-q">Location or property</label><input id="browse-q" name="q" type="search" maxlength="120" value="<?= e($filters['q']) ?>" placeholder="Property number, sector or block"></div>
      <div class="find-field"><label for="browse-project">Project</label><select id="browse-project" name="project"><option value="">All projects</option><?php foreach($projects as$p): ?><option value="<?= e($p['slug']) ?>" <?= $filters['project']===$p['slug']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach ?></select></div>
      <div class="find-field"><label for="browse-type">Property type</label><select id="browse-type" name="type"><option value="">All property types</option><?php foreach(CATEGORIES as$type): ?><option value="<?= $type ?>" <?= $filters['type']===$type?'selected':'' ?>><?= e(category_label($type)) ?></option><?php endforeach ?></select></div>
      <div class="find-field"><label for="browse-sector">Category / sector</label><select id="browse-sector" name="sector" data-depends="browse-project"><option value="">All categories</option><?php foreach($projects as$p)foreach($p['sectors']as$sector=>$blocks): ?><option value="<?= e($sector) ?>" data-project="<?= e($p['slug']) ?>" <?= $filters['sector']===$sector&&$filters['project']===$p['slug']?'selected':'' ?>><?= e($p['tree'][$sector]['label']??$sector) ?></option><?php endforeach ?></select></div>
      <div class="find-field"><label for="browse-block">Subcategory / block</label><select id="browse-block" name="block" data-depends="browse-sector" data-attr="sector"><option value="">All subcategories</option><?php foreach($projects as$p)foreach($p['sectors']as$sector=>$blocks)foreach($blocks as$block): ?><option value="<?= e($block) ?>" data-project="<?= e($p['slug']) ?>" data-sector="<?= e($sector) ?>" <?= $filters['block']===$block&&$filters['project']===$p['slug']?'selected':'' ?>><?= e($p['tree'][$sector]['subs'][$block]['label']??$block) ?></option><?php endforeach ?></select></div>
      <div class="find-field"><label for="browse-budget"><?= $isRent?'Monthly rent':'Sale budget' ?></label><select id="browse-budget" name="budget"><?php foreach($isRent?[''=>'Any monthly rent','r-u40'=>'Under PKR 40,000','r-40-80'=>'PKR 40,000–80,000','r-80+'=>'PKR 80,000+']:[''=>'Any sale price','u50l'=>'Under PKR 50 Lac','50l-1c'=>'PKR 50 Lac–1 Crore','1c-2c'=>'PKR 1–2 Crore','2c-5c'=>'PKR 2–5 Crore','5c+'=>'PKR 5 Crore+']as$key=>$label): ?><option value="<?= $key ?>" <?= $filters['budget']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
      <?php foreach(['min'=>'Minimum','max'=>'Maximum']as$key=>$label): ?><div class="find-field"><label for="browse-<?= $key ?>"><?= $label ?> <?= $isRent?'rent / month':'sale price' ?> (PKR)</label><input id="browse-<?= $key ?>" name="<?= $key ?>" type="number" min="0" max="999999999999" step="0.01" value="<?= e($filters[$key]) ?>" placeholder="Any"></div><?php endforeach ?>
      <div class="find-field"><label for="browse-unit">Area unit</label><select id="browse-unit" name="unit"><?php foreach([''=>'Any unit','marla'=>'Marla','sqft'=>'Square feet']as$key=>$label): ?><option value="<?= $key ?>" <?= $filters['unit']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div>
      <?php foreach(['min_area'=>'Minimum area','max_area'=>'Maximum area']as$key=>$label): ?><div class="find-field"><label for="browse-<?= $key ?>"><?= $label ?></label><input id="browse-<?= $key ?>" name="<?= $key ?>" type="number" min="0" step="0.01" value="<?= e($filters[$key]) ?>" placeholder="Any"></div><?php endforeach ?>
      <div class="find-field"><label for="browse-sort">Sort by</label><select id="browse-sort" name="sort"><?php foreach(['newest'=>'Newest approved','price_asc'=>$isRent?'Monthly rent: low to high':'Sale price: low to high','price_desc'=>$isRent?'Monthly rent: high to low':'Sale price: high to low','area_asc'=>'Area: low to high','area_desc'=>'Area: high to low']as$key=>$label): ?><option value="<?= $key ?>" <?= $filters['sort']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div>
    </div>
    <div class="find-extra"><label class="find-chip" for="browse-verified"><input id="browse-verified" type="checkbox" name="verified" value="1" <?= $filters['verified']?'checked':'' ?>>Ownership verified at publication</label><label class="find-chip" for="browse-beds">Bedrooms <select id="browse-beds" name="beds"><?php foreach([0=>'Any',2=>'2+',3=>'3+',4=>'4+',5=>'5+']as$key=>$label): ?><option value="<?= $key ?>" <?= $filters['beds']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label><label class="find-chip" for="browse-size">Per page <select id="browse-size" name="per_page"><?php foreach([12,24,48]as$n): ?><option value="<?= $n ?>" <?= $filters['per_page']===$n?'selected':'' ?>><?= $n ?></option><?php endforeach ?></select></label><?php if($filters['agency']): ?><input type="hidden" name="agency" value="<?= (int)$filters['agency'] ?>"><?php endif ?><button class="btn btn-solid" type="submit">Search properties</button><a class="find-chip" href="<?= $route ?>">Reset filters</a></div>
  </form>
</div></section>
<section class="sec-tight"><div class="wrap">
  <div class="result-bar"><div><h2><?= number_format($result['total']) ?> <?= $isRent?'rental properties':'properties for sale' ?></h2><p>Admin-approved, currently published listings. <?= $isRent?'Amounts are monthly rent.':'Amounts are asking sale prices.' ?></p></div><a href="post-property.php<?= $isRent?'?purpose=rent':'' ?>" class="btn btn-ghost btn-sm"><?= $isRent?'Rent out':'Sell' ?> your property</a></div>
  <?php if($result['rows']): ?><div class="listing-grid"><?php foreach($result['rows']as$i=>$listing)echo render_card($listing,($i%3)*60); ?></div><?php else: ?><div class="empty-note" role="status">No approved <?= $isRent?'rentals':'sale properties' ?> match these filters. <a href="<?= $route ?>">Clear filters</a>.</div><?php endif ?>
  <?php if($result['pages']>1): ?><nav class="public-pagination" aria-label="Property result pages"><?php if($result['page']>1): ?><a class="btn btn-ghost" rel="prev" href="<?= e($link($result['page']-1)) ?>">Previous</a><?php endif ?><span aria-live="polite">Page <?= $result['page'] ?> of <?= $result['pages'] ?></span><?php if($result['page']<$result['pages']): ?><a class="btn btn-ghost" rel="next" href="<?= e($link($result['page']+1)) ?>">Next</a><?php endif ?></nav><?php endif ?>
</div></section></main>
<?php require __DIR__.'/footer.php'; ?>
</body></html>
