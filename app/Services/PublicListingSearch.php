<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

/** Read-only discovery shared by the existing standalone public website. */
final class PublicListingSearch
{
    public function __construct(private PDO $pdo) {}

    public static function filters(array $input, string $purpose=''): array
    {
        $errors=[];
        $text=static function(string $key,int $limit=120) use($input,&$errors):string {
            $value=$input[$key]??'';
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$limit){$errors[]='Choose valid search filters.';return '';}
            return trim($value);
        };
        $out=[];
        foreach(['q','project','sector','block','type','city','budget','sort','unit','purpose']as$key)$out[$key]=$text($key);
        $requested=$out['purpose'];$out['purpose']=$purpose?:$requested;
        if(!in_array($out['purpose'],['','sale','rent'],true)||($purpose!==''&&$requested!==''&&$requested!==$purpose))$errors[]='Choose a valid listing purpose.';
        if(!in_array($out['type'],['','house','plot','apartment','commercial','office','farmhouse'],true))$errors[]='Choose a supported property type.';
        $out['sort']=$out['sort']?:'newest';
        if(!in_array($out['sort'],['newest','price_asc','price_desc','area_asc','area_desc'],true))$errors[]='Choose a valid sort order.';
        foreach(['min','max','min_area','max_area']as$key){$value=$text($key,16);$out[$key]=null;if($value!==''){if(!preg_match('/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/D',$value))$errors[]='Enter a valid positive price or area.';else $out[$key]=$value;}}
        foreach([''=>'','u50l'=>'sale','50l-1c'=>'sale','1c-2c'=>'sale','2c-5c'=>'sale','5c+'=>'sale','r-u40'=>'rent','r-40-80'=>'rent','r-80+'=>'rent'] as $key=>$scope)$budgets[$key]=$scope;
        if(!array_key_exists($out['budget'],$budgets)||($out['budget']!==''&&$budgets[$out['budget']]!==$out['purpose']))$errors[]='Use a budget for the selected Sale or Rent purpose.';
        $presets=['u50l'=>[0,5000000],'50l-1c'=>[5000000,10000000],'1c-2c'=>[10000000,20000000],'2c-5c'=>[20000000,50000000],'5c+'=>[50000000,null],'r-u40'=>[0,40000],'r-40-80'=>[40000,80000],'r-80+'=>[80000,null]];
        if(isset($presets[$out['budget']]))foreach(['min','max']as$i=>$key)if($out[$key]===null)$out[$key]=$presets[$out['budget']][$i];
        if($out['purpose']===''&&($out['min']!==null||$out['max']!==null))$errors[]='Select Sale or Rent before filtering prices.';
        foreach([['min','max'],['min_area','max_area']]as[$min,$max])if($out[$min]!==null&&$out[$max]!==null&&(float)$out[$min]>(float)$out[$max])$errors[]='Minimum must not exceed maximum.';
        if(!in_array($out['unit'],['','marla','sqft'],true)||(($out['min_area']!==null||$out['max_area']!==null||str_starts_with($out['sort'],'area_'))&&$out['unit']===''))$errors[]='Select an area unit for size filters and sorting.';
        foreach(['beds'=>20,'agency'=>2147483647,'page'=>100000,'per_page'=>48]as$key=>$max){$value=$text($key,10);if($value!==''&&(!ctype_digit($value)||(int)$value>$max))$errors[]='Choose valid numeric search filters.';$out[$key]=$value===''?(['page'=>1,'per_page'=>12][$key]??0):(int)$value;}
        $out['page']=max(1,$out['page']);
        if(!in_array($out['per_page'],[12,24,48],true))$errors[]='Choose 12, 24 or 48 results per page.';
        $out['verified']=$text('verified',5)!=='';$out['available']=true;
        $out['errors']=array_values(array_unique($errors));return $out;
    }

    public static function fromSql():string
    {
        return ' FROM portal_listings l JOIN erp_project_mappings m ON m.id=l.mapping_id AND m.project_slug=l.project_slug AND (l.erp_source_id IS NULL OR m.erp_source_id=l.erp_source_id)'.ErpVisibility::joins().' WHERE JSON_VALID(l.payload) AND '.ErpVisibility::mappingSql().' AND '.ListingPolicy::publicSql();
    }

    public function search(array $filters,array $legacyPurposes=[],bool $paginate=true):array
    {
        $where=self::fromSql();$params=[];
        if($filters['errors']??[])$where.=' AND 1=0';
        if(($filters['purpose']??'')!==''){$where.=' AND l.listing_purpose=?';$params[]=$filters['purpose'];}
        if($legacyPurposes){
            $canonical=array_values(array_unique(array_map(static fn($p)=>$p==='rent'?'rent':'sale',$legacyPurposes)));
            $where.=' AND l.listing_purpose IN ('.implode(',',array_fill(0,count($canonical),'?')).')';$params=array_merge($params,$canonical);
            if($legacyPurposes===['project'])$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.purpose'))='project'";
        }
        foreach(['project'=>'l.project_slug','type'=>"JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.category'))",'sector'=>"JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.sector'))",'block'=>"JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.block'))",'city'=>"JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.city'))"]as$key=>$column)if(($filters[$key]??'')!==''){$where.=" AND $column=?";$params[]=$filters[$key];}
        $price="COALESCE(l.asking_price,CAST(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.price')) AS DECIMAL(16,2)))";
        $area="CAST(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.size')) AS DECIMAL(16,2))";
        foreach(['min'=>[$price,'>='],'max'=>[$price,'<='],'min_area'=>[$area,'>='],'max_area'=>[$area,'<=']]as$key=>[$column,$op])if(isset($filters[$key])){$where.=" AND $column $op ?";$params[]=$filters[$key];}
        if(($filters['unit']??'')!==''){$where.=" AND JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.unit'))=?";$params[]=$filters['unit'];}
        if($filters['beds']??0){$where.=" AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.beds')) AS UNSIGNED)>=?";$params[]=$filters['beds'];}
        if($filters['agency']??0){$where.=" AND l.selling_method='authorized_agency' AND l.portal_agency_id=?";$params[]=$filters['agency'];}
        if(($filters['q']??'')!==''){
            $parts=[];$needle='%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            foreach(['title','ref','plot_no','location','sector','block','city']as$key){$parts[]="JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.$key')) LIKE ? ESCAPE '!'";$params[]=$needle;}
            $where.=' AND ('.implode(' OR ',$parts).')';
        }
        if($filters['verified']??false){
            $where.=" AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.approval_mode')),'')<>'manual'";
            $proof="CASE WHEN JSON_VALID(l.owner_verification) THEN l.owner_verification ELSE '{}' END";
            $where.=" AND l.portal_customer_id=c.portal_customer_id AND BINARY l.submission_key=BINARY c.submission_key AND c.delivery_state='sent' AND c.verified_at>0 AND c.consumed_at>=c.verified_at AND JSON_UNQUOTE(JSON_EXTRACT($proof,'$.property_ref'))=l.erp_property_id";
            foreach(['ownership_match','current_booking','resale_eligible']as$key)$where.=" AND JSON_EXTRACT($proof,'$.$key')=true";
            foreach(['booking_status'=>'active','head_smp_active'=>'yes','subhead_smp_active'=>'yes']as$key=>$value)$where.=" AND BINARY JSON_UNQUOTE(JSON_EXTRACT($proof,'$.$key'))=BINARY '$value'";
        }
        // Join only the proof row, never the private identity/mobile tables.
        $where=preg_replace('/ WHERE /',' LEFT JOIN resale_otp_challenges c ON c.id=l.resale_verification_id WHERE ',$where,1);
        $query=$this->pdo->prepare('SELECT COUNT(*)'.$where);$query->execute($params);$total=(int)$query->fetchColumn();
        $size=$filters['per_page']??12;$pages=max(1,(int)ceil($total/$size));$page=min($pages,$filters['page']??1);
        $orders=['newest'=>"COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.approved_at')),JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.published_at')),l.created_at) DESC",'price_asc'=>"$price ASC",'price_desc'=>"$price DESC",'area_asc'=>"$area ASC",'area_desc'=>"$area DESC"];
        $select='SELECT l.*,c.portal_customer_id AS otp_customer_id,c.submission_key AS otp_submission_key,c.delivery_state AS otp_delivery_state,c.verified_at AS otp_verified_at,c.consumed_at AS otp_consumed_at';
        $sql=$select.$where.' ORDER BY '.($orders[$filters['sort']??'newest']??$orders['newest']).',l.id DESC';
        if($paginate)$sql.=' LIMIT '.(int)$size.' OFFSET '.(int)(($page-1)*$size);
        $query=$this->pdo->prepare($sql);$query->execute($params);$rows=array_map([self::class,'decorate'],$query->fetchAll(PDO::FETCH_ASSOC));
        return compact('rows','total','page','pages','size','filters');
    }

    public function find(int $id):?array
    {
        $query=$this->pdo->prepare('SELECT l.*,c.portal_customer_id AS otp_customer_id,c.submission_key AS otp_submission_key,c.delivery_state AS otp_delivery_state,c.verified_at AS otp_verified_at,c.consumed_at AS otp_consumed_at'.preg_replace('/ WHERE /',' LEFT JOIN resale_otp_challenges c ON c.id=l.resale_verification_id WHERE ',self::fromSql(),1).' AND l.id=?');
        $query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);return $row?self::decorate($row):null;
    }

    public static function decorate(array $row):array
    {
        $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);
        $keys=['id','ref','title','category','plot_no','size','size_label','unit','sector','block','location','city','beds','baths','description','negotiable','rental','images','images_status','published_at','approved_at','charges','source'];
        $out=array_replace(['title'=>'Property '.$row['id'],'category'=>'property','plot_no'=>'','size'=>0,'unit'=>'marla','sector'=>'','block'=>'','location'=>'','city'=>'','beds'=>0,'baths'=>0,'description'=>'','images'=>[],'rental'=>[],'source'=>''],array_intersect_key($payload,array_flip($keys)));
        $out['id']=(int)$row['id'];$out['ref']=$payload['ref']??'SP-'.$row['id'];$out['price']=$row['asking_price']??$payload['price']??0;
        $out['purpose']=$row['listing_purpose']==='rent'?'rent':(($payload['purpose']??'')==='project'?'project':'resale');
        $out['project']=$row['project_slug'];$out['status']=$row['status'];$out['deal']=$row['deal'];
        $out['_listing_purpose']=$row['listing_purpose'];$out['_selling_method']=$row['selling_method'];$out['_review_state']=$row['review_state'];
        $out['_publication_verification']=PublicationVerification::summary($row);
        return $out;
    }
}
