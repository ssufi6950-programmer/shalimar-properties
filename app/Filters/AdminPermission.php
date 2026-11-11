<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminPermission implements FilterInterface
{
    public static function permissionFor(string $path,string $method): ?string
    {
        $path=trim($path,'/');
        if(in_array($path,['admin/logout','admin/profile','admin/change-password'],true))return '';
        if(in_array($path,['admin','admin/dashboard'],true))return 'dashboard.view';
        if($method==='GET'&&preg_match('~^admin/listings(?:/\d+/(?:review|photos/(?:legacy-)?[a-f0-9]{64}))?$~',$path))return 'resale_listings.view';
        if($method==='POST'&&preg_match('~^admin/listings/\d+/(approve|reject|changes|verify|photos|photo-set)$~',$path,$review))return in_array($review[1],['approve','verify'],true)?'resale_listings.approve':(in_array($review[1],['reject','changes'],true)?'resale_listings.reject':'resale_listings.edit');
        if($method==='GET'&&preg_match('~^admin/(customers|properties|resale)(?:/\d+)?$~',$path,$records))return ['customers'=>'customers.view','properties'=>'properties.view','resale'=>'resale_listings.view'][$records[1]];
        if($method==='POST'&&preg_match('~^admin/resale/\d+/unpublish$~',$path))return 'resale_listings.deactivate';
        if(preg_match('~^admin/workspace/([a-z-]+)$~',$path,$m))return \Config\AdminSecurity::TABS[$m[1]]??null;
        if(preg_match('~^admin/erp-sources(?:/(.*))?$~',$path,$m)) {
            $tail=$m[1]??'';
            if($method==='GET')return 'erp_sources.view';
            if($method!=='POST')return null;
            if($tail==='')return 'erp_sources.create';
            if(preg_match('~^\d+/status$~',$tail))return 'erp_sources.deactivate';
            if(preg_match('~^\d+/test$~',$tail))return 'erp_sources.test_connection';
            if(ctype_digit($tail))return 'erp_sources.edit';
        }
        if($method==='GET'&&preg_match('~^admin/(?:agencies(?:/\d+)?|agency-links/\d+)$~',$path))return 'agencies.view';
        if($method==='GET'&&preg_match('~^admin/agents(?:/\d+)?$~',$path))return 'agents.view';
        if($method==='POST'){
            if($path==='admin/agents/sync')return 'erp_sync.run';
            if($path==='admin/agents/configuration')return 'erp_sync.manage_settings';
            if(preg_match('~^admin/agents/\d+/status$~',$path))return 'agents.deactivate';
            if(preg_match('~^admin/agents/\d+/contacts$~',$path))return 'agents.edit';
            if(preg_match('~^admin/agents/listings/\d+/review$~',$path))return 'resale_listings.edit';
        }
        if($method==='POST') {
            if(in_array($path,['admin/agency-links/bulk-review','admin/agency-links/bulk-map'],true))return 'agencies.edit';
            if(preg_match('~^admin/agencies/sync(?:/\d+)?$~',$path))return 'erp_sync.run';
            if(preg_match('~^admin/agencies/\d+/status$~',$path))return 'agencies.deactivate';
            if(preg_match('~^admin/agencies/\d+/portal-access(?:/(?:reset|status))?$~',$path))return 'agencies.edit';
            if(preg_match('~^admin/agencies/\d+$~',$path)||preg_match('~^admin/agency-links/\d+/map$~',$path))return 'agencies.edit';
            if(preg_match('~^admin/agency-links/\d+/create$~',$path))return 'agencies.create';
        }
        if($method==='POST') {
            if($path==='admin/erp-mappings')return 'erp_mapping.create';
            if(preg_match('~^admin/erp-mappings/\d+/status$~',$path))return 'erp_mapping.edit';
            if($path==='admin/erp-mappings/assign')return 'resale_listings.edit';
            if(preg_match('~^admin/erp-catalog/\d+/sync$~',$path))return 'erp_sync.run';
        }
        foreach(['users','roles']as$module)if(preg_match('~^admin/'.$module.'(?:/(.*))?$~',$path,$m)) {
            $tail=$m[1]??'';
            if($method==='GET')return $module.'.view';
            if($method!=='POST')return null;
            if($tail==='')return $module.'.create';
            if(ctype_digit($tail))return $module.'.edit';
            if($module==='users'&&preg_match('~^\d+/password$~',$tail))return 'users.reset_password';
            if($module==='users'&&preg_match('~^\d+/status$~',$tail))return 'users.deactivate';
            if($module==='roles'&&preg_match('~^\d+/delete$~',$tail))return 'roles.delete';
        }
        if($path==='admin/audit-logs')return 'audit_logs.view';
        if($path==='admin/audit-logs/export')return 'audit_logs.export';
        if($path==='admin/classic')return ''; // Controller checks every legacy tab and action.
        if($path==='admin/listing-preview')return 'resale_listings.view';
        return null;
    }
    public function before(RequestInterface $request,$arguments=null)
    {
        $key=$arguments[0]??self::permissionFor($request->getUri()->getRoutePath(),strtoupper($request->getMethod()));
        if($key!==null&&($key===''||can($key)))return null;
        return admin_denied();
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null) {}
}
