<?php
namespace App\Controllers\Admin;

use App\Services\AgencyDealerRun;

final class AgencyDealerBatches extends \App\Controllers\BaseController
{
    protected $helpers=['admin','form'];
    private const KEY='agency_dealer_global_run';

    private function links(int $limit): array
    {
        return db_connect()->query("SELECT l.id,l.portal_agency_id,l.erp_source_id,l.erp_agency_id,l.erp_agency_name,s.code AS source_code
            FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id JOIN authorized_agencies a ON a.id=l.portal_agency_id
            WHERE s.code IN ('SGC','ZALMI') AND s.is_active=1 AND a.portal_active=1 AND a.agency_type='external'
            AND a.is_shalimar_agency=0 AND l.present=1 AND l.mapping_status='mapped' AND l.erp_authorize='yes' AND l.erp_locked='no'
            ORDER BY l.id LIMIT ".($limit+1))->getResultArray();
    }

    public function action(string $action)
    {
        if (!can('erp_sync.run') || !can('agencies.edit') || !can('agencies.view')) return admin_denied();
        // CI's session lock is intentionally held for this one-link request. It
        // serializes double clicks/tabs; the shared sync lease guards other admins.
        $session=session(); $actor=(int)admin_user()['id']; $run=$session->get(self::KEY);
        try {
            if ($action==='status' && (!$run || $run['actor']!==$actor || $run['expires']<time())) return $this->reply(['run'=>null]);
            if ($action==='start') {
                if ($run && $run['actor']===$actor && $run['expires']>time() && !AgencyDealerRun::progress($run)['done']) {
                    throw new \DomainException('Resume the existing run before starting another.');
                }
                $maximum=max(1,min(10000,config('DealerBatches')->maximumLinks));
                $links=$this->links($maximum);
                if (count($links)>$maximum) throw new \DomainException('Mapped links exceed the configured run limit.');
                $run=AgencyDealerRun::begin($links,$actor);
                $run['expires']=time()+max(300,min(7200,config('DealerBatches')->checkpointLifetime));
            } else {
                if (!$run || $run['actor']!==$actor || $run['expires']<time()) throw new \DomainException('Run expired. Start a new run.');
                if ($action!=='status' && !hash_equals($run['token'],(string)$this->request->getPost('token'))) throw new \DomainException('The run checkpoint changed. Reload progress.');
                if ($action==='retry') {
                    if (!AgencyDealerRun::progress($run)['done']) throw new \DomainException('Finish the current pass before retrying.');
                    $run['retry']=array_map('intval',array_keys(array_filter($run['results'],static fn($r)=>$r['status']!=='success')));
                    $run['retry_mode']=true;
                } elseif ($action==='step') {
                    $limit=(int)ini_get('max_execution_time');
                    if ($limit>0 && $limit<30) throw new \DomainException('Dealer sync requires a PHP request limit of at least 30 seconds.');
                    AgencyDealerRun::advance($run,function(array $link) use($actor):array {
                        $db=db_connect();
                        $current=$db->table('agency_erp_links')->where('id',$link['id'])->get()->getRowArray();
                        foreach (['portal_agency_id','erp_source_id','erp_agency_id'] as $key) {
                            if (!$current || (string)$current[$key] !== (string)$link[$key]) return ['status'=>'skipped','message'=>'Mapping changed; start a new run to include the new mapping.'];
                        }
                        try {
                            $result=service('agencyDealerSync')->synchronize((int)$link['portal_agency_id'],(int)$link['id'],$actor,$link);
                            return ['status'=>'success','count'=>$result['count'],'message'=>'Synchronized'];
                        } catch (\Throwable $e) {
                            $busy=$e->getMessage()==='A dealer sync is already running for this ERP agency.';
                            return ['status'=>$busy?'skipped':'failed','message'=>$busy?'Busy: another sync owns this link.':'Dealer sync failed or mapping is no longer eligible. Existing records retained; check source sync status/log.'];
                        }
                    });
                }
            }
            if ($action!=='status') $run['token']=bin2hex(random_bytes(24));
            $session->set(self::KEY,$run);
            return $this->reply(['run'=>AgencyDealerRun::progress($run)]);
        } catch (\DomainException $e) { return $this->reply(['message'=>$e->getMessage()],409); }
    }

    private function reply(array $body,int $status=200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store, private')->setJSON($body+['csrf'=>['name'=>csrf_token(),'hash'=>csrf_hash()]]);
    }
}
