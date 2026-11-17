<?php
// Shared navigation metadata; no mock records or database access.
$adminNav = [
 'Overview' => ['dashboard'=>['Dashboard','grid-1x2'],'analytics'=>['Analytics','bar-chart-line']],
 'Property management' => ['projects'=>['Projects','buildings'],'properties'=>['Properties','house-door'],'company-properties'=>['Company Properties','building-check'],'resale'=>['Property Listings','arrow-left-right'],'customers'=>['Customers','people']],
 'Property Explorer' => ['explorer-layer2'=>['Filter Builder','sliders']],
 'ERP management' => ['sources'=>['ERP Sources','database'],'mapping'=>['Project Mapping','diagram-3'],'heads'=>['Head / Subhead','collection'],'synchronization'=>['Synchronization','arrow-repeat']],
 'Sales network' => ['agents'=>['Shalimar Agents','person-badge'],'agencies'=>['Authorized Agencies','briefcase'],'inquiries'=>['Leads & Inquiries','chat-square-text']],
 'Administration' => ['reports'=>['Reports','file-earmark-bar-graph'],'users'=>['Users','people'],'roles'=>['Roles & Permissions','shield-check'],'settings'=>['Settings','gear'],'activity'=>['Audit Logs','clock-history'],'dealer-activity'=>['Dealer Credential Activity','person-lock']],
];
$pageTitles=[];
foreach($adminNav as $items) foreach($items as $key=>$item) $pageTitles[$key]=$item[0];
$pageTitles+=['project-form'=>'Project details','property-details'=>'Property details','resale-details'=>'Listing details','components'=>'Component library'];
