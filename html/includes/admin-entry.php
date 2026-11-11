<?php
// This entry cannot authenticate or mutate data. Only CI4 admin routes may do so.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405); header('Allow: GET'); header('Content-Type: application/json');
    echo json_encode(['message'=>'Use the protected CI4 administrator routes.']); exit;
}
$script=$_SERVER['SCRIPT_NAME']??'';$marker=strpos($script,'/html/');
$root=$marker===false?'':substr($script,0,$marker);
$tab=isset($_GET['tab'])&&is_string($_GET['tab'])?$_GET['tab']:'dashboard';
$dest=basename($script)==='admin-classic.php'?'classic':match($tab){'sources'=>'erp-sources','users'=>'users','roles'=>'roles','activity'=>'audit-logs','dashboard'=>'dashboard',default=>'workspace/'.rawurlencode($tab)};
$query=[];
foreach(['id','q','status','erp','project','view','mode']as$key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=substr($_GET[$key],0,200);
if(basename($script)==='admin-classic.php')$query['tab']=$tab;
header('Cache-Control: no-store');
header('Location: '.$root.'/public/admin/'.$dest.($query?'?'.http_build_query($query):''),true,302);exit;
