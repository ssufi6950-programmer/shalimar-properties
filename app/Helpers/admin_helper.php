<?php

if (!function_exists('ad_e')) {
    function ad_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

/** Link to the configured public website, retaining the local sibling layout by default. */
function admin_legacy_url(string $path=''): string
{
    return \App\Libraries\PortalLinks::publicSite($path);
}

function admin_user(): ?array {return service('adminAuth')->user();}
function can(string $permission): bool
{
    $user=admin_user();
    return $user!==null&&(new \App\Services\AdminPermissionService())->hasPermission((int)$user['id'],$permission);
}
function admin_denied()
{
    $actor=admin_user();
    $permission=\App\Filters\AdminPermission::permissionFor(service('request')->getUri()->getRoutePath(),strtoupper(service('request')->getMethod()));
    (new \App\Services\AdminAuditService())->record('authorization.denied','security',$actor?(int)$actor['id']:null,null,null,null,['permission'=>$permission??'unregistered']);
    $request=service('request');$response=service('response')->setStatusCode(403)->setHeader('Cache-Control','no-store, private');
    if($request->isAJAX()||str_contains($request->getHeaderLine('Accept'),'application/json'))return $response->setJSON(['message'=>'You do not have permission for this action.','code'=>'access_denied','csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    return $response->setBody(view('admin/errors/403'));
}
function admin_nav_url(string $tab): string
{
    return site_url(match($tab){'customers'=>'admin/customers','properties'=>'admin/properties','resale'=>'admin/listings','agents'=>'admin/agents','agencies'=>'admin/agencies','sources'=>'admin/erp-sources','users'=>'admin/users','roles'=>'admin/roles','activity'=>'admin/audit-logs','dashboard'=>'admin/dashboard',default=>'admin/workspace/'.rawurlencode($tab)});
}
