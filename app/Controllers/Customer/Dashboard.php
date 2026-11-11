<?php

namespace App\Controllers\Customer;

final class Dashboard extends PortalController
{
    public function index(): string
    {
        $counts=(new \App\Services\CustomerListingReadModel(db_connect()))->counts((int)service('customerSession')->user()['id']);
        return $this->renderPortal('dashboard/index', ['pageTitle'=>'Your property home','activeNav'=>'dashboard','listingCounts'=>$counts]);
    }

    public function notifications(): string
    {
        return $this->renderPortal('notifications/index', ['pageTitle'=>'Your notifications','activeNav'=>'notifications']);
    }

    public function support(): string
    {
        require_once ROOTPATH.'html/includes/config.php';
        return $this->renderPortal('support/index',['pageTitle'=>'Help & Support','activeNav'=>'support','support'=>['phone'=>SITE_PHONE,'tel'=>SITE_TEL,'email'=>SITE_EMAIL,'whatsapp'=>SITE_WHATSAPP,'address'=>SITE_ADDRESS]]);
    }
}
