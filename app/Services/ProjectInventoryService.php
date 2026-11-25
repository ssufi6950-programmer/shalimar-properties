<?php
declare(strict_types=1);
namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Builds a public, read-only projection from exactly mapped ERP project identities. */
final class ProjectInventoryService
{
    public function __construct(private BaseConnection $db, private ProjectInventoryTransport $transport) {}

    public static function enabled(): bool
    {
        $value=$_ENV['projectInventory.enabled']??$_SERVER['projectInventory.enabled']??getenv('projectInventory.enabled');
        return is_string($value)&&in_array(strtolower($value), ['1','true','yes','on'], true);
    }

    private function mappings(string $slug): array
    {
        return $this->db->query(
            'SELECT m.erp_source_id,m.erp_head_id,m.erp_subhead_id,s.code,s.name AS source_name,s.base_url,s.auth_type,s.credential_ref,s.is_active,sh.name AS mapped_subhead_name '
            .'FROM erp_project_mappings m'.ErpVisibility::joins().' WHERE m.project_slug=? AND '.ErpVisibility::mappingSql().' ORDER BY m.erp_source_id,m.erp_head_id,m.erp_subhead_id',
            [$slug]
        )->getResultArray();
    }

    public function load(string $slug): array
    {
        $mappings = $this->mappings($slug);
        if (!$mappings) throw new \CodeIgniter\Exceptions\PageNotFoundException('No verified ERP project mapping is available.');
        $groups = [];
        foreach ($mappings as $map) {
            $key = (string)$map['erp_source_id'];
            $groups[$key]['source'] = [
                'id'=>(int)$map['erp_source_id'],'code'=>(string)$map['code'],'name'=>(string)$map['source_name'],
                'base_url'=>$map['base_url'],'auth_type'=>$map['auth_type'],'credential_ref'=>$map['credential_ref'],'is_active'=>(int)$map['is_active'],
            ];
            $head = (string)$map['erp_head_id'];
            $groups[$key]['scopes'][$head] = $groups[$key]['scopes'][$head] ?? [];
            $groups[$key]['scopes'][$head][(string)$map['erp_subhead_id']] = (string)$map['mapped_subhead_name'];
        }
        $result = ['project'=>$slug,'generated_at'=>date(DATE_ATOM),'sources'=>[],'available'=>count($groups)>0,'message'=>null];
        foreach ($groups as $group) {
            $source = $group['source']; $scopes=[];
            if(count($group['scopes'])>100)throw new \RuntimeException('This project has too many mapped inventory groups for one safe ERP request.');
            $subheadNames=[];$allowed=[];
            foreach ($group['scopes'] as $head=>$subheads) {
                if(count($subheads)>100)throw new \RuntimeException('This project has too many mapped subheads under one Head.');
                $scopes[]=['head_id'=>(string)$head,'subhead_ids'=>array_keys($subheads)];
                foreach($subheads as$id=>$name){$subheadNames[(string)$id]=$name;$allowed[(string)$head.':'.(string)$id]=true;}
            }
            $after='0';$rows=[];$pages=0;$companyId=null;
            do {
                if (++$pages > 100) throw new \RuntimeException('The inventory exceeds the safe page limit.');
                $data=$this->transport->fetch($source,['scopes'=>$scopes,'after_id'=>$after,'page_size'=>500]);
                if (!isset($data['records'],$data['has_more'],$data['next_after_id'],$data['company_id']) || !is_array($data['records']) || !is_bool($data['has_more']) || !is_string((string)$data['next_after_id']) || !ctype_digit((string)$data['company_id'])) throw new \RuntimeException('ERP inventory response did not match the expected schema.');
                if ($companyId!==null && $companyId!==(string)$data['company_id']) throw new \RuntimeException('ERP company identity changed between pages.');
                $companyId=(string)$data['company_id'];
                $last=(int)$after;
                foreach ($data['records'] as $row) {
                    if (!is_array($row) || !ctype_digit((string)($row['property_id']??'')) || (int)$row['property_id'] <= $last || !isset($allowed[(string)($row['head_id']??'').':'.(string)($row['subhead_id']??'')]) || !in_array($row['availability_status']??null,['available','booked','other'],true) || !is_string($row['property_number']??null) || !is_string($row['sector']??null) || !is_string($row['property_type']??null) || !is_string($row['size']??null)) throw new \RuntimeException('ERP inventory record failed validation.');
                    $last=(int)$row['property_id'];
                    $row['source_code']=$source['code'];$row['source_name']=$source['name'];$row['company_id']=$companyId;
                    $rows[]=$row;
                }
                if ((string)$last !== (string)$data['next_after_id'] || ($data['has_more'] && !$data['records'])) throw new \RuntimeException('ERP inventory cursor is inconsistent.');
                $after=(string)$data['next_after_id'];
                if ($data['has_more'] && (int)$after<=0) throw new \RuntimeException('ERP inventory cursor did not advance.');
            } while ($data['has_more']);
            $result['sources'][]=$this->summarize($source,$companyId??'', $rows,$subheadNames);
        }
        if (count($groups)>1) $result['message']='This project maps to multiple ERP sources. Counts are shown separately by source and company to prevent ID collisions or double counting.';
        return $result;
    }

    private function summarize(array $source,string $company,array $rows,array $subheadNames): array
    {
        $subheads=[];$sectors=[];$totals=['total'=>0,'available'=>0,'booked'=>0,'other'=>0];$seen=[];
        foreach($subheadNames as$id=>$name)$subheads[$id]=['id'=>$id,'name'=>$name,'total'=>0,'available'=>0,'booked'=>0,'other'=>0];
        foreach($rows as $row){
            $identity=$source['id'].':'.$company.':'.$row['property_id'];
            if(isset($seen[$identity]))throw new \RuntimeException('ERP returned duplicate inventory identity.');
            $seen[$identity]=true;
            $state=$row['availability_status'];$totals['total']++;$totals[$state]++;
            $sub=(string)($row['subhead_id']??'');$subName=(string)($row['subhead_name']??'');
            $sector=(string)($row['sector']??'');$sectorKey=$sector!==''?$sector:'__unrecorded__';
            foreach ([['subheads',$sub,$subName],['sectors',$sectorKey,$sector!==''?$sector:'Not recorded']] as [$kind,$key,$name]) {
                $bucket=&$$kind;
                if(!isset($bucket[$key]))$bucket[$key]=['id'=>$kind==='subheads'?$key:null,'name'=>$name,'total'=>0,'available'=>0,'booked'=>0,'other'=>0];
                $bucket[$key]['total']++;$bucket[$key][$state]++;
                unset($bucket);
            }
        }
        ksort($subheads,SORT_NATURAL);ksort($sectors,SORT_NATURAL);
        foreach($rows as&$row){unset($row['property_id'],$row['erp_inventory_status']);}unset($row);
        if($totals['total']!==$totals['available']+$totals['booked']+$totals['other'])throw new \RuntimeException('Inventory status totals do not reconcile.');
        $fetchedAt=date(DATE_ATOM);
        return ['source_id'=>$source['id'],'source_code'=>$source['code'],'source_name'=>$source['name'],'company_id'=>$company,'counts'=>$totals,'subheads'=>array_values($subheads),'sectors'=>array_values($sectors),'units'=>$rows,'fetched_at'=>$fetchedAt,'as_of'=>$fetchedAt];
    }
}
