<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$urls=[];
foreach(['index.php','buy.php','rent.php','projects.php','insights.php']as$page)$urls[]=\App\Libraries\PublicWebsiteUrl::make($page);
foreach(\App\Libraries\PortalCatalogStore::connect()->visibleListingIds()as$id)$urls[]=\App\Libraries\PublicWebsiteUrl::make('listing.php',['id'=>$id]);
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n",'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as$url)echo '<url><loc>',htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8'),'</loc></url>';
echo '</urlset>';
