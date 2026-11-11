<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class CustomerPortal extends BaseConfig
{
 public bool $enabled=false;
 public string $identityKey='';
 public int $otpLifetime=300;
 public int $resendCooldown=60;
 public int $maximumAttempts=5;
 public int $idleTimeout=1800;
 public int $maximumLifetime=28800;
 public bool $showLocalOtp=false;
 /** Temporary test preview on all hosts, including live. Disable before customer rollout. */
 public bool $showOtpPreview=false;
 /** Temporary login testing without SMS; requires showOtpPreview and sms.testMode. */
 public bool $loginOtpPreviewOnly=false;
 /** Temporary property verification testing without SMS; requires showOtpPreview and sms.testMode. */
 public bool $resaleOtpPreviewOnly=false;
 public int $photoMaximumCount=6;
 public int $photoMaximumBytes=4194304;
 public int $photoMaximumPixels=20000000;
 public int $photoStagingLifetime=86400;
 public array $identityPaths=[];
 public array $propertiesPaths=[];
 public array $bookingPaths=['SGC'=>'/verify-current-booking.php','ZALMI'=>'/verify-current-booking.php'];
 /** Existing deployment keys use customerPortal; CI's default short prefix is customerportal.
  * Read the request-local values before falling back to process-wide getenv(), which can
  * disappear during another request's environment cleanup on threaded Windows Apache.
  */
 protected function getEnvValue(string $property,string $prefix,string $shortPrefix)
 {
  foreach(['customerPortal.'.$property,'customerPortal_'.str_replace('.','_',$property)]as$key){
   if(array_key_exists($key,$_ENV))return $_ENV[$key];
   if(array_key_exists($key,$_SERVER))return $_SERVER[$key];
  }
  return parent::getEnvValue($property,$prefix,$shortPrefix);
 }
 public function __construct(){parent::__construct();foreach(['identityPaths','propertiesPaths','bookingPaths']as$key){$raw=env('customerPortal.'.$key);if($raw!==null){$value=json_decode((string)$raw,true);$this->{$key}=is_array($value)?$value:[];}}}
}
