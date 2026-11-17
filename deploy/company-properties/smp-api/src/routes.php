<?php
declare(strict_types=1);

if (!defined('SMP_API_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

// All data endpoints require the existing server-side bearer credential.
return [
    '/v1/company-properties.php' => ['method'=>'GET','handler'=>[\SmpApi\CompanyPropertiesController::class,'page']],
    '/v1/filter-tree.php' => ['method'=>'POST', 'handler'=>[\SmpApi\FilterTreeController::class, 'show']],
    '/v1/filter-options.php' => ['method'=>'GET', 'handler'=>[\SmpApi\FilterOptionsController::class, 'show']],
    '/v1/market-intelligence.php' => ['method'=>'POST','handler'=>[\SmpApi\MarketController::class,'show']],
    '/v1/verify-current-booking.php' => ['method'=>'POST','handler'=>[\SmpApi\CurrentBookingVerification::class,'verify'],'normalized_verification'=>true],
    '/v1/resale/verify-current-booking' => ['method'=>'POST','handler'=>[\SmpApi\CurrentBookingVerification::class,'verify'],'normalized_verification'=>true],
    '/v1/resale/current-booking.php' => ['method'=>'POST','handler'=>[\SmpApi\ResaleCurrentBooking::class,'lookup']],
    '/v1/dealers.php' => ['method'=>'GET', 'handler'=>[\SmpApi\DealerController::class,'page']],
    '/v1/agency-dealers.php' => ['method'=>'GET', 'handler'=>[\SmpApi\AgencyDealerController::class,'page']],
    '/v1/agencies.php' => ['method'=>'GET', 'handler'=>[\SmpApi\AgencyController::class,'page']],
    '/v1/customer-identity.php' => ['method'=>'POST', 'handler'=>[\SmpApi\CustomerController::class,'identity']],
    '/v1/customer-owned-properties.php' => ['method'=>'POST', 'handler'=>[\SmpApi\CustomerController::class,'owned']],
    '/v1/catalog.php' => ['method'=>'GET', 'handler'=>[\SmpApi\CatalogController::class, 'snapshot']],
    '/v1/health.php' => [
        'method' => 'GET',
        'handler' => [\SmpApi\HealthController::class, 'show'],
        'public' => true,
    ],
];
