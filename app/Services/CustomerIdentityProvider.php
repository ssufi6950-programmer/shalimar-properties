<?php
namespace App\Services;
interface CustomerIdentityProvider
{
 public function identities(string $cnic,string $mobile):array;
 public function owned(array $identity,int $page=1):array;
}
