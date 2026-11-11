<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function adminAuth(bool $getShared=true): \App\Services\AdminAuthService
    {
        if ($getShared) return static::getSharedInstance('adminAuth');
        return new \App\Services\AdminAuthService();
    }
    public static function customerErp(bool $getShared=true): \App\Services\CustomerErpApi{if($getShared)return static::getSharedInstance('customerErp');return new \App\Services\CustomerErpApi(config('CustomerPortal'));}
    public static function currentBooking(bool $getShared=true): \App\Services\CurrentBookingApi{if($getShared)return static::getSharedInstance('currentBooking');return new \App\Services\CurrentBookingApi(config('CustomerPortal'));}
    public static function resaleOwnershipOtp(bool $getShared=true): \App\Services\ResaleOwnershipOtp{if($getShared)return static::getSharedInstance('resaleOwnershipOtp');return new \App\Services\ResaleOwnershipOtp(db_connect(),static::currentBooking(),new \App\Services\JazzCustomerSms(config('Sms')),config('CustomerPortal'));}
    public static function customerOtp(bool $getShared=true): \App\Services\CustomerOtpService{if($getShared)return static::getSharedInstance('customerOtp');return new \App\Services\CustomerOtpService(db_connect(),static::customerErp(),new \App\Services\JazzCustomerSms(config('Sms')),config('CustomerPortal'));}
    public static function customerSession(bool $getShared=true): \App\Services\CustomerSession{if($getShared)return static::getSharedInstance('customerSession');return new \App\Services\CustomerSession();}
    public static function customerPortfolio(bool $getShared=true): \App\Services\CustomerPortfolio{if($getShared)return static::getSharedInstance('customerPortfolio');return new \App\Services\CustomerPortfolio(db_connect(),static::customerErp());}
    public static function agencyDirectory(bool $getShared=true): \App\Services\AgencyDirectory{if($getShared)return static::getSharedInstance('agencyDirectory');return new \App\Services\AgencyDirectory(db_connect());}
    public static function agencyApi(bool $getShared=true): \App\Services\ErpAgencyApi{if($getShared)return static::getSharedInstance('agencyApi');return new \App\Services\ErpAgencyApi(new \App\Models\ErpSourceModel(),config('ErpConnections'));}
    public static function dealerApi(bool $getShared=true): \App\Services\ErpDealerApi{if($getShared)return static::getSharedInstance('dealerApi');return new \App\Services\ErpDealerApi(new \App\Models\ErpSourceModel(),config('ErpConnections'));}
    public static function shalimarAgents(bool $getShared=true): \App\Services\ShalimarAgents{if($getShared)return static::getSharedInstance('shalimarAgents');return new \App\Services\ShalimarAgents(db_connect());}
    public static function resaleSubmission(bool $getShared=true): \App\Services\ResaleSubmission{if($getShared)return static::getSharedInstance('resaleSubmission');return new \App\Services\ResaleSubmission(db_connect());}
    public static function agencyPortalAccounts(bool $getShared=true): \App\Services\AgencyPortalAccounts { if ($getShared) return static::getSharedInstance('agencyPortalAccounts'); return new \App\Services\AgencyPortalAccounts(db_connect()); }
    public static function agencyPortalSession(bool $getShared=true): \App\Services\AgencyPortalSession { if ($getShared) return static::getSharedInstance('agencyPortalSession'); return new \App\Services\AgencyPortalSession(db_connect()); }
    public static function agencyPortalListings(bool $getShared=true): \App\Services\AgencyPortalListings { if ($getShared) return static::getSharedInstance('agencyPortalListings'); return new \App\Services\AgencyPortalListings(db_connect()); }
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
