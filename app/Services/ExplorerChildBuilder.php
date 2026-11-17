<?php
declare(strict_types=1);
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/** Admin child configuration only. Existing root Builder and public matching stay separate. */
final class ExplorerChildBuilder
{
    public function __construct(private BaseConnection $db) {}

    /** One bounded local read for all immediate-child summaries; never contacts ERP. */
    public function summaries(string $slug): array
    {
        $rows=$this->db->query('SELECT * FROM portal_explorer_filters WHERE project_slug=? ORDER BY id LIMIT 10001',[$slug])->getResultArray();
        if(count($rows)>10000)throw new InvalidArgumentException('Project exceeds the 10000-node summary budget.');
        $nodes=[];$out=[];foreach($rows as$row)$nodes[(string)$row['id']]=$row;
        foreach($rows as$row){
            if($row['parent_filter_id']===null)continue;
            $parent=(string)$row['parent_filter_id'];$p=$nodes[$parent]??null;
            if(!$p||(string)$row['erp_source_id']!==(string)$p['erp_source_id']||(string)$row['erp_head_id']!==(string)$p['erp_head_id'])continue;
            $out[$parent]??=['name'=>$p['display_name'],'source_id'=>(string)$p['erp_source_id'],'head_id'=>(string)$p['erp_head_id'],'count'=>0,'visible'=>0,'fields'=>[],'children'=>[]];
            $out[$parent]['count']++;$out[$parent]['visible']+=(int)$row['is_visible'];
            $out[$parent]['fields'][$row['source_field']]=self::fieldLabel($row['source_field']);
            $out[$parent]['children'][]=['id'=>(string)$row['id'],'name'=>$row['display_name'],'visible'=>(bool)$row['is_visible'],'order'=>(int)$row['display_order']];
        }
        foreach($out as&$summary)usort($summary['children'],static fn($a,$b)=>[$a['order'],$a['id']]<=>[$b['order'],$b['id']]);unset($summary);
        return $out;
    }

    public static function fieldLabel(string $field): string
    {
        return ['item_sublocation.id'=>'Sub Location','pro_inventory.floor_no'=>'Inventory Floor','pro_inventory.block_no'=>'Block','pro_inventory.sector'=>'Sector','pro_inventory.tower'=>'Tower'][$field]??ucwords(str_replace('_',' ',substr($field,strpos($field,'.')+1)));
    }

    /** Prepare a reviewed copy; this operation never writes settings. */
    public function copyDraft(array $destination,string $sourceParent,array $catalog,callable $transport): array
    {
        self::id($sourceParent);
        if($sourceParent===$destination['parent_id'])throw new InvalidArgumentException('Choose a different parent to copy from.');
        $source=$this->context($destination['project'],$sourceParent);
        if(!$source['children']||$source['child_field']===null)throw new InvalidArgumentException('The source parent has no child options to copy.');
        if(count($source['children'])>128)throw new InvalidArgumentException('Copy supports up to 128 immediate child options per operation.');
        $field=$source['child_field'];$available=$this->fetch($destination,$field,$catalog,$transport);
        $existing=[];foreach($destination['children']as$row)$existing[self::key($destination,$row['source_field'],$row['source_value'])]=true;
        $options=[];$skipped=0;$kept=0;
        foreach($source['children']as$row){
            $key=self::key($destination,$field,$row['source_value']);
            if(isset($existing[$key])){$kept++;continue;}
            if(!isset($available[$key])){$skipped++;continue;}
            $options[$key]=$available[$key]+['display_name'=>$row['display_name'],'icon'=>$row['icon'],'is_visible'=>(int)$row['is_visible'],'display_order'=>(int)$row['display_order'],'copy_selected'=>true];
        }
        return ['field'=>$field,'options'=>$options,'copy_source'=>$sourceParent,'copied'=>count($options),'skipped'=>$skipped,'kept'=>$kept];
    }

