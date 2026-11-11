<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ErpSourceModel;
use App\Services\ErpConnectionService;
use DomainException;
use InvalidArgumentException;
use Throwable;

class ErpSources extends BaseController
{
    protected $helpers=['url','form','admin'];

    protected function model(): ErpSourceModel { return new ErpSourceModel(); }
    protected function connections(?ErpSourceModel $model=null): ErpConnectionService
    {
        return new ErpConnectionService($model??$this->model(),config('ErpConnections'));
    }

    public function index(): string
    {
        if(!can('erp_sources.view'))throw new \RuntimeException('ERP view permission is required.');
        $references=[];
        foreach (can('erp_sources.manage_credentials')?config('ErpConnections')->credentialRefs:[] as $id=>$definition) {
            if (is_string($id)&&is_array($definition)) $references[]=['id'=>$id,'type'=>$definition['type']??''];
        }
        return view('admin/erp/sources',[
            'pageTitle'=>'ERP Sources','tab'=>'sources','credentialReferences'=>$references,
            'bootstrapData'=>$this->listingPayload(),
        ]);
    }

    protected function json(array $payload,int $status=200)
    {
        return $this->response->setStatusCode($status)->setJSON($payload+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }

    protected function serialise(array $row,ErpSourceModel $model,ErpConnectionService $service): array
    {
        $row['id']=(int)$row['id'];$row['is_active']=(bool)$row['is_active'];
        $configuration=$service->configuration($row);
        $row['connection_status']=$configuration['configured']?$row['connection_status']:'not_configured';
        $row+=$configuration;
        $row['reference_count']=$model->referenceCount($row['id']);
        $row['can_change_code']=$row['reference_count']===0;
        foreach (['last_checked_at','last_sync_at','created_at','updated_at'] as $field) {
            if ($row[$field]) $row[$field]=(new \DateTimeImmutable($row[$field],new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM);
        }
        return $row;
    }

    protected function listingPayload(): array
    {
        $model=$this->model();$service=$this->connections($model);
        $rows=array_map(fn($row)=>$this->serialise($row,$model,$service),$model->orderBy('name')->findAll());
        return ['sources'=>$rows,'summary'=>[
            'total'=>count($rows),'active'=>count(array_filter($rows,fn($row)=>$row['is_active'])),
            'connected'=>count(array_filter($rows,fn($row)=>$row['is_active']&&$row['connection_status']==='connected')),
        ]];
    }

    public function list() { if(!can('erp_sources.view'))return admin_denied();return $this->json($this->listingPayload()); }
    public function options() { if(!can('erp_sources.view'))return admin_denied();return $this->json(['sources'=>$this->model()->activeOptions()]); }
    public function show(int $id)
    {
        if(!can('erp_sources.view'))return admin_denied();
        $model=$this->model();$row=$model->find($id);
        return $row?$this->json(['source'=>$this->serialise($row,$model,$this->connections($model))]):$this->json(['message'=>'ERP source not found.'],404);
    }

    protected function input(): array
    {
        try {$input=str_contains(strtolower($this->request->getHeaderLine('Content-Type')),'application/json')?$this->request->getJSON(true):$this->request->getPost();}
        catch (Throwable $error) { return []; }
        return is_array($input)?$input:[];
    }

    /** Only configuration fields are accepted. Statuses and timestamps are server-managed. */
    protected function configurationInput(array $input): array
    {
        $errors=[];$result=[];
        foreach (['name','code','base_url','auth_type','credential_ref'] as $field) {
            if (isset($input[$field])&&!is_string($input[$field])) $errors[$field]='Enter a text value.';
            $result[$field]=is_string($input[$field]??null)?trim($input[$field]):'';
        }
        $result['code']=strtoupper($result['code']);
        $result['auth_type']=$result['auth_type']?:'none';
        $active=$input['is_active']??1;
        if (!in_array($active,[true,false,0,1,'0','1'],true)) $errors['is_active']='Select an active or inactive status.';
        $result['is_active']=in_array($active,[true,1,'1'],true)?1:0;
        try {$result['base_url']=ErpConnectionService::normaliseBaseUrl($result['base_url']);}
        catch (InvalidArgumentException $error) {$errors['base_url']=$error->getMessage();}
        $result['credential_ref']=$result['credential_ref']?:null;
        if ($result['auth_type']==='none') $result['credential_ref']=null;
        else {
            $reference=config('ErpConnections')->credentialRefs[$result['credential_ref']??'']??null;
            if (!is_array($reference)||($reference['type']??'')!==$result['auth_type']) $errors['credential_ref']='Select a matching credential reference configured on the server. Never enter a password or token here.';
        }
        return [$result,$errors];
    }

    public function create() { return $this->saveConfiguration(null); }
    public function update(int $id) { return $this->saveConfiguration($id); }

    protected function saveConfiguration(?int $id)
    {
        if(!can($id===null?'erp_sources.create':'erp_sources.edit'))return admin_denied();
        [$data,$errors]=$this->configurationInput($this->input());
        if ($errors) return $this->json(['message'=>'Please check the highlighted fields.','errors'=>$errors],422);
        $model=$this->model();$service=$this->connections($model);$db=db_connect();$db->transBegin();
        try {
            $current=null;
            if ($id!==null) {
                $current=$db->query('SELECT * FROM '.$db->escapeIdentifiers($db->prefixTable('erp_sources')).' WHERE id = ? FOR UPDATE',[$id])->getRowArray();
                if (!$current) {$db->transRollback();return $this->json(['message'=>'ERP source not found.'],404);}
                if ($data['code']!==$current['code']&&$model->referenceCount($id)>0) {
                    $db->transRollback();return $this->json(['message'=>'This ERP code is referenced by other records and cannot be changed.','errors'=>['code'=>'Referenced ERP codes are locked. Deactivate the source if it is no longer used.']],409);
                }
            }
            $configurationChanged=$current===null;
            if($current&&(int)$current['is_active']!==$data['is_active']&&!can('erp_sources.deactivate')){$db->transRollback();return admin_denied();}
            if(!can('erp_sources.manage_credentials')) {
                $credentialsChanged=$current===null?($data['auth_type']!=='none'||$data['credential_ref']!==null):($current['auth_type']!==$data['auth_type']||$current['credential_ref']!==$data['credential_ref']);
                if($current&&$current['auth_type']!=='none'&&$current['base_url']!==$data['base_url'])$credentialsChanged=true;
                if($credentialsChanged){$db->transRollback();return admin_denied();}
            }
            foreach (['code','base_url','auth_type','credential_ref'] as $field) if ($current!==null&&$current[$field]!==$data[$field]) $configurationChanged=true;
            if ($configurationChanged) {
                $data['connection_status']=$service->configuration($data)['configured']?'not_checked':'not_configured';
                $data['last_checked_at']=null;
            }
            $saved=$id===null?$model->insert($data):$model->update($id,$data+['id'=>$id]);
            if ($saved===false) {
                $validationErrors=$model->errors();$databaseError=$db->error();
                $db->transRollback();
                if ((int)($databaseError['code']??0)===1062) return $this->json(['message'=>'This ERP source code is already in use.','errors'=>['code'=>'Choose a unique ERP source code.']],409);
                if ($validationErrors) return $this->json(['message'=>'Please check the highlighted fields.','errors'=>$validationErrors],422);
                log_message('error','ERP source write was rejected by the database.');
                return $this->json(['message'=>'The ERP source could not be saved. Please try again.'],500);
            }
            if ($db->transStatus()===false) throw new \RuntimeException('ERP source transaction failed.');
            $id=$id??(int)$model->getInsertID();
            (new \App\Services\AdminAuditService($db))->record($current?'erp_source.updated':'erp_source.created','erp_sources',(int)admin_user()['id'],'erp_source',$id,$current,$data);
            if($current&&($current['auth_type']!==$data['auth_type']||$current['credential_ref']!==$data['credential_ref']))(new \App\Services\AdminAuditService($db))->record('erp_source.credentials_changed','erp_sources',(int)admin_user()['id'],'erp_source',$id);
            $db->transCommit();
            return $this->json(['message'=>$current?'ERP source updated.':'ERP source created.','source'=>$this->serialise($model->find($id),$model,$service)],$current?200:201);
        } catch (DomainException $error) {
            $db->transRollback();return $this->json(['message'=>$error->getMessage()],409);
        } catch (Throwable $error) {
            $details=$db->error();$db->transRollback();
            if ((int)($details['code']??0)===1062 || (int)$error->getCode()===1062) return $this->json(['message'=>'This ERP source code is already in use.','errors'=>['code'=>'Choose a unique ERP source code.']],409);
            log_message('error','ERP source save failed ({class}).',['class'=>$error::class]);
            return $this->json(['message'=>'The ERP source could not be saved. Please try again.'],500);
        }
    }

    public function status(int $id)
    {
        if(!can('erp_sources.deactivate'))return admin_denied();
        $input=$this->input();
        if (!array_key_exists('is_active',$input)||!in_array($input['is_active'],[true,false,0,1,'0','1'],true)) return $this->json(['message'=>'Choose an active or inactive status.'],422);
        $model=$this->model();
        if (!$model->find($id)) return $this->json(['message'=>'ERP source not found.'],404);
        $db=$model->databaseConnection();$db->transBegin();
        try {
            $active=in_array($input['is_active'],[true,1,'1'],true)?1:0;
            if(!$model->update($id,['is_active'=>$active]))throw new \RuntimeException('Status write failed.');
            (new \App\Services\AdminAuditService($db))->record('erp_source.status_changed','erp_sources',(int)admin_user()['id'],'erp_source',$id,null,['is_active'=>$active]);
            if(!$db->transStatus())throw new \RuntimeException('Status transaction failed.');$db->transCommit();
        }catch(\Throwable $e){$db->transRollback();return $this->json(['message'=>'ERP status could not be saved.'],500);}
        return $this->json(['message'=>'ERP source status updated.','source'=>$this->serialise($model->find($id),$model,$this->connections($model))]);
    }

    public function test(int $id)
    {
        if(!can('erp_sources.test_connection'))return admin_denied();
        try {
            $model=$this->model();$service=$this->connections($model);$result=$service->test($id);$row=$model->find($id);
            return $this->json(['message'=>$result['message'],'source'=>$row?$this->serialise($row,$model,$service):null],$result['http_status']);
        } catch (Throwable $error) {
            log_message('error','ERP health result could not be saved ({class}).',['class'=>$error::class]);
            return $this->json(['message'=>'The connection check could not be saved. Please try again.'],500);
        }
    }
}
