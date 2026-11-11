<?php
namespace App\Controllers\Admin;
class AuditLogs extends \App\Controllers\BaseController
{
    protected $helpers=['url','form','admin'];
    private function query()
    {
        $q=db_connect()->table('admin_audit_logs a')->select('a.*,u.name AS actor_name')->join('admin_users u','u.id=a.actor_user_id','left');
        foreach(['module','action','actor_user_id']as$field){$v=$this->request->getGet($field);if(is_string($v)&&$v!=='')$q->where('a.'.$field,substr($v,0,100));}
        foreach(['from'=>'>=','to'=>'<=']as$field=>$operator){$v=$this->request->getGet($field);if(is_string($v)&&preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$v))$q->where('a.created_at '.$operator,$v.($field==='from'?' 00:00:00':' 23:59:59'));}
        return $q;
    }
    public function index()
    {
        if(!can('audit_logs.view'))return admin_denied();$q=$this->query();$page=max(1,min(100000,(int)$this->request->getGet('page')));$total=(clone $q)->countAllResults();
        return view('admin/security/audit',['pageTitle'=>'Audit Logs','tab'=>'activity','rows'=>$q->orderBy('a.id','DESC')->limit(25,($page-1)*25)->get()->getResultArray(),'page'=>$page,'total'=>$total]);
    }
    public function export()
    {
        if(!can('audit_logs.export'))return admin_denied();
        (new \App\Services\AdminAuditService())->record('audit.exported','audit_logs',(int)admin_user()['id']);
        $rows=$this->query()->orderBy('a.id','DESC')->limit(10000)->get()->getResultArray();$stream=fopen('php://temp','r+');
        fputcsv($stream,['ID','Time','Actor','Action','Module','Target','Description']);
        foreach($rows as$row){$values=[$row['id'],$row['created_at'],$row['actor_name']??'System',$row['action'],$row['module'],$row['target_id'],$row['description']];foreach($values as &$value){$value=(string)$value;if(preg_match('/\A[=+@\-\t\r]/',$value))$value="'".$value;}unset($value);fputcsv($stream,$values);}
        rewind($stream);$body=stream_get_contents($stream);fclose($stream);
        return $this->response->setContentType('text/csv')->setHeader('Content-Disposition','attachment; filename="admin-audit.csv"')->setBody($body);
    }
}