    public static function id(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$value))throw new InvalidArgumentException('Select a valid saved parent filter.');
        return $value;
    }
    public static function field(mixed $value): string
    {
        if(!is_string($value)||strlen($value)>64||!preg_match('/\A(?:item_sublocation\.id|pro_inventory\.[A-Za-z_][A-Za-z0-9_]*)\z/D',$value)||in_array($value,['pro_inventory.phase','pro_inventory.street_no','pro_inventory.size','pro_inventory.size_code'],true))throw new InvalidArgumentException('Select an available safe field; Phase, Street and composite Size are excluded.');
        return $value;
    }
    public static function key(array $context,string $field,string $value): string
    {
        return hash('sha256',json_encode([$context['project'],$context['source_id'],$context['head_id'],$context['parent_id'],$field,$value],JSON_THROW_ON_ERROR));
    }
    public static function readiness(string $field): array
    {
        $paths=['item_sublocation.id'=>'Existing source-specific mapping identity.','pro_inventory.sector'=>'Exact source-bound listing payload.sector where recorded.','pro_inventory.block_no'=>'Exact listing payload.block where recorded; missing values cannot match.'];
        $supported=isset($paths[$field])||ExplorerListingSnapshot::supports($field);
        return ['metadata_available'=>$supported,'public_children_enabled'=>$supported,'message'=>$paths[$field]??($supported?'Exact inventory snapshot supported for newly submitted/reviewed listings after the ownership API update. Older listings without this value do not match.':'Public matching unavailable: this field has no approved snapshot adapter.')];
    }

    public function context(string $slug,string $parent,bool $lock=false): array
    {
        self::id($parent);$scope=(new ExplorerFilterBuilder($this->db))->scope($slug,$lock);
        $rows=$this->db->query('SELECT * FROM portal_explorer_filters WHERE project_slug=? ORDER BY id LIMIT 10001'.($lock?' FOR UPDATE':''),[$slug])->getResultArray();
        if(count($rows)>10000)throw new InvalidArgumentException('This project exceeds the 10000-node operation budget; no tree was truncated.');
        $nodes=[];foreach($rows as$row)$nodes[(string)$row['id']]=$row;
        $path=[];$seen=[];$id=$parent;
        while($id!==null){
            if(isset($seen[$id]))throw new InvalidArgumentException('Parent cycle detected. Configuration was not changed.');
            $seen[$id]=true;$node=$nodes[$id]??null;
            if(!$node||$node['project_slug']!==$slug||(string)$node['erp_source_id']!==$scope['source_id']||(string)$node['erp_head_id']!==$scope['head_id'])throw new InvalidArgumentException('Parent is missing or crosses the project/source/Head scope.');
            self::field($node['source_field']);
            if(PropertyExplorer::value($node['source_value'])===null)throw new InvalidArgumentException('Ancestor has an invalid source identity.');
            $path[]=$node;$id=$node['parent_filter_id']===null?null:(string)$node['parent_filter_id'];
        }
        $path=array_reverse($path);$root=$path[0];
        if($root['source_field']!==$scope['field']||($scope['field']==='item_sublocation.id'&&!in_array($root['source_value'],$scope['subheads'],true)))throw new InvalidArgumentException('Root no longer matches this project mapping.');
        // Check the sibling-field invariant at every selected ancestor, not just the leaf.
        $branchFields=[];
        foreach($rows as$row){
            if($row['parent_filter_id']===null)continue;
            $pid=(string)$row['parent_filter_id'];$branchFields[$pid][$row['source_field']]=true;
        }
        foreach($path as$node)if(count($branchFields[(string)$node['id']]??[])>1)throw new InvalidArgumentException('An ancestor has inconsistent sibling fields; review the saved branch first.');
        $conditions=[];foreach($path as$node){$f=$node['source_field'];if(isset($conditions[$f])&&$conditions[$f]!==$node['source_value'])throw new InvalidArgumentException('Ancestor conditions contradict each other.');$conditions[$f]=$node['source_value'];}
        $children=array_values(array_filter($rows,static fn($r)=>$r['parent_filter_id']!==null&&(string)$r['parent_filter_id']===$parent));
        $fields=[];foreach($children as$child){
            if((string)$child['erp_source_id']!==$scope['source_id']||(string)$child['erp_head_id']!==$scope['head_id'])throw new InvalidArgumentException('Saved children cross the source/Head scope.');
            $fields[$child['source_field']]=true;
        }
        if(count($fields)>1)throw new InvalidArgumentException('Saved siblings use inconsistent fields; manual review is required.');
        $fingerprint=hash('sha256',serialize([ExplorerFilterBuilder::fingerprint($scope),array_map(static fn($r)=>[$r['id'],$r['parent_filter_id'],$r['source_field'],$r['source_value'],$r['record_version']],array_merge($path,$children))]));
        return $scope+['parent_id'=>$parent,'path'=>$path,'children'=>$children,'child_field'=>array_key_first($fields),'fingerprint'=>$fingerprint];
    }

    private static function request(array $context,string $operation,?string $field): array
    {
        $nodes=[];foreach($context['path']as$row)$nodes[]=['id'=>(string)$row['id'],'parent_id'=>$row['parent_filter_id']===null?null:(string)$row['parent_filter_id'],'project'=>$row['project_slug'],'source_id'=>(string)$row['erp_source_id'],'head_id'=>(string)$row['erp_head_id'],'field'=>$row['source_field'],'value'=>$row['source_value']];
        return ['operation'=>$operation,'project'=>$context['project'],'source'=>$context['source']['code'],'source_id'=>$context['source_id'],'head'=>$context['head_id'],'subheads'=>$context['subheads'],'parent_id'=>$context['parent_id'],'ancestors'=>$nodes,'field'=>$field,'context'=>$context['fingerprint']];
    }
    private static function response(array $context,array $data): void
    {
        foreach(['source'=>$context['source']['code'],'project'=>$context['project'],'head'=>$context['head_id'],'parent_id'=>$context['parent_id'],'context'=>$context['fingerprint'],'api_version'=>'v1','complete'=>true]as$key=>$value)if(($data[$key]??null)!==$value)throw new RuntimeException('ERP returned mismatched child metadata context.');
    }
    public function catalog(array $context,callable $transport): array
    {
        $data=$transport($context['source'],self::request($context,'fields',null));self::response($context,$data);
        if(!is_array($data['fields']??null)||!array_is_list($data['fields'])||count($data['fields'])>64)throw new RuntimeException('Invalid ERP field catalog.');
        $out=[];
        foreach($data['fields']as$row){
            if(!is_array($row))throw new RuntimeException('Invalid catalog field.');$field=self::field($row['key']??null);
            if(isset($out[$field])||!is_string($row['label']??null)||PropertyExplorer::value($row['label'])===null||!in_array($row['data_type']??null,['enum','varchar','char','tinyint','smallint','mediumint','int','bigint'],true)||($row['discovery_available']??null)!==true||($row['option_fetch_available']??null)!==true)throw new RuntimeException('Invalid ERP field capabilities.');
            $out[$field]=array_replace($row,['readiness'=>self::readiness($field)]);
        }
        if(!is_array($data['unavailable']??[])||count($data['unavailable']??[])>64)throw new RuntimeException('ERP catalog limitation response exceeds its budget.');
        $unavailable=[];foreach(($data['unavailable']??[])as$row)if(is_array($row)&&is_string($row['key']??null)&&is_string($row['reason']??null))$unavailable[]=['key'=>mb_substr($row['key'],0,64),'reason'=>mb_substr($row['reason'],0,255)];
        return ['fields'=>$out,'unavailable'=>$unavailable];
    }
    public function fetch(array $context,string $field,array $catalog,callable $transport): array
    {
        self::field($field);
        if(!isset($catalog['fields'][$field]))throw new InvalidArgumentException('Discover the current safe field catalog before fetching this field.');
        if($context['child_field']!==null&&$context['child_field']!==$field)throw new InvalidArgumentException('Existing siblings pin this parent to another field. The field was not switched.');
        $data=$transport($context['source'],self::request($context,'values',$field));self::response($context,$data);
        if(($data['source_field']??null)!==$field||!is_array($data['options']??null)||!array_is_list($data['options'])||count($data['options'])>128)throw new RuntimeException('Invalid child option response.');
        $out=[];
        foreach($data['options']as$row){
            if(!is_array($row)||($value=PropertyExplorer::value($row['value']??null))===null||!is_string($row['label']??null)||PropertyExplorer::value($row['label'])===null)throw new RuntimeException('Child identity is invalid or cannot be stored exactly.');
            if(isset($context['path'][0])&&$field==='item_sublocation.id'&&$context['field']==='item_sublocation.id'&&!in_array($value,$context['subheads'],true))throw new RuntimeException('ERP returned an unmapped Sub Location.');
            $key=self::key($context,$field,$value);if(isset($out[$key]))throw new RuntimeException('ERP returned duplicate identities.');
            $out[$key]=['key'=>$key,'value'=>$value,'source_label'=>$row['label']];
        }
        return $out;
    }
    public function rows(array $context,array $draft): array
    {
        $field=$draft['field']??$context['child_field'];$out=[];
        foreach(($draft['options']??[])as$key=>$row)$out[$key]=$row+['display_name'=>mb_substr(trim($row['source_label']),0,120),'icon'=>'fa-layer-group','is_visible'=>0,'display_order'=>0,'record_version'=>0,'saved'=>false,'available'=>true,'editable'=>true];
        foreach($context['children']as$row){$key=self::key($context,$row['source_field'],$row['source_value']);$out[$key]=$row+['key'=>$key,'value'=>$row['source_value'],'saved'=>true,'available'=>isset($draft['options'][$key]),'editable'=>$field===$row['source_field']];}
        uasort($out,static fn($a,$b)=>[(int)$a['display_order'],$a['display_name'],$a['key']]<=>[(int)$b['display_order'],$b['display_name'],$b['key']]);return$out;
    }

    public function save(string $slug,string $parent,array $input,array $draft,int $actor): int
    {
        if(!array_is_list($input)||!$input||count($input)>128)throw new InvalidArgumentException('Select between 1 and 128 children.');
        if(!$this->db->transBegin())throw new RuntimeException('Settings transaction unavailable.');
        try{
            if(!$this->db->query('SELECT slug FROM portal_projects WHERE slug=? FOR UPDATE',[$slug])->getRowArray())throw new InvalidArgumentException('Project not found.');
            $context=$this->context($slug,$parent,true);
            if(($draft['scope']??null)!==$context['fingerprint']||($draft['parent']??null)!==$parent)throw new InvalidArgumentException('Ancestor, sibling or mapping changed. Reload before saving.');
            $field=self::field($draft['field']??null);
            if($context['child_field']!==null&&$context['child_field']!==$field)throw new InvalidArgumentException('All siblings must keep the same field.');
            $existing=[];foreach($context['children']as$row)$existing[self::key($context,$row['source_field'],$row['source_value'])]=$row;
            $seen=[];
            foreach($input as$item){
                if(!is_array($item))throw new InvalidArgumentException('Invalid child selection.');
                $key=$item['key']??null;if(!is_string($key)||isset($seen[$key]))throw new InvalidArgumentException('Invalid or duplicate selected child.');$seen[$key]=true;
                $saved=$existing[$key]??null;$fetched=$draft['options'][$key]??null;$value=$saved['source_value']??$fetched['value']??null;
                if(!is_string($value)||PropertyExplorer::value($value)===null||self::key($context,$field,$value)!==$key||(!$saved&&!isset($draft['catalog']['fields'][$field])))throw new InvalidArgumentException('Child was not fetched for this exact branch.');
                if(!is_string($version=$item['version']??null)||!ctype_digit($version)||(int)$version!==(int)($saved['record_version']??0))throw new InvalidArgumentException('Child version changed. Reload before saving.');
                $validated=PropertyExplorer::patch([],['key'=>$key],['version'=>'0','label'=>$item['label']??null,'icon'=>$item['icon']??null,'visible'=>$item['visible']??null,'order'=>$item['order']??null]);$v=$validated['_property_explorer']['options'][$key];
                $values=['display_name'=>$v['label'],'icon'=>$v['icon'],'is_visible'=>$v['visible']?1:0,'display_order'=>$v['order'],'record_version'=>(int)$version+1];
                if($fetched){if($fetched['value']!==$value)throw new RuntimeException('Exact child identity mismatch.');$values['source_label']=$fetched['source_label'];$values['last_seen_at']=$draft['fetched_at'];}
                if($saved){
                    if(!$this->db->table('portal_explorer_filters')->where('id',$saved['id'])->where('record_version',(int)$version)->update($values)||$this->db->affectedRows()!==1)throw new RuntimeException('Child save conflict.');$id=$saved['id'];
                }else{
                    $values+=['project_slug'=>$slug,'erp_source_id'=>$context['source_id'],'erp_head_id'=>$context['head_id'],'parent_filter_id'=>$parent,'source_field'=>$field,'source_value'=>$value];
                    if(!$this->db->table('portal_explorer_filters')->insert($values))throw new RuntimeException('Duplicate child or storage failure.');$id=$this->db->insertID();
                }
                $audit=static fn($r)=>array_intersect_key($r,array_flip(['project_slug','erp_source_id','erp_head_id','parent_filter_id','source_field','source_value','display_name','icon','is_visible','display_order','record_version']));
                (new AdminAuditService($this->db))->record('explorer.child_saved','projects',$actor,'portal_explorer_filter',$id,$saved?$audit($saved):null,$audit($values+($saved??[])));
            }
            if(!$this->db->transStatus()||!$this->db->transCommit())throw new RuntimeException('Child/audit transaction failed.');return count($input);
        }catch(\Throwable$error){$this->db->transRollback();throw$error;}
    }
}
