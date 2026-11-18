<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$urls=[];
foreach(['home','buy','rent','projects','insights','agencies']as$route)$urls[]=\App\Libraries\PublicWebsiteUrl::route($route);
foreach(projects()as$project)$urls[]=project_seo_url((string)$project['slug']);
$listings=\App\Libraries\PortalCatalogStore::connect()->publicSearch(\App\Services\PublicListingSearch::filters([]),[],false)['rows'];
foreach($listings as$listing)$urls[]=listing_seo_url($listing);
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n",'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as$url)echo '<url><loc>',htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8'),'</loc></url>';
echo '</urlset>';
