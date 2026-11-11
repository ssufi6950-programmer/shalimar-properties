<?php

namespace App\Controllers\Customer;

final class Profile extends PortalController
{
    public function index(): string
    {
        return $this->renderPortal('profile/index', ['pageTitle'=>'My Profile','activeNav'=>'profile']);
    }
}
