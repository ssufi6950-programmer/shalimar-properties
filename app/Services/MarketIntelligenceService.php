<?php
declare(strict_types=1);
namespace App\Services;
/** Compatibility entry point. The former local-listing calculation has been removed. */
final class MarketIntelligenceService
{
    public function __construct(private \PDO $pdo){}
    public function snapshot(array $filters=[],?\DateTimeImmutable $now=null):array{return(new ErpMarketIntelligenceService($this->pdo))->snapshot($filters,$now);}
    public static function periods(?\DateTimeImmutable $now=null):array{return ErpMarketPeriods::make($now);}
    public static function formatChange(?float $value):string{return ErpMarketPeriods::change($value);}
}
