<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class AdminSecurity extends BaseConfig
{
    public int $idleTimeout = 1800;
    public int $maximumLifetime = 28800;
    public int $loginWindow = 600;
    public int $ipAttempts = 10;
    public int $accountAttempts = 5;
    public int $auditRetentionDays = 365;

    public const MODULES = [
        'dashboard'=>['view'], 'erp_sources'=>['view','create','edit','deactivate','test_connection','manage_credentials'],
        'erp_mapping'=>['view','create','edit','delete'], 'erp_sync'=>['view','run','view_logs','manage_settings'],
        'projects'=>['view','create','edit','delete','publish'], 'properties'=>['view','create','edit','delete','export'],
        'resale_listings'=>['view','edit','approve','reject','deactivate','export'], 'customers'=>['view','edit','export'],
        'agents'=>['view','create','edit','deactivate'], 'agencies'=>['view','create','edit','deactivate'],
        'leads'=>['view','assign','edit','export'], 'reports'=>['view','export'],
        'users'=>['view','create','edit','deactivate','assign_roles','reset_password'],
        'roles'=>['view','create','edit','delete','manage_permissions'], 'settings'=>['view','edit'],
        'audit_logs'=>['view','export'],
        'transactions'=>['view','create'],
    ];

    public const TABS = [
        'dashboard'=>'dashboard.view','analytics'=>'reports.view','projects'=>'projects.view','project-form'=>'projects.create',
        'properties'=>'properties.view','property-details'=>'properties.view','resale'=>'resale_listings.view',
        'resale-details'=>'resale_listings.view','customers'=>'customers.view','sources'=>'erp_sources.view',
        'mapping'=>'erp_mapping.view','heads'=>'erp_mapping.view','synchronization'=>'erp_sync.view',
        'agents'=>'agents.view','agencies'=>'agencies.view','inquiries'=>'leads.view','reports'=>'reports.view',
        'users'=>'users.view','roles'=>'roles.view','settings'=>'settings.view','activity'=>'audit_logs.view','components'=>'settings.view',
    ];
}
