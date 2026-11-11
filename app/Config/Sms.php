<?php
namespace Config;
class Sms extends \CodeIgniter\Config\BaseConfig
{
 public bool $enabled=false;
 public string $gatewayURL='https://www.jaageer.com/jazz-sms-api.php';
 public string $gatewayKey='';
 public string $phoneFormat='national';
 public bool $testMode=false;
 public string $testRecipient='';
 public array $testAllowedNetworks=[];
 public array $testAllowedHosts=[];
 public function __construct()
 {
  parent::__construct();
  foreach(['testAllowedNetworks','testAllowedHosts']as$field){$raw=env('sms.'.$field);
   if($raw!==null){$values=json_decode((string)$raw,true);$this->{$field}=is_array($values)&&array_is_list($values)?$values:[];}}
 }
}
