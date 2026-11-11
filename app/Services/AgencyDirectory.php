<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

final class AgencyDirectory
{
    public function __construct(private BaseConnection $db) {}
    public static function normalize(string $name): string
    {
        if (!mb_check_encoding($name,'UTF-8')) throw new InvalidArgumentException('Enter a valid agency name.');
        return mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)),'UTF-8');
    }
    public static function authorization($authorize,$locked,$restricted): string
    {
        // Existing v1 API combined operational status; retained for compatibility
        // and customer resale checks, not the ERP's agency-directory badge.
        if ($authorize==='no'||$locked==='yes'||$restricted==='yes') return 'unauthorized';
        return $authorize==='yes'&&$locked==='no'&&$restricted==='no'?'authorized':'unknown';
    }
    public static function erpAuthorization($authorize): string
    {
        return $authorize==='yes'?'authorized':($authorize==='no'?'unauthorized':'unknown');
    }
    /** Current source authorizations used by admin lists, alerts and mapping queues. */
    public function authorizedLinks(?int $source=null): \CodeIgniter\Database\BaseBuilder
    {
        $query=$this->db->table('agency_erp_links l')->join('erp_sources s','s.id=l.erp_source_id')
            ->where('s.is_active',1)->where('l.present',1)->where("BINARY l.erp_authorize=BINARY 'yes'",null,false);
        if($source!==null)$query->where('l.erp_source_id',$source);
        return $query;
    }
    public function unmappedLinks(?int $source=null): \CodeIgniter\Database\BaseBuilder
    {
        return $this->authorizedLinks($source)->select('l.*,s.name AS source_name')->where('l.mapping_status','unmapped');
    }
    public function unmappedCount(?int $source=null): int
    {
        return $this->db->tableExists('agency_erp_links')?$this->unmappedLinks($source)->countAllResults():0;
    }
    public function portalAgencies(): \CodeIgniter\Database\BaseBuilder
    {
        $authorized=$this->authorizedLinks()->select('1',false)->where('l.portal_agency_id=a.id',null,false)->where('l.mapping_status','mapped')->getCompiledSelect();
        return $this->db->table('authorized_agencies a')->select('a.*')->where('EXISTS ('.$authorized.')',null,false);
    }
    public function isMappable(array $link): bool
    {
        return (int)$link['present']===1&&self::erpAuthorization($link['erp_authorize'])==='authorized'
            &&(bool)$this->db->table('erp_sources')->where('id',$link['erp_source_id'])->where('is_active',1)->countAllResults();
    }
    private function requireMappable(array $link): void
    {
        if(!$this->isMappable($link))throw new InvalidArgumentException('Only currently authorized agencies from enabled ERP sources can be mapped. Refresh the agency list.');
    }
    public function suggestions(string $normalized): array
    {
        if ($normalized==='') return [];
        return $this->db->table('authorized_agencies')->select('id,agency_name,portal_active')->where('normalized_name',$normalized)->orderBy('agency_name')->orderBy('id')->get()->getResultArray();
    }
    public function eligibleForSource(int $source,bool $lock=false,?int $link=null): array
    {
        $params=[$source];$tail='';if($link!==null){$tail=' AND l.id=?';$params[]=$link;}
        return $this->db->query(self::eligibleSql().$tail.' ORDER BY a.agency_name,l.id'.($lock?' FOR UPDATE':''),$params)->getResultArray();
    }
    /** Same mapped central directory as Admin, scoped to this property's ERP.
     * Unavailable agencies remain visible with a reason and cannot be selected.
     */
    public function resaleOptionsForSource(int $source):array
    {
        $eligible=array_fill_keys(array_column($this->eligibleForSource($source),'agency_erp_link_id'),true);
        $rows=$this->authorizedLinks($source)->join('authorized_agencies a','a.id=l.portal_agency_id')
            ->select('l.id AS agency_erp_link_id,a.id AS portal_agency_id,a.agency_name,l.erp_agency_id,a.portal_active,l.erp_locked,l.erp_restricted,l.erp_authorization_status')
            ->where('l.mapping_status','mapped')->where('a.agency_type','external')->where('a.is_shalimar_agency',0)
            ->orderBy('a.agency_name')->orderBy('l.id')->get()->getResultArray();
        foreach($rows as&$row){
            $row['selectable']=isset($eligible[$row['agency_erp_link_id']]);$row['unavailable_reason']='';
            if($row['selectable'])continue;
            if(!(int)$row['portal_active'])$row['unavailable_reason']='Portal disabled';
            elseif($row['erp_locked']!=='no')$row['unavailable_reason']=$row['erp_locked']==='yes'?'ERP locked':'ERP lock status unconfirmed';
            else $row['unavailable_reason']='ERP eligibility unconfirmed';
        }unset($row);
        return $rows;
    }
    /** Shared by customer selection and standalone public contact resolution. */
    public static function resaleAuthorizationSql(string $alias):string
    {
        if(!in_array($alias,['l','al'],true))throw new InvalidArgumentException('Invalid agency link alias.');
        return "BINARY $alias.erp_authorize=BINARY 'yes' AND BINARY $alias.erp_locked=BINARY 'no'";
    }
    public static function eligibleSql(): string
    {
        // ERP authorize is authoritative. The legacy aggregate status also included
        // restricted, which is explicitly not a customer resale exclusion.
        return "SELECT l.id AS agency_erp_link_id,a.id AS portal_agency_id,a.agency_name,a.phone,a.email,a.city,l.erp_agency_id FROM agency_erp_links l JOIN authorized_agencies a ON a.id=l.portal_agency_id JOIN erp_sources s ON s.id=l.erp_source_id WHERE l.erp_source_id=? AND s.is_active=1 AND a.portal_active=1 AND a.agency_type='external' AND a.is_shalimar_agency=0 AND l.present=1 AND l.mapping_status='mapped' AND ".self::resaleAuthorizationSql('l');
    }
    public function requireEligible(int $source,int $link,bool $lock=false): array
    {
        foreach ($this->eligibleForSource($source,$lock,$link) as $row) if ((int)$row['agency_erp_link_id']===$link) return $row;
        throw new InvalidArgumentException('Choose an active, mapped agency authorized for this property’s ERP source.');
    }
    public function link(int $id): array
    {
        $row=$this->db->query('SELECT l.*,s.name AS source_name,s.is_active AS source_active,a.agency_name AS portal_name FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id LEFT JOIN authorized_agencies a ON a.id=l.portal_agency_id WHERE l.id=?',[$id])->getRowArray();
        if (!$row) throw new InvalidArgumentException('ERP agency link not found.');
        return $row;
    }
    private function transaction(callable $action)
    {
        $this->db->transBegin();
        try {$result=$action();if (!$this->db->transStatus()) throw new RuntimeException('Agency change could not be saved.');if (!$this->db->transCommit()) throw new RuntimeException('Agency change could not commit.');return $result;}
        catch (\Throwable $e) {$this->db->transRollback();$this->db->resetTransStatus();throw $e;}
    }
    public function map(int $linkId,int $agencyId,int $actor): void
    {
        $this->transaction(function()use($linkId,$agencyId,$actor){
            $link=$this->db->query('SELECT * FROM agency_erp_links WHERE id=? FOR UPDATE',[$linkId])->getRowArray();
            $agency=$this->db->query('SELECT * FROM authorized_agencies WHERE id=? FOR UPDATE',[$agencyId])->getRowArray();
            if (!$link||!$agency) throw new InvalidArgumentException('Choose an existing ERP link and portal agency.');
            // A repeated confirmation is harmless. Remapping requires a separate explicit workflow.
            if ($link['portal_agency_id']!==null) {if ((int)$link['portal_agency_id']===$agencyId) return;throw new InvalidArgumentException('This ERP agency is already confirmed. Its existing mapping has been preserved.');}
            $this->requireMappable($link);
            $change=['portal_agency_id'=>$agencyId,'mapping_status'=>'mapped','mapped_by'=>$actor,'mapped_at'=>$this->now(),'updated_at'=>$this->now()];
            $this->db->table('agency_erp_links')->where('id',$linkId)->update($change);
            (new AdminAuditService($this->db))->record('agency.mapping_confirmed','agencies',$actor,'agency_erp_link',$linkId,$link,$change+['erp_source_id'=>$link['erp_source_id'],'erp_agency_id'=>$link['erp_agency_id']]);
        });
    }
    private static function selectionId($value): int
    {
        if ((!is_string($value)&&!is_int($value))||!preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$value)) throw new InvalidArgumentException('Choose valid ERP links and suggested portal agencies.');
        return (int)$value;
    }
    private static function mappingFingerprint(array $link,array $agency): string
    {
        return hash('sha256',json_encode([
            (string)$link['id'],(string)$link['erp_source_id'],(string)$link['erp_agency_id'],$link['erp_agency_name'],$link['normalized_name'],
            $link['erp_authorize'],$link['erp_locked'],$link['erp_restricted'],$link['erp_authorization_status'],(int)$link['present'],
            (string)$agency['id'],$agency['agency_name'],$agency['normalized_name'],(int)$agency['portal_active'],
        ],JSON_THROW_ON_ERROR));
    }
    private static function requireNameMatch(array $link,array $agency): void
    {
        if ($link['normalized_name']===''||$link['normalized_name']!==$agency['normalized_name']) throw new InvalidArgumentException('A selected agency is no longer a suggested name match. Review the selected rows again.');
    }
    private function proposedAgency(array $link): array
    {
        $contact=json_decode($link['contact_json'],true);if(!is_array($contact))$contact=[];
        $email=trim((string)($contact['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>255)$email='';
        return $this->agencyData(['agency_name'=>$link['erp_agency_name'],'phone'=>mb_substr(trim((string)(($contact['cell']??'')?:($contact['phone']??''))),0,100),'email'=>$email,'city'=>mb_substr((string)($contact['city']??''),0,255),'address'=>mb_substr((string)($contact['address']??''),0,2000)]);
    }
    private static function creationFingerprint(array $link,array $data): string
    {
        return hash('sha256',self::mappingFingerprint($link,$data+['id'=>0,'portal_active'=>1]).json_encode($data,JSON_THROW_ON_ERROR));
    }
    /** Prepare explicit map/create choices for admin review; never writes records. */
    public function reviewMappings(array $choices,bool $allowCreate=false): array
    {
        if (!$choices||!array_is_list($choices)||count($choices)>100) throw new InvalidArgumentException('Select between 1 and 100 agencies for review.');
        $rows=[];$seen=[];$newNames=[];
        foreach ($choices as $choice) {
            if (!is_array($choice)) throw new InvalidArgumentException('Invalid agency selection.');
            $id=self::selectionId($choice['link_id']??null);$target=$choice['portal_agency_id']??null;
            if (isset($seen[$id])) throw new InvalidArgumentException('An ERP agency was selected more than once.');$seen[$id]=true;
            $link=$this->link($id);
            $this->requireMappable($link);
            if ($link['portal_agency_id']!==null||$link['mapping_status']!=='unmapped') throw new InvalidArgumentException('A selected ERP agency is already mapped. Refresh the unmapped list.');
            $base=['link_id'=>$id,'source_name'=>$link['source_name'],'erp_agency_id'=>$link['erp_agency_id'],'erp_agency_name'=>$link['erp_agency_name'],'authorization'=>self::erpAuthorization($link['erp_authorize'])];
            if ($target==='new') {
                if(!$allowCreate)throw new InvalidArgumentException('Creating portal agencies requires agency-create permission.');
                $data=$this->proposedAgency($link);
                if($this->suggestions($data['normalized_name']))throw new InvalidArgumentException('A portal agency with this name now exists. Refresh and select its suggested match.');
                if(isset($newNames[$data['normalized_name']]))throw new InvalidArgumentException('Multiple new agencies named "'.$data['agency_name'].'" are selected. Create one first, then explicitly map the others to its suggested record.');
                $newNames[$data['normalized_name']]=true;
                $rows[]=$base+['action'=>'create','portal_agency_id'=>null,'portal_agency_name'=>$data['agency_name'],'portal_active'=>1,'agency_data'=>$data,'fingerprint'=>self::creationFingerprint($link,$data)];
            }else{
                $agencyId=self::selectionId($target);$agency=$this->db->table('authorized_agencies')->where('id',$agencyId)->get()->getRowArray();
                if (!$agency) throw new InvalidArgumentException('A selected portal agency no longer exists.');self::requireNameMatch($link,$agency);
                $rows[]=$base+['action'=>'map','portal_agency_id'=>$agencyId,'portal_agency_name'=>$agency['agency_name'],'portal_active'=>(int)$agency['portal_active'],'fingerprint'=>self::mappingFingerprint($link,$agency)];
            }
        }
        return $rows;
    }
    /** Apply a reviewed map/create batch atomically. Creation always needs explicit permission. */
    public function bulkMap(array $review,int $actor,bool $allowCreate=false): array
    {
        if (!$review||!array_is_list($review)||count($review)>100) throw new InvalidArgumentException('Review between 1 and 100 agencies before confirming.');
        $choices=[];$agencyIds=[];$newNames=[];$locks=[];
        foreach ($review as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Invalid mapping review.');
            $id=self::selectionId($row['link_id']??null);$action=$row['action']??'map';$fingerprint=$row['fingerprint']??null;
            if (isset($choices[$id])||!is_string($fingerprint)||!preg_match('/\A[a-f0-9]{64}\z/D',$fingerprint)||!in_array($action,['map','create'],true)) throw new InvalidArgumentException('Invalid or repeated mapping review.');
            if($action==='create'){
                if(!$allowCreate)throw new InvalidArgumentException('Creating portal agencies requires agency-create permission.');
                $data=$this->agencyData($row['agency_data']??[]);if(isset($newNames[$data['normalized_name']]))throw new InvalidArgumentException('Duplicate new names in this review. Review one record per new agency name.');$newNames[$data['normalized_name']]=true;
                $choices[$id]=['action'=>'create','fingerprint'=>$fingerprint];
            }else{$agency=self::selectionId($row['portal_agency_id']??null);$choices[$id]=['action'=>'map','agency'=>$agency,'fingerprint'=>$fingerprint];$agencyIds[$agency]=true;}
        }
        ksort($choices,SORT_NUMERIC);ksort($agencyIds,SORT_NUMERIC);ksort($newNames,SORT_STRING);
        try{
            // Hold name locks through commit to prevent concurrent duplicate creation.
            foreach($newNames as$name=>$_){$lock='agency_name_'.substr(hash('sha256',$this->db->getDatabase().':'.$name),0,44);if((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1)throw new InvalidArgumentException('A selected agency name is being created. Retry the review.');$locks[]=$lock;}
            return $this->transaction(function()use($choices,$agencyIds,$actor){
                $links=[];$agencies=[];
                foreach ($choices as $id=>$choice) {$link=$this->db->query('SELECT * FROM agency_erp_links WHERE id=? FOR UPDATE',[$id])->getRowArray();if (!$link) throw new InvalidArgumentException('A reviewed ERP agency no longer exists.');$links[$id]=$link;}
                foreach ($agencyIds as $id=>$_) {$agency=$this->db->query('SELECT * FROM authorized_agencies WHERE id=? FOR UPDATE',[$id])->getRowArray();if (!$agency) throw new InvalidArgumentException('A reviewed portal agency no longer exists.');$agencies[$id]=$agency;}
                $mapped=0;$already=0;$created=0;
                foreach ($choices as $id=>$choice) {
                    $link=$links[$id];$this->requireMappable($link);$new=$choice['action']==='create';
                    if($new){
                        $data=$this->proposedAgency($link);
                        if(!hash_equals($choice['fingerprint'],self::creationFingerprint($link,$data)))throw new InvalidArgumentException('Agency details changed after review. No records were changed; review again.');
                        if($link['portal_agency_id']!==null)throw new InvalidArgumentException('A selected agency has already been mapped. Refresh and review again.');
                        if($this->suggestions($data['normalized_name']))throw new InvalidArgumentException('A matching portal agency was created after review. No records were changed; refresh to confirm its suggested match.');
                        $data+=['portal_active'=>1,'created_at'=>$this->now(),'updated_at'=>$this->now()];$this->db->table('authorized_agencies')->insert($data);$agencyId=(int)$this->db->insertID();$created++;
                    }else{
                        $agency=$agencies[$choice['agency']];
                        if(!hash_equals($choice['fingerprint'],self::mappingFingerprint($link,$agency)))throw new InvalidArgumentException('Agency details changed after review. No mappings were changed; review the current matches again.');self::requireNameMatch($link,$agency);$agencyId=$choice['agency'];
                        if($link['portal_agency_id']!==null){if((int)$link['portal_agency_id']===$agencyId){$already++;continue;}throw new InvalidArgumentException('Another administrator mapped a selected ERP agency differently. Existing confirmations were preserved; review again.');}
                    }
                    $change=['portal_agency_id'=>$agencyId,'mapping_status'=>'mapped','mapped_by'=>$actor,'mapped_at'=>$this->now(),'updated_at'=>$this->now()];$this->db->table('agency_erp_links')->where('id',$id)->update($change);
                    (new AdminAuditService($this->db))->record($new?'agency.created_and_mapped':'agency.mapping_confirmed','agencies',$actor,'agency_erp_link',$id,$link,$change+['agency_name'=>$new?$data['agency_name']:$agency['agency_name'],'erp_source_id'=>$link['erp_source_id'],'erp_agency_id'=>$link['erp_agency_id']]);$mapped++;
                }
                $result=['mapped'=>$mapped,'already_mapped'=>$already];if($created)$result['created']=$created;return $result;
            });
        }finally{foreach(array_reverse($locks)as$lock)$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    private function agencyData(array $input): array
    {
        $name=$input['agency_name']??null;
        if (!is_string($name)||mb_strlen($name)>255||self::normalize($name)==='') throw new InvalidArgumentException('Enter an agency name of up to 255 characters.');
        $data=['agency_name'=>trim(preg_replace('/\s+/u',' ',$name)),'normalized_name'=>self::normalize($name)];
        foreach (['phone'=>100,'email'=>255,'city'=>255,'address'=>2000] as $key=>$limit) {
            $value=$input[$key]??'';if (!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$limit) throw new InvalidArgumentException('Check the agency contact information.');$data[$key]=trim($value);
        }
        $data['email']=PublicContactEmail::optional($data['email']);
        return $data;
    }
    public function createAndMap(int $linkId,array $input,int $actor): int
    {
        $data=$this->agencyData($input);
        $lock='agency_name_'.substr(hash('sha256',$this->db->getDatabase().':'.$data['normalized_name']),0,44);
        if ((int)$this->db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRow()->acquired!==1) throw new InvalidArgumentException('This agency name is being mapped. Please retry.');
        try {
            return $this->transaction(function()use($linkId,$data,$actor){
                $link=$this->db->query('SELECT * FROM agency_erp_links WHERE id=? FOR UPDATE',[$linkId])->getRowArray();
                if (!$link||$link['portal_agency_id']!==null) throw new InvalidArgumentException('Choose an unmapped ERP agency.');
                $this->requireMappable($link);
                if ($this->suggestions($data['normalized_name'])) throw new InvalidArgumentException('A portal agency with this normalized name already exists. Confirm an existing agency instead of creating a duplicate.');
                $data+=['portal_active'=>1,'created_at'=>$this->now(),'updated_at'=>$this->now()];
                $this->db->table('authorized_agencies')->insert($data);$id=(int)$this->db->insertID();
                $change=['portal_agency_id'=>$id,'mapping_status'=>'mapped','mapped_by'=>$actor,'mapped_at'=>$this->now(),'updated_at'=>$this->now()];
                $this->db->table('agency_erp_links')->where('id',$linkId)->update($change);
                (new AdminAuditService($this->db))->record('agency.created_and_mapped','agencies',$actor,'agency_erp_link',$linkId,$link,$change+['agency_name'=>$data['agency_name'],'erp_source_id'=>$link['erp_source_id'],'erp_agency_id'=>$link['erp_agency_id']]);
                return $id;
            });
        } finally {$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public function update(int $id,array $input,int $actor): void
    {
        $this->transaction(function()use($id,$input,$actor){$old=$this->db->query('SELECT * FROM authorized_agencies WHERE id=? FOR UPDATE',[$id])->getRowArray();if (!$old) throw new InvalidArgumentException('Agency not found.');$data=$this->agencyData($input)+['updated_at'=>$this->now()];$this->db->table('authorized_agencies')->where('id',$id)->update($data);(new AdminAuditService($this->db))->record('agency.updated','agencies',$actor,'authorized_agency',$id,$old,$data);});
    }
    public function setActive(int $id,bool $active,int $actor): void
    {
        $this->transaction(function()use($id,$active,$actor){$old=$this->db->query('SELECT * FROM authorized_agencies WHERE id=? FOR UPDATE',[$id])->getRowArray();if (!$old) throw new InvalidArgumentException('Agency not found.');$data=['portal_active'=>(int)$active,'updated_at'=>$this->now()];$this->db->table('authorized_agencies')->where('id',$id)->update($data);if($this->db->tableExists('shalimar_agent_settings'))(new ShalimarAgents($this->db))->flagAssignments();(new AdminAuditService($this->db))->record('agency.status_changed','agencies',$actor,'authorized_agency',$id,$old,$data);});
    }
    private function now(): string {return (new \DateTimeImmutable('now',new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');}
}
