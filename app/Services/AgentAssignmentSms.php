<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Libraries\CustomerIdentity;

/** Approval-scoped delivery log. Only a committed approval may send an assignment SMS. */
final class AgentAssignmentSms
{
    public function __construct(private BaseConnection $db,private ?BusinessSmsGateway $sms=null){}
    private static function text($value,int $limit):string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x20\x7f]+/u',' ',is_string($value)?$value:'')??''),0,$limit);
    }
    /** Called inside the same transaction as the actual approval. */
    public function queue(array $listing):void
    {
        if($listing['selling_method']!=='shalimar_agent')return;
        if($listing['status']!=='live'||$listing['review_state']!=='approved')throw new \LogicException('Only approved listings can queue an assignment SMS.');
        $payload=json_decode($listing['payload'],true,128,JSON_THROW_ON_ERROR);
        $project=$this->db->table('portal_projects')->select('name')->where('slug',$listing['project_slug'])->get()->getRowArray();
        $plot=self::text($payload['plot_no']??'',70)?:'reference '.(string)$listing['erp_property_id'];
        $name=self::text($project['name']??'',100)?:self::text($listing['project_slug'],100);
        $purpose=$listing['listing_purpose']==='rent'?'rent':'sale';
        $message='Shalimar Properties: Property '.$plot.', project '.$name.', has been assigned to you. Listing SP-'.$listing['id'].' is approved for '.$purpose.'.';
        $now=(new ShalimarAgents($this->db))->now();
        if(!$this->db->table('listing_assignment_sms')->insert(['listing_id'=>$listing['id'],'listing_version'=>$listing['record_version'],
            'portal_agent_id'=>$listing['portal_agent_id'],'agent_erp_link_id'=>$listing['agent_erp_link_id'],'agent_source_id'=>$listing['agent_source_id'],
            'message'=>$message,'status'=>'pending','attempts'=>0,'created_at'=>$now,'updated_at'=>$now]))throw new \RuntimeException('Assignment SMS could not be queued.');
    }
    public function statusFor(array $listing):?array
    {
        if(!$this->db->tableExists('listing_assignment_sms'))return null;
        return $this->db->table('listing_assignment_sms')->select('id,listing_version,status,attempts,test_mode,recipient_mask,last_error,attempted_at,sent_at')
            ->where('listing_id',$listing['id'])->where('listing_version',$listing['record_version'])->get()->getRowArray();
    }
    /** After commit only. A failed/uncertain attempt is never blindly resent by repeat approval. */
    public function dispatch(array $listing,bool $retry=false,?int $expectedAttempts=null):?array
    {
        $job=$this->statusFor($listing);if(!$job)return null;
        if((int)$this->db->transDepth>0)return $job;
        $lock='agent_assignment_sms_'.substr(hash('sha256',$this->db->getDatabase().':'.$job['id']),0,40);
        if((int)$this->db->query('SELECT GET_LOCK(?,0) AS held',[$lock])->getRow()->held!==1)return $job;
        try{
            $job=$this->db->table('listing_assignment_sms')->where('id',$job['id'])->get()->getRowArray();
            if(!in_array($job['status'],$retry?['pending','failed']:['pending'],true)||($retry&&($expectedAttempts===null||$expectedAttempts!==(int)$job['attempts'])))return $this->statusFor($listing);
            $current=$this->db->table('portal_listings')->where('id',$job['listing_id'])->get()->getRowArray();
            if(!$current||$current['status']!=='live'||$current['review_state']!=='approved'||!in_array($current['deal'],['available','active'],true)
                ||$current['selling_method']!=='shalimar_agent'||(int)$current['record_version']!==(int)$job['listing_version']
                ||(string)$current['portal_agent_id']!==(string)$job['portal_agent_id']||(string)$current['agent_erp_link_id']!==(string)$job['agent_erp_link_id']||(string)$current['agent_source_id']!==(string)$job['agent_source_id']){
                $this->db->table('listing_assignment_sms')->where('id',$job['id'])->update(['status'=>'skipped','last_error'=>'listing_assignment_changed','updated_at'=>(new ShalimarAgents($this->db))->now()]);return $this->statusFor($listing);
            }
            $now=(new ShalimarAgents($this->db))->now();
            if(!$this->db->table('listing_assignment_sms')->where('id',$job['id'])->where('status',$job['status'])->where('attempts',$job['attempts'])->update(['status'=>'sending','attempts'=>(int)$job['attempts']+1,'attempted_at'=>$now,'updated_at'=>$now,'last_error'=>null])||$this->db->affectedRows()!==1)throw new \RuntimeException('SMS delivery attempt could not be claimed.');
            $accepted=false;
            try{
                $agent=(new ShalimarAgents($this->db))->requireEligible((int)$current['erp_source_id'],(int)$job['portal_agent_id'],false,(int)$job['agent_erp_link_id']);
                if((int)$agent['agent_erp_link_id']!==(int)$job['agent_erp_link_id']||(int)$agent['agent_source_id']!==(int)$job['agent_source_id'])throw new \RuntimeException('Agent assignment changed.');
                $mobile=CustomerIdentity::mobile($agent['mobile']);$settings=config('Sms');
                $recipient=$settings->testMode?CustomerIdentity::mobile($settings->testRecipient):$mobile;
                $this->db->table('listing_assignment_sms')->where('id',$job['id'])->update(['test_mode'=>(int)$settings->testMode,'recipient_mask'=>CustomerIdentity::mask($recipient)]);
                ($this->sms??new JazzCustomerSms($settings))->sendMessage($mobile,$job['message']);
                $accepted=true;
                if(!$this->db->table('listing_assignment_sms')->where('id',$job['id'])->update(['status'=>'sent','sent_at'=>(new ShalimarAgents($this->db))->now(),'updated_at'=>(new ShalimarAgents($this->db))->now()]))throw new \RuntimeException('SMS result could not be recorded.');
            }catch(\Throwable $e){
                log_message('error','Agent assignment SMS failed: listing={listing} notification={notification} type={type}.',['listing'=>$listing['id'],'notification'=>$job['id'],'type'=>get_class($e)]);
                $this->db->table('listing_assignment_sms')->where('id',$job['id'])->update(['status'=>$accepted?'uncertain':'failed','last_error'=>$accepted?'delivery_result_storage_failed':'delivery_not_confirmed','updated_at'=>(new ShalimarAgents($this->db))->now()]);
            }
            return $this->statusFor($listing);
        }finally{$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
