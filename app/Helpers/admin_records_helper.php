<?php
function ar_value($value):string{return $value===null||$value===''?'Not recorded':esc((string)$value);}
function ar_date($value):string
{
 if(!is_string($value)||trim($value)==='')return 'Not recorded';try{$zone=new DateTimeZone(config('App')->appTimezone);return esc((new DateTimeImmutable($value,$zone))->setTimezone($zone)->format('d M Y, h:i A'));}catch(Throwable $e){return 'Not recorded';}
}
function ar_status(string $status):string
{
 $tone=match($status){'live','active','approved'=>'active','pending','pending_approval','cancellation_requested'=>'pending','sold','rented'=>'sold','unlisted','inactive','removed','cancelled','rejected'=>'cancelled',default=>'draft'};
 $label=match($status){'unlisted'=>'Unpublished',default=>ucwords(str_replace('_',' ',$status))};return '<span class="status-badge status-'.$tone.'"><i></i>'.esc($label).'</span>';
}
function ar_method(?string $method):string{return esc(match($method){'self_sell'=>'Self Sell','shalimar_agent'=>'Shalimar Agent','authorized_agency'=>'Authorized Agency',default=>'Not recorded'});}
function ar_page_url(string $base,array $filters,int $page):string
{
 $params=[];foreach(['q','status','source','customer','property','project','method','per_page']as$key)if(isset($filters[$key])&&$filters[$key]!=='')$params[$key]=$filters[$key];$params['page']=$page;return $base.'?'.http_build_query($params);
}
