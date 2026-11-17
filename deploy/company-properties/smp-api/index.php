<?php
declare(strict_types=1);

// This bootstrap deliberately never loads the ERP's database/session bootstrap.
define('SMP_API_BOOTSTRAPPED', true);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/src/JsonResponse.php';
if(!defined('SMP_API_NORMALIZED_VERIFICATION')&&preg_match('~/v1/(?:verify-current-booking\.php|resale/verify-current-booking)$~',(string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)))define('SMP_API_NORMALIZED_VERIFICATION',true);

set_exception_handler(static function (Throwable $error): void {
    // Do not send exception messages, SQL, credentials or filesystem paths to clients.
    error_log('[SMP API] Unhandled failure: ' . get_class($error));
    \SmpApi\JsonResponse::send(500,defined('SMP_API_NORMALIZED_VERIFICATION')?['success'=>false,'error'=>['code'=>'INTERNAL_VERIFICATION_ERROR','message'=>'The API could not complete this request.']]:['error'=>['code'=>'internal_error','message'=>'The API could not complete this request.']]);
});

require_once __DIR__ . '/src/BearerAuthentication.php';
require_once __DIR__ . '/src/HealthController.php';
require_once __DIR__ . '/src/CatalogController.php';
require_once __DIR__ . '/src/CustomerController.php';
require_once __DIR__ . '/src/CustomerDatabase.php';
require_once __DIR__ . '/src/FilterOptionsController.php';
require_once __DIR__ . '/src/InventoryFieldDiscovery.php';
require_once __DIR__ . '/src/FilterTreeController.php';
require_once __DIR__ . '/src/PropertyClassification.php';
require_once __DIR__ . '/src/CurrentBookingVerification.php';
require_once __DIR__ . '/src/ResaleCurrentBooking.php';
require_once __DIR__ . '/src/AgencyController.php';
require_once __DIR__ . '/src/AgencyDealerController.php';
require_once __DIR__ . '/src/CompanyPropertiesController.php';
require_once __DIR__ . '/src/DealerController.php';
require_once __DIR__ . '/src/MarketIntelligence.php';
$config = require __DIR__ . '/src/config.php';
$routes = require __DIR__ . '/src/routes.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$mount = rtrim(str_replace('\\', '/', defined('SMP_API_MOUNT_PATH') ? SMP_API_MOUNT_PATH : dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!is_string($path) || strpos($path, $mount . '/') !== 0) {
    \SmpApi\JsonResponse::send(404, ['error' => ['code' => 'not_found', 'message' => 'API endpoint not found.']]);
}
$path = substr($path, strlen($mount));
$route = $routes[$path] ?? null;
if ($route === null) {
    \SmpApi\JsonResponse::send(404, ['error' => ['code' => 'not_found', 'message' => 'API endpoint not found.']]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== $route['method']) {
    header('Allow: ' . $route['method']);
    \SmpApi\JsonResponse::send(405,($route['normalized_verification']??false)?['success'=>false,'error'=>['code'=>'METHOD_NOT_ALLOWED','message'=>'This HTTP method is not allowed.']]:['error'=>['code'=>'method_not_allowed','message'=>'This HTTP method is not allowed.']]);
}

// Every future route requires authentication unless explicitly marked public.
if (($route['public'] ?? false) !== true) {
    \SmpApi\BearerAuthentication::requireAuthentication($config,($route['normalized_verification']??false)===true);
}
call_user_func($route['handler']);
