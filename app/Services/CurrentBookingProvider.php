<?php
namespace App\Services;
interface CurrentBookingProvider
{
 public function verify(array $identity,string $property,?string $booking=null):array;
}
