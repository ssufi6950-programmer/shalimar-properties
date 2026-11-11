<?php
namespace App\Services;
interface CustomerSmsGateway{public function sendCode(string $verifiedMobile,string $otp):void;}
