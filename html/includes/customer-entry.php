<?php
// Compatibility entry only: never start the old poster session, inspect POST data or write a listing.
require_once dirname(__DIR__,2).'/vendor/autoload.php';
header('Cache-Control: no-store');
try {
    $destination=\App\Libraries\PortalLinks::customerEntry();
    header('Location: '.$destination,true,($_SERVER['REQUEST_METHOD']??'GET')==='GET'?302:303);
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Property posting is temporarily unavailable. Please use the customer portal.';
}
exit;
