<?php
namespace App\Services;
interface BusinessSmsGateway{public function sendMessage(string $mobile,string $message):void;}
