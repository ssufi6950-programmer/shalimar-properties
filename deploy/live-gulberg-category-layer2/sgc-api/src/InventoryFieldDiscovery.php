<?php
declare(strict_types=1);
namespace SmpApi;
if (!defined('SMP_API_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Schema-aware category metadata. The browser never supplies executable SQL identifiers. */
final class InventoryFieldDiscovery
{
    public const MAX_VALUES = 128;
    public const SNAPSHOT_COLUMNS = ['block_no','floor_no','sector','tower','apartment_type','aprt_catagory','category','dimension_type','nature','no_of_floor','sale_type','file_no_series'];

    public static function listingSnapshot(array $row, array $schema): ?array
    {
        if ((string)$row['explorer_head'] !== (string)$row['head_id'] || (string)$row['explorer_subhead'] !== (string)$row['subhead_id']) return null;
        $fields=[];
        foreach (self::SNAPSHOT_COLUMNS as $column) {
            if (!isset($schema[$column]) || !self::suitable($column,$schema[$column])) continue;
            $value=$row['explorer_'.$column] ?? null;
            if (!is_string($value) && !is_int($value)) continue;
            try { $value=self::exact($value); } catch (CustomerApiFailure $error) { continue; }
            if (self::safeValue($value)) $fields['pro_inventory.'.$column]=$value;
        }
        return ['version'=>1,'property_id'=>(string)$row['property_id'],'head_id'=>(string)$row['explorer_head'],'subhead_id'=>(string)$row['explorer_subhead'],'fields'=>$fields];
    }
    private $db;
    private array $schema;
    private float $deadline;
    private array $profiles = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->deadline = microtime(true) + 8;
        try {
            $this->schema = $db->columns('pro_inventory');
            foreach (['id','head','sub_head'] as $required) if (!isset($this->schema[$required])) throw new \RuntimeException('Missing scope column.');
        } catch (\Throwable $error) {
            throw new CustomerApiFailure('schema_unavailable', 'Inventory schema inspection is unavailable; no fields were guessed.', 503);
        }
        if (count($this->schema) > 512) throw new CustomerApiFailure('schema_limit', 'Inventory schema exceeds the inspection budget.', 422);
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    public static function suitable(string $column, string $type): bool
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $column) || strlen('pro_inventory.'.$column) > 64) return false;
        $column = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $column));
        if(preg_match('/customer|owner|ledger|firstname|lastname|fullname|surname|password|secret|token|credential|session|cnic|passport|email|mobile|phone|bank|account|address/',$column)||substr($column,-2)==='id')return false;
        if (!in_array(strtolower($type), ['enum','varchar','char','tinyint','smallint','mediumint','int','bigint'], true)) return false;
        // Explicit business-approved grouping field; individual file numbers remain excluded.
        if ($column === 'file_no_series') return true;
        // Server policy, not a field-by-field allowlist: future ordinary categorical columns need no code change.
        if (in_array(strtolower($column), ['phase','street_no','size','size_code','id','head','sub_head','property_no','plot_no','file_no','file_no_series','old_file_no','order_no','suit_or_flat','name'], true)) return false;
        return !preg_match('/(?:^|_)(?:ids?|uuid|guid|ref|code|number|serial|customer|owner|coowners?|ledger|user|company|contact|telephone|whatsapp|resident|name|firstname|lastname|fullname|surname|father|mother|dob|birth|biometric|latitude|longitude|coordinates|phone|mobile|cell|email|address|cnic|nic|idcard|passport|credential|password|pass|pin|secret|token|key|auth|hash|session|bank|account|iban|card|price|rate|amount|balance|payment|cost|charges?|fees?|tax|rent|salary|commission|income|revenue|profit|discount|deposit|demand|budget|security|notes?|narration|description|remarks?|message|sms|image|pics?|photo|video|url|link|path|file|date|time|created|updated|deleted|approved?|verified|locked|status|permission|role|restrict|ristrict|investor|merge|percentage|occupancy|dev)(?:_|$)/i', $column);
    }

    private function read(string $sql, array $values): array
    {
        if (microtime(true) > $this->deadline) throw new CustomerApiFailure('metadata_budget', 'Metadata inspection exceeded its time budget; no partial catalog was accepted.', 503);
        return $this->db->select($sql, $values);
    }

    public static function exact($value): string
    {
        if (!is_string($value) && !is_int($value)) throw new CustomerApiFailure('invalid_identity', 'Source identity must be text or an integer.', 422);
        $value = (string) $value;
        if (trim($value) === '' || strlen($value) > 1020 || preg_match('//u', $value) !== 1 || count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY)) > 255) throw new CustomerApiFailure('unsupported_value', 'A source value is blank, invalid or too long to preserve exactly.', 422);
        return $value;
    }

    private static function safeValue(string $value): bool
    {
        if (preg_match('/[\x00-\x1f\x7f]|@|https?:|www\.|(?:Bearer\s)|eyJ[A-Za-z0-9_-]+\./i', $value)) return false;
        $digits = preg_replace('/[\s()+-]/', '', $value);
        if (preg_match('/\A[0-9]{8,}\z/D', $digits)) return false;
        if (preg_match('/\A(?:[0-9]{1,3}\.){3}[0-9]{1,3}\z/D', $value)) return false;
        if (preg_match('/\A[0-9a-f]{24,}\z/Di', $value) || preg_match('/\A[A-Za-z0-9+\/_=-]{40,}\z/D', $value)) return false;
        // Free-form prose is unsuitable for a compact category filter.
        return count(preg_split('/\s+/', trim($value))) <= 12;
    }

    private function descriptor(string $field): array
    {
        if ($field === 'item_sublocation.id') return ['key'=>$field,'label'=>'Sub Location','data_type'=>'int','identity_type'=>'integer','adapter'=>'sublocation'];
        if (strpos($field, 'pro_inventory.') !== 0) throw new CustomerApiFailure('field_not_allowed','Select a field from the current safe catalog.',400);
        $column = substr($field, 14);
        if (!isset($this->schema[$column]) || !self::suitable($column, $this->schema[$column])) throw new CustomerApiFailure('field_not_allowed','This field is excluded or unavailable in this source schema.',400);
        $label = ['block_no'=>'Block','floor_no'=>'Inventory Floor','tower'=>'Tower'][$column] ?? ucwords(str_replace('_',' ',$column));
        return ['key'=>$field,'label'=>$label,'data_type'=>$this->schema[$column], 'identity_type'=>in_array($this->schema[$column],['enum','varchar','char'],true)?'string':'integer','adapter'=>'scalar'];
    }

    /** All predicates apply to one inventory alias. Ancestors are a flat, validated path. */
    public function branch(array $input, string $source): array
    {
        $project = $input['project'] ?? null;
        if(!is_string($project))throw new CustomerApiFailure('invalid_scope','A portal project is required.',400);
        $root = ['SGC'=>['gulberg-city'=>'pro_inventory.category','shalimar-smart-city'=>'pro_inventory.sector'],'ZALMI'=>['zalmi-x'=>'item_sublocation.id']][$source][$project ?? ''] ?? null;
        if (!$root || ($input['source'] ?? null) !== $source) throw new CustomerApiFailure('invalid_scope','Project and ERP source do not match.',400);
        foreach (['head','source_id','parent_id'] as $id) if (!is_string($input[$id] ?? null) || !preg_match('/\A[0-9]{1,18}\z/D',$input[$id])) throw new CustomerApiFailure('invalid_scope','A valid source, Head and saved parent are required.',400);
        $path = $input['ancestors'] ?? null;
        if (!is_array($path) || !self::isList($path) || !$path) throw new CustomerApiFailure('invalid_path','A complete ancestor path is required.',400);
        $subheads = $input['subheads'] ?? null;
        if (!is_array($subheads) || !self::isList($subheads) || count($subheads)>100) throw new CustomerApiFailure('invalid_scope','Mapped Sub Location scope is invalid.',400);
        foreach ($subheads as $id) if (!is_string($id) || !preg_match('/\A[0-9]{1,18}\z/D',$id)) throw new CustomerApiFailure('invalid_scope','Invalid mapped Sub Location.',400);
        $headTable = ApiEnvironment::get('SMP_API_HEAD_TABLE') ?: 'item_location';
        $subTable = ApiEnvironment::get('SMP_API_SUBHEAD_TABLE') ?: 'item_sublocation';
        $parent = ApiEnvironment::get('SMP_API_PARENT_COLUMN') ?: 'location_id';
        foreach ([$headTable,$subTable,$parent] as $id) if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$id)) throw new CustomerApiFailure('invalid_configuration','Catalog schema configuration is invalid.',503);
        $this->db->requireColumns($subTable,['id','name',$parent]);
        if (!$this->read("SELECT id FROM `$headTable` WHERE id=? LIMIT 1",[$input['head']])) throw new CustomerApiFailure('invalid_scope','Mapped Head is unavailable.',422);
        $where='i.head=?';$params=[$input['head']];
        // Gulberg and Zalmi use project-mapped Sub Locations; Smart City intentionally spans its mapped Head.
        if ($project !== 'shalimar-smart-city') {
            if (!$subheads) throw new CustomerApiFailure('invalid_scope','Project has no mapped Sub Locations.',422);
            $where.=' AND i.sub_head IN ('.implode(',',array_fill(0,count($subheads),'?')).')';
            $params=array_merge($params,$subheads);
        }
        $seen=[];$previous=null;$conditions=[];
        foreach ($path as $node) {
            if (!is_array($node) || !is_string($node['id']??null) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',$node['id']) || isset($seen[$node['id']]) || ($node['parent_id']??null)!==$previous || ($node['project']??null)!==$project || ($node['source_id']??null)!==$input['source_id'] || ($node['head_id']??null)!==$input['head']) throw new CustomerApiFailure('invalid_path','Ancestor path is incomplete, cyclic or crosses a source/project/Head.',400);
            $field=$node['field']??null;
            $legacyGulbergSubhead=$source==='SGC'&&$project==='gulberg-city'&&$previous===null&&$field==='item_sublocation.id';
            if (!is_string($field) || ($previous===null && $field!==$root && !$legacyGulbergSubhead)) throw new CustomerApiFailure('invalid_path','Root filter identity does not match the project.',400);
            $descriptor=$this->descriptor($field);$value=self::exact($node['value']??null);
            if (isset($conditions[$field]) && $conditions[$field]!==$value) throw new CustomerApiFailure('invalid_path','Ancestor conditions contradict each other.',422);
            $conditions[$field]=$value;
            if ($field==='item_sublocation.id') {
                if (!preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D',$value) || ($project!=='shalimar-smart-city' && !in_array($value,$subheads,true))) throw new CustomerApiFailure('invalid_scope','Sub Location is outside the mapped project.',422);
                $where.=" AND i.sub_head=? AND EXISTS (SELECT 1 FROM `$subTable` branch_sub WHERE branch_sub.id=i.sub_head AND branch_sub.`$parent`=i.head)";
            } else {
                $column=substr($field,14);
                if (!self::safeValue($value)) throw new CustomerApiFailure('unsafe_value','Unsafe ancestor value.',422);
                $where.=" AND BINARY CONVERT(i.`$column` USING utf8mb4)=BINARY ?";
            }
            $params[]=$value;$seen[$node['id']]=true;$previous=$node['id'];
        }
        if ($previous!==$input['parent_id']) throw new CustomerApiFailure('invalid_path','Selected parent does not finish the ancestor path.',400);
        if (!$this->read('SELECT i.id FROM pro_inventory i WHERE '.$where.' LIMIT 1',$params)) throw new CustomerApiFailure('empty_branch','No inventory property satisfies the complete ancestor path.',422);
        return ['where'=>$where,'params'=>$params,'subtable'=>$subTable,'parent_column'=>$parent,'project'=>$project,'head'=>$input['head'],'subheads'=>$subheads,'used'=>$conditions];
    }

    public function values(string $field, array $branch): array
    {
        $descriptor=$this->descriptor($field);
        $cache=hash('sha256',serialize([$field,$branch]));if(isset($this->profiles[$cache]))return$this->profiles[$cache];
        $extra='';$params=$branch['params'];$joins='';
        if ($descriptor['adapter']==='sublocation') {
            $table=$branch['subtable'];$parent=$branch['parent_column'];
            $joins=" JOIN `$table` option_sub ON option_sub.id=i.sub_head AND option_sub.`$parent`=i.head";
            $value='CAST(option_sub.id AS CHAR)';$label='option_sub.name';
            if ($branch['project']!=='shalimar-smart-city') {
                if (!$branch['subheads']) throw new CustomerApiFailure('invalid_scope','Project has no mapped Sub Locations.',422);
                $extra=' AND i.sub_head IN ('.implode(',',array_fill(0,count($branch['subheads']),'?')).')';$params=array_merge($params,$branch['subheads']);
            }
        } else {$column=substr($field,14);$value="CONVERT(i.`$column` USING utf8mb4)";$label=$value;}
        $rows=$this->read("SELECT DISTINCT BINARY $value AS value,BINARY $label AS label FROM pro_inventory i$joins WHERE ".$branch['where'].$extra." AND $value IS NOT NULL AND $value REGEXP '[^[:space:]]' LIMIT ".(self::MAX_VALUES+1),$params);
        if (count($rows)>self::MAX_VALUES) throw new CustomerApiFailure('high_cardinality','This field has more than 128 distinct values in this branch and is not suitable for category filtering.',422);
        $out=[];
        foreach ($rows as $row) {
            $v=self::exact($row['value']);$l=self::exact($row['label']);
            if (!self::safeValue($v) || !self::safeValue($l)) throw new CustomerApiFailure('unsafe_value','This column contains unsuitable or potentially sensitive values; none were exposed.',422);
            $out[]=['value'=>$v,'label'=>$l];
        }
        usort($out,static fn($a,$b)=>strcmp($a['value'],$b['value']));
        return $this->profiles[$cache]=$out;
    }

    public function catalog(array $branch): array
    {
        $fields=[];$unavailable=[];$candidates=['item_sublocation.id'];
        foreach($this->schema as$column=>$type)if(self::suitable($column,$type))$candidates[]='pro_inventory.'.$column;
        if(count($candidates)>64)throw new CustomerApiFailure('field_budget','Too many candidate columns for one inspection; adjust the server policy.',422);
        foreach($candidates as$field){
            try{
                $values=$this->values($field,$branch);
                if(!$values){$unavailable[]=['key'=>$field,'reason'=>'No nonblank values in this branch.'];continue;}
                $fields[]=$this->descriptor($field)+['value_count'=>count($values),'discovery_available'=>true,'option_fetch_available'=>true];
            }catch(CustomerApiFailure$error){
                if(!in_array($error->errorCode,['high_cardinality','unsafe_value','unsupported_value'],true))throw$error;
                $unavailable[]=['key'=>$field,'reason'=>$error->getMessage()];
            }
        }
        usort($fields,static fn($a,$b)=>[in_array($a['key'],['item_sublocation.id','pro_inventory.sector','pro_inventory.block_no','pro_inventory.floor_no','pro_inventory.tower'],true)?0:1,$a['label']]<=>[in_array($b['key'],['item_sublocation.id','pro_inventory.sector','pro_inventory.block_no','pro_inventory.floor_no','pro_inventory.tower'],true)?0:1,$b['label']]);
        return ['fields'=>$fields,'unavailable'=>$unavailable];
    }
}
