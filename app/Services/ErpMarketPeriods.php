<?php
declare(strict_types=1);
namespace App\Services;
final class ErpMarketPeriods
{
    public static function make(?\DateTimeImmutable $now=null):array
    {
        $now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Karachi'));
        $today=$now->setTime(0,0);$month=$today->modify('first day of this month');$prior=$month->modify('-1 year');
        // Clamp Feb 29 rather than allowing PHP to roll the equivalent date into March.
        $day=min((int)$now->format('j'),(int)$prior->format('t'));
        $priorNow=$prior->setDate((int)$prior->format('Y'),(int)$prior->format('m'),$day)->setTime((int)$now->format('H'),(int)$now->format('i'),(int)$now->format('s'));
        return ['now'=>$now,'today'=>$today,'tomorrow'=>$today->modify('+1 day'),'month'=>$month,
            'last_month'=>$month->modify('-1 month'),'next_month'=>$month->modify('+1 month'),
            'year_ago'=>$prior,'year_ago_now'=>$priorNow,'chart_start'=>$today->modify('-29 days')];
    }
    public static function change(?float $value):string
    {
        if($value===null||!is_finite($value))return 'N/A';$value=round($value,1);if(abs($value)<0.05)$value=0.0;
        return ($value>0?'+':'').number_format($value,1,'.','').'%';
    }
    /** Exact merged distributions, not an average/median of source medians. */
    public static function distributionMedian(array $bins):?float
    {
        $merged=[];$total=0;foreach($bins as$bin){$price=$bin['price_per_unit']??null;$n=$bin['count']??null;
            if(!is_numeric($price)||!is_finite((float)$price)||(float)$price<=0||!is_int($n)||$n<1||$n>100000000)throw new \InvalidArgumentException('Invalid actual-price distribution.');
            $key=(string)(float)$price;$merged[$key]=($merged[$key]??0)+$n;$total+=$n;
        }if(!$total)return null;uksort($merged,static fn($a,$b)=>(float)$a<=>(float)$b);$targets=[intdiv($total+1,2),intdiv($total+2,2)];$values=[];$seen=0;
        foreach($merged as$price=>$n){foreach($targets as$i=>$target)if(!isset($values[$i])&&$seen+$n>=$target)$values[$i]=(float)$price;$seen+=$n;}return ($values[0]+$values[1])/2;
    }
    public static function actualPriceChange(array $current,array $previous,string $basis,bool $comparable):?float
    {
        if($basis!=='actual_completed_resale_consideration'||!$comparable||array_sum(array_column($current,'count'))<5||array_sum(array_column($previous,'count'))<5)return null;
        $a=self::distributionMedian($current);$b=self::distributionMedian($previous);return $a!==null&&$b!==null&&$b>0?($a-$b)/$b*100:null;
    }
}
