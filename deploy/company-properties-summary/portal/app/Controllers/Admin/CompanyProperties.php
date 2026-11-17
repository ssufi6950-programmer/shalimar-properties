<?php
namespace App\Controllers\Admin;
final class CompanyProperties extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    public function index()
    {
        if(!can('properties.view')) return admin_denied();
        $db=db_connect();
        $client=new \App\Services\ExplorerErpClient(new \App\Models\ErpSourceModel($db),config('ErpConnections'));
        $sections=[];$rows=[];
        $mappings=$db->table('erp_project_mappings m')->select('m.erp_source_id,m.erp_head_id,m.erp_subhead_id,p.name AS project_name')->join('portal_projects p','p.slug=m.project_slug')->where('m.is_active',1)->get()->getResultArray();
        $projects=[];foreach($mappings as$mapping)$projects[$mapping['erp_source_id'].':'.$mapping['erp_head_id'].':'.$mapping['erp_subhead_id']][]=$mapping['project_name'];
        foreach(['SGC','ZALMI'] as $code){
            $section=['code'=>$code,'rows'=>[],'error'=>null,'more'=>false,'next'=>'0'];
            try {
                $sources=$db->table('erp_sources')->where('code',$code)->where('is_active',1)->limit(2)->get()->getResultArray();
                if(count($sources)!==1) throw new \RuntimeException('source_unavailable');
                $after=$this->request->getGet(strtolower($code).'_after')??'0';
                if(!is_string($after)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$after)) throw new \InvalidArgumentException('Invalid cursor.');
                $data=$client->companyProperties($sources[0],$after);
                if(!isset($data['records'],$data['has_more'],$data['next_after_id'])||!is_array($data['records'])||count($data['records'])>50||!is_bool($data['has_more'])) throw new \RuntimeException('invalid_response');
                $last=(int)$after;
                foreach($data['records'] as $row){
                    if(!is_array($row)||($row['smp_active']??null)!=='yes'||!isset($row['id'])||!ctype_digit((string)$row['id'])||(int)$row['id']<=$last) throw new \RuntimeException('invalid_inventory_selection');
                    foreach($row as $value) if(!is_scalar($value)&&$value!==null) throw new \RuntimeException('invalid_response');
                    $last=(int)$row['id'];
                }
                if((string)$last!==(string)$data['next_after_id']||($data['has_more']&&!$data['records'])) throw new \RuntimeException('invalid_cursor');
                foreach($data['records'] as &$record){
                    $names=array_unique($projects[$sources[0]['id'].':'.($record['head']??'').':'.($record['sub_head']??'')]??[]);
                    $record['project_name']=count($names)===1?reset($names):($record['head_name']??'');
                    $record['source_code']=$code;
                }unset($record);
                $section['rows']=$data['records'];
                $rows=array_merge($rows,$data['records']);$section['more']=$data['has_more'];$section['next']=(string)$last;
            } catch(\Throwable $e){
                log_message('error','Company properties fetch failed: source={source} type={type}',['source'=>$code,'type'=>get_class($e)]);
                $section['error']='Selected inventory could not be fetched. Check the enabled source, bearer credentials, company-properties.php API upload and pro_inventory.smp_active column.';
            }
            $sections[]=$section;
        }
        return view('admin/company_properties',['pageTitle'=>'Company Properties','tab'=>'company-properties','sections'=>$sections,'rows'=>$rows]);
    }
}
