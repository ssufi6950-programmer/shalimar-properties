<?php
// Sample data only. No site bootstrap, sessions, databases or file-backed stores.
if (!function_exists('ad_e')) { function ad_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/components/status_badge.php';
require __DIR__ . '/navigation.php';
$tab=isset($_GET['tab'])&&is_string($_GET['tab'])&&isset($pageTitles[$_GET['tab']])?$_GET['tab']:'dashboard';
$pageTitle=$pageTitles[$tab];
$projects=[
 ['id'=>'PRJ-001','name'=>'Shalimar Smart City','location'=>'Lahore Road, Sargodha','erp'=>'Shalimar','mapping'=>'Mapped','properties'=>6840,'listings'=>162,'status'=>'Active','image'=>'ssc-aerial-sm.webp','head'=>'Residential','subhead'=>'Block A','description'=>'A thoughtfully planned community with modern infrastructure, green spaces and a connected lifestyle.','featured'=>true],
 ['id'=>'PRJ-002','name'=>'Gulberg City','location'=>'Faisalabad Road, Sargodha','erp'=>'Shalimar','mapping'=>'Mapped','properties'=>3920,'listings'=>80,'status'=>'Active','image'=>'gc-aerial-sm.webp','head'=>'Residential','subhead'=>'Block B','description'=>'Premium residential living in the heart of a growing community.','featured'=>true],
 ['id'=>'PRJ-003','name'=>'Zalmi X','location'=>'Main University Road, Sargodha','erp'=>'Zalmi','mapping'=>'Mapped','properties'=>1240,'listings'=>90,'status'=>'Active','image'=>'zx-building-sm.webp','head'=>'Commercial','subhead'=>'Tower A','description'=>'Contemporary commercial spaces built for ambitious businesses.','featured'=>false],
 ['id'=>'PRJ-004','name'=>'Smart Business District','location'=>'Lahore Road, Sargodha','erp'=>'Shalimar','mapping'=>'Pending','properties'=>480,'listings'=>16,'status'=>'Upcoming','image'=>'ssc-business-district-sm.webp','head'=>'Commercial','subhead'=>'Business Bay','description'=>'The next destination for business, retail and investment.','featured'=>false],
];
$names=['Ahmed Raza','Fatima Khalid','Usman Ali','Ayesha Malik','Hassan Tariq','Sara Ahmed','Bilal Aslam','Maryam Shah','Omar Farooq','Zainab Iqbal','Ali Hamza','Hira Saeed'];
$listings=[];$properties=[];$customers=[];$inquiries=[];
$statuses=['Active','Pending','Active','Sold','Approved','Draft','Active','Cancelled','Pending','Sold','Active','Approved'];
$modes=['Shalimar Agent','Self Sell','Authorized Agency'];
foreach($names as $i=>$name){
 $p=$projects[$i%3];$size=[5,10,7,10,5,20][$i%6];$propertyId='SP-'.(10481+$i);
 $properties[]=['id'=>$propertyId,'plot'=>'Plot '.(124+$i).' / '.chr(65+$i%3),'project'=>$p['name'],'erp'=>$p['erp'],'head'=>$p['head'].' / '.$p['subhead'],'booking'=>'BK-'.(20841+$i),'customer'=>$name,'size'=>$size.' Marla','ownership'=>$i===5?'Transfer pending':'Verified','status'=>$statuses[$i],'image'=>$p['image'],'agency'=>['Shalimar Properties','City Estate Partners','Prime Property Group'][$i%3],'lastSync'=>'03 Nov 2026, 09:42 AM'];
 $listings[]=['id'=>'RSL-'.(2401+$i),'property'=>$size.' Marla '.($i%3===2?'Commercial Plot':'Residential Plot'),'propertyId'=>$propertyId,'plot'=>'Plot '.(124+$i).' / '.chr(65+$i%3),'project'=>$p['name'],'erp'=>$p['erp'],'seller'=>$name,'price'=>[4500000,8200000,5600000,11500000,4200000,17800000][$i%6],'mode'=>$modes[$i%3],'agent'=>['Muhammad Adeel','Self managed','City Estate Partners'][$i%3],'date'=>sprintf('2026-11-%02d',3-$i%3),'status'=>$statuses[$i],'image'=>$p['image'],'inquiries'=>[8,3,12,6,4,0][$i%6]];
 $customers[]=['id'=>'CUS-'.(1001+$i),'name'=>$name,'cnic'=>'*****-'.(34000+$i).'**-*','phone'=>'+92 3'.(10+$i).' •••• '.(120+$i),'erp'=>$p['erp'],'properties'=>$i%4+1,'listings'=>$i%3,'status'=>'Active'];
 $inquiries[]=['id'=>'INQ-'.(801+$i),'name'=>$names[($i+4)%12],'property'=>$size.' Marla in '.$p['name'],'contact'=>'+92 3'.(10+$i).' •••• '.(120+$i),'source'=>['Website','WhatsApp','Phone call'][$i%3],'agent'=>['Muhammad Adeel','Sana Iqbal','Unassigned'][$i%3],'date'=>'2026-11-03','status'=>['New','Contacted','Qualified','Closed'][$i%4]];
}
$agents=[
 ['id'=>'AG-001','name'=>'Muhammad Adeel','email'=>'adeel@example.com','phone'=>'+92 300 123 4567','erp'=>'Shalimar','listings'=>42,'closed'=>18,'status'=>'Active'],
 ['id'=>'AG-002','name'=>'Sana Iqbal','email'=>'sana@example.com','phone'=>'+92 321 234 5678','erp'=>'Shalimar','listings'=>36,'closed'=>14,'status'=>'Active'],
 ['id'=>'AG-003','name'=>'Hamza Sheikh','email'=>'hamza@example.com','phone'=>'+92 333 345 6789','erp'=>'Zalmi','listings'=>28,'closed'=>12,'status'=>'Active'],
 ['id'=>'AG-004','name'=>'Areeba Khan','email'=>'areeba@example.com','phone'=>'+92 345 456 7890','erp'=>'Shalimar','listings'=>21,'closed'=>9,'status'=>'On leave'],
 ['id'=>'AG-005','name'=>'Faisal Mehmood','email'=>'faisal@example.com','phone'=>'+92 312 567 8901','erp'=>'Zalmi','listings'=>18,'closed'=>7,'status'=>'Active'],
];
$agencies=[
 ['id'=>'AY-001','name'=>'City Estate Partners','person'=>'Asad Mahmood','phone'=>'+92 300 111 2233','erp'=>'Shalimar','listings'=>38,'status'=>'Active'],
 ['id'=>'AY-002','name'=>'Prime Property Group','person'=>'Nabeel Akram','phone'=>'+92 321 222 3344','erp'=>'Shalimar','listings'=>27,'status'=>'Active'],
 ['id'=>'AY-003','name'=>'Zalmi Realty Network','person'=>'Imran Shah','phone'=>'+92 333 333 4455','erp'=>'Zalmi','listings'=>19,'status'=>'Active'],
 ['id'=>'AY-004','name'=>'Green Avenue Estates','person'=>'Sadia Rafiq','phone'=>'+92 345 444 5566','erp'=>'Shalimar','listings'=>12,'status'=>'Pending'],
];
$mapping=array_map(fn($p)=>['id'=>$p['id'],'project'=>$p['name'],'erp'=>$p['erp'],'erpProject'=>strtoupper(substr($p['erp'],0,2)).'-'.substr($p['id'],-3),'head'=>$p['head'],'subhead'=>$p['subhead'],'status'=>$p['mapping']],$projects);
$heads=[
 ['id'=>'HD-001','head'=>'Residential','subhead'=>'Block A','erp'=>'Shalimar','properties'=>3840,'status'=>'Mapped'],
 ['id'=>'HD-002','head'=>'Residential','subhead'=>'Block B','erp'=>'Shalimar','properties'=>3000,'status'=>'Mapped'],
 ['id'=>'HD-003','head'=>'Commercial','subhead'=>'Tower A','erp'=>'Zalmi','properties'=>1240,'status'=>'Mapped'],
 ['id'=>'HD-004','head'=>'Commercial','subhead'=>'Business Bay','erp'=>'Shalimar','properties'=>480,'status'=>'Pending'],
];
$activity=[
 ['id'=>'EV-1042','event'=>'Listing approved','description'=>'RSL-2405 approved for publication','user'=>'Hassan Ali','module'=>'Resale Listings','date'=>'03 Nov 2026, 10:24 AM','status'=>'Success'],
 ['id'=>'EV-1041','event'=>'Synchronization completed','description'=>'128 property records checked','user'=>'Demo scheduler','module'=>'Synchronization','date'=>'03 Nov 2026, 09:42 AM','status'=>'Success'],
 ['id'=>'EV-1040','event'=>'Project mapping updated','description'=>'Gulberg City linked to SH-002','user'=>'Hassan Ali','module'=>'Project Mapping','date'=>'03 Nov 2026, 09:16 AM','status'=>'Success'],
 ['id'=>'EV-1039','event'=>'Mapping needs attention','description'=>'Business Bay subhead is not mapped','user'=>'Demo scheduler','module'=>'Synchronization','date'=>'02 Nov 2026, 06:30 PM','status'=>'Warning'],
 ['id'=>'EV-1038','event'=>'Inquiry assigned','description'=>'INQ-801 assigned to Muhammad Adeel','user'=>'Sana Iqbal','module'=>'Leads & Inquiries','date'=>'02 Nov 2026, 04:12 PM','status'=>'Success'],
];
$users=[
 ['id'=>'USR-001','name'=>'Hassan Ali','email'=>'hassan@example.com','role'=>'Super Admin','access'=>'All modules','lastActive'=>'Today, 10:24 AM','status'=>'Active'],
 ['id'=>'USR-002','name'=>'Sana Iqbal','email'=>'sana@example.com','role'=>'Sales Manager','access'=>'Sales & properties','lastActive'=>'Today, 09:56 AM','status'=>'Active'],
 ['id'=>'USR-003','name'=>'Ahmed Saleem','email'=>'ahmed@example.com','role'=>'ERP Operator','access'=>'ERP management','lastActive'=>'Yesterday, 04:30 PM','status'=>'Active'],
 ['id'=>'USR-004','name'=>'Ayesha Noor','email'=>'ayesha@example.com','role'=>'Viewer','access'=>'Reports & analytics','lastActive'=>'Invitation pending','status'=>'Pending'],
];
$demoData=compact('projects','properties','listings','customers','agents','agencies','inquiries','mapping','heads','activity','users');
