<?php
namespace App\Libraries;
use PDO;
use App\Services\ErpVisibility;
use App\Services\ListingPolicy;

/** Local database adapter shared with the pre-existing standalone PHP website. */
final class PortalCatalogStore
{
    private ?\App\Services\PublicListingContact $publicContacts=null;
    public function __construct(private PDO $pdo) { $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public static function connect(): self
    {
        static $store;
        if ($store) return $store;
        $root=dirname(__DIR__,2);
        require_once $root.'/vendor/autoload.php';
        require_once $root.'/vendor/codeigniter4/framework/system/Config/DotEnv.php';
        (new \CodeIgniter\Config\DotEnv($root))->load();
        $env=static fn($key,$default='')=>$_ENV[$key]??$_SERVER[$key]??(getenv($key)!==false?getenv($key):$default);
        // CI4 protects its own credentials; standalone public pages use the same private environment.
        $host=$env('database.default.hostname','localhost');$port=$env('database.default.port','3306');
        $db=$env('database.default.database');
        $pdo=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$db.';charset=utf8mb4',$env('database.default.username'),$env('database.default.password'),[PDO::ATTR_EMULATE_PREPARES=>false]);
        return $store=new self($pdo);
    }
    public function read(string $name): array
    {
        $table=match($name){'projects'=>'portal_projects','listings'=>'portal_listings',default=>throw new \InvalidArgumentException('Unsupported catalog')};
        $out=[];
        $sql=$name==='listings'
            ? 'SELECT l.*,c.portal_customer_id AS otp_customer_id,c.submission_key AS otp_submission_key,c.delivery_state AS otp_delivery_state,c.verified_at AS otp_verified_at,c.consumed_at AS otp_consumed_at FROM portal_listings l LEFT JOIN resale_otp_challenges c ON c.id=l.resale_verification_id'
            : 'SELECT * FROM '.$table;
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload=json_decode($row['payload'],true,128,JSON_THROW_ON_ERROR);
            if ($name==='projects') {
                $payload['active']=(bool)$row['published'];$out[$row['slug']]=$payload;
            } else {
                $payload['status']=$row['status'];$payload['deal']=$row['deal'];$payload['mapping_id']=$row['mapping_id'];$payload['project']=$row['project_slug'];$payload['_record_version']=(int)$row['record_version'];
                $payload['_publication_verification']=\App\Services\PublicationVerification::summary($row);
                $payload['_selling_method']=$row['selling_method'];$payload['_listing_purpose']=$row['listing_purpose'];$payload['_review_state']=$row['review_state'];$payload['purpose']=self::payloadPurpose($row['listing_purpose'],$payload['purpose']??'');$out[]=$payload;
            }
        }
        return $out;
    }
    /** Preserve original payloads. Never delete omitted records or alter ownership on catalog writes. */
    public function write(string $name,array $rows,bool $onlyMissing=false): void
    {
        $owns=!$this->pdo->inTransaction();if ($owns) $this->pdo->beginTransaction();
        try {
            foreach ($rows as $key=>$row) {
                if ($name==='projects') {
                    $slug=(string)($row['slug']??$key);
                    $sql='INSERT INTO portal_projects(slug,name,published,payload) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE '.($onlyMissing?'slug=slug':'name=VALUES(name),published=VALUES(published),payload=VALUES(payload)');
                    $this->pdo->prepare($sql)->execute([$slug,$row['name'],empty($row['active'])?0:1,json_encode($row,JSON_THROW_ON_ERROR)]);
                } elseif ($name==='listings') {
                    if (!$onlyMissing) {
                        $previous=$this->pdo->prepare('SELECT status,deal,record_version,payload,project_slug,mapping_id,listing_purpose,review_state FROM portal_listings WHERE id=? FOR UPDATE');
                        $previous->execute([$row['id']]);
                        $before=$previous->fetch(PDO::FETCH_ASSOC) ?: null;
                        \App\Services\LegacyListingPolicy::assertCatalogUpdate($before,$row);
                        if($before){
                            $current=json_decode($before['payload'],true,128,JSON_THROW_ON_ERROR);$current['status']=$before['status'];$current['deal']=$before['deal'];$current['mapping_id']=$before['mapping_id'];$current['project']=$before['project_slug'];
                            $current['purpose']=self::payloadPurpose($before['listing_purpose'],$current['purpose']??'');
                            $requested=$row;unset($requested['_record_version'],$requested['_publication_verification'],$requested['_selling_method'],$requested['_listing_purpose'],$requested['_review_state']);
                            if($current===$requested)continue; // A legacy full-catalog save must not touch unrelated rows.
                        }
                        if($before&&($row['_record_version']??null)!==(int)$before['record_version'])throw new \InvalidArgumentException('The listing changed during this legacy action. Reload and review the current record.');
                    }
                    unset($row['_record_version'],$row['_publication_verification'],$row['_selling_method'],$row['_listing_purpose'],$row['_review_state']);
                    // Existing mappings survive legacy payload updates; clients cannot forge a different mapping.
                    $sql='INSERT INTO portal_listings(id,project_slug,status,deal,payload,listing_purpose) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE '.($onlyMissing?'id=id':'project_slug=VALUES(project_slug),status=VALUES(status),deal=VALUES(deal),payload=VALUES(payload),record_version=record_version+1');
                    $this->pdo->prepare($sql)->execute([$row['id'],($row['project']??'')?:null,$row['status']??'pending',$row['deal']??'available',json_encode($row,JSON_THROW_ON_ERROR),($row['purpose']??'')==='rent'?'rent':'sale']);
                } else throw new \InvalidArgumentException('Unsupported catalog');
            }
            if (!$onlyMissing) {
                $table=$name==='projects'?'portal_projects':'portal_listings';
                $column=$name==='projects'?'slug':'id';
                $keys=array_map(static fn($row,$key)=>$name==='projects'?($row['slug']??$key):$row['id'],$rows,array_keys($rows));
                $sql='UPDATE '.$table.' SET '.($name==='projects'?'published=0':"status='removed'");
                if ($keys) $sql.=' WHERE '.$column.' NOT IN ('.implode(',',array_fill(0,count($keys),'?')).')';
                $this->pdo->prepare($sql)->execute($keys);
            }
            if ($owns) $this->pdo->commit();
        } catch (\Throwable $e) { if ($owns&&$this->pdo->inTransaction()) $this->pdo->rollBack();throw $e; }
    }
    private static function payloadPurpose(string $purpose,string $legacy):string
    {
        return $purpose==='rent'?'rent':(in_array($legacy,['sale','project'],true)?$legacy:'resale');
    }
    public function visibleProjectSlugs(): array
    {
        $sql='SELECT DISTINCT p.slug FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE '.ErpVisibility::mappingSql();
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }
    public function visibleListingIds(): array
    {
        $sql='SELECT l.id'.\App\Services\PublicListingSearch::fromSql();
        return array_map('intval',$this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
    public function publicSearch(array $filters,array $purposes=[],bool $paginate=true):array
    {
        $result=(new \App\Services\PublicListingSearch($this->pdo))->search($filters,$purposes,$paginate);
        $this->contacts()->prime(array_column($result['rows'],'id'));
        foreach($result['rows']as&$row)$row['_public_contact']=$this->contacts()->resolve((int)$row['id']);
        return $result;
    }
    public function publicListing(int $id):?array
    {
        return (new \App\Services\PublicListingSearch($this->pdo))->find($id);
    }
    private function contacts():\App\Services\PublicListingContact
    {
        return $this->publicContacts??=new \App\Services\PublicListingContact($this->pdo);
    }
    public function publicContact(int $listingId): array
    {
        return $this->contacts()->resolve($listingId);
    }
    /** Local PDO supplies ERP connection/mapping metadata only; metrics come from authenticated ERP APIs. */
    public function marketSnapshot(array $filters=[]): array
    {
        return (new \App\Services\ErpMarketIntelligenceService($this->pdo))->snapshot($filters);
    }
}
