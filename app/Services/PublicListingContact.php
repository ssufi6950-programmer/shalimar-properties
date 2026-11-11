<?php
namespace App\Services;

use App\Libraries\CustomerIdentity;
use PDO;

/** Read-only public contacts and linked seller display name. Never reads identity secrets, ERP mobile or OTP tables. */
final class PublicListingContact
{
    private array $prepared=[];
    private array $agents=[];
    private array $agencies=[];
    public function __construct(private PDO $pdo) {}

    /** Batch the exact eligible identities for cards instead of issuing queries per card. */
    public function prime(array $ids):void
    {
        $ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids)return;
        foreach(array_chunk($ids,500)as$chunk){
            $sql='SELECT l.*,s.code AS property_source_code,seller.display_name AS owner_name'.PublicListingSearch::fromSql();
            $sql=preg_replace('/ WHERE /',' LEFT JOIN portal_customers seller ON seller.id=l.portal_customer_id WHERE ',$sql,1);
            $query=$this->pdo->prepare($sql.' AND l.id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');$query->execute($chunk);
            $rows=$query->fetchAll(PDO::FETCH_ASSOC);$agentIds=[];$sources=[];
            foreach($chunk as$id)$this->prepared[$id]=null;
            foreach($rows as$row){$this->prepared[(int)$row['id']]=$row;if($row['selling_method']==='shalimar_agent')$agentIds[]=(int)$row['agent_erp_link_id'];if($row['selling_method']==='authorized_agency')$sources[(int)$row['erp_source_id']][]=(int)$row['agency_erp_link_id'];}
            if($agentIds){$query=$this->pdo->prepare(ShalimarAgents::eligibleSql().' AND l.id IN ('.implode(',',array_fill(0,count($agentIds),'?')).')');$query->execute($agentIds);foreach($query->fetchAll(PDO::FETCH_ASSOC)as$agent)$this->agents[(int)$agent['agent_erp_link_id']]=$agent;}
            foreach($sources as$source=>$links){$query=$this->pdo->prepare(AgencyDirectory::eligibleSql().' AND l.id IN ('.implode(',',array_fill(0,count($links),'?')).')');$query->execute(array_merge([$source],$links));foreach($query->fetchAll(PDO::FETCH_ASSOC)as$agency)$this->agencies[$source.':'.$agency['agency_erp_link_id']]=$agency;}
        }
    }

    public static function phone($value, bool $mobileOnly = false): ?string
    {
        if (!is_string($value) || strlen($value)>100) return null;
        if ($mobileOnly) {
            try { return CustomerIdentity::mobile($value); } catch (\InvalidArgumentException $e) { return null; }
        }
        $value=trim($value);
        if (!preg_match('/\A\+?[0-9 ()-]+\z/D',$value)) return null;
        $digits=preg_replace('/[^0-9]/','',$value);
        if (preg_match('/\A0[1-9][0-9]{8,10}\z/D',$digits)) return '92'.substr($digits,1);
        if (preg_match('/\A92[1-9][0-9]{7,11}\z/D',$digits)) return $digits;
        if (str_starts_with($value,'+') && preg_match('/\A[1-9][0-9]{7,14}\z/D',$digits)) return $digits;
        return null;
    }

    public function resolve(int $id): array
    {
        $empty=['available'=>false,'identity_available'=>false,'owner_name'=>'','phone'=>'','tel'=>'','whatsapp'=>'','email'=>'','mailto'=>'','name'=>'Seller unavailable','method'=>'','badge'=>'','subtitle'=>'','target'=>'Seller','description'=>'','notice'=>'Public contact details are currently unavailable.'];
        if(!array_key_exists($id,$this->prepared))$this->prime([$id]);
        $listing=$this->prepared[$id];
        if (!$listing) return $empty;
        $empty['method']=in_array($listing['selling_method'],['self_sell','shalimar_agent','authorized_agency'],true)?$listing['selling_method']:'';
        $phone=null;$email=null;$name='';$notice='Business contact details.';$identity=false;
        switch ($listing['selling_method']) {
            case 'self_sell':
                // Explicit listing public contact only. No customer-profile/booking fallback.
                $payload=json_decode($listing['payload'],true);$poster=is_array($payload['poster']??null)?$payload['poster']:[];
                $phone=self::phone($poster['phone']??null,true);$email=($poster['email_public']??null)===true?PublicContactEmail::normalize($poster['email']??null):null;$name='Property Owner';$identity=true;
                $notice='Public contact supplied by the seller; this number is not OTP verified.';
                break;
            case 'shalimar_agent':
                $agent=$this->agents[(int)$listing['agent_erp_link_id']]??null;
                if(!in_array($listing['property_source_code'],['SGC','ZALMI'],true))$agent=null;
                if($agent&&((int)$agent['portal_agent_id']!==(int)$listing['portal_agent_id']||(int)$agent['agent_source_id']!==(int)$listing['agent_source_id']))$agent=null;
                if ($agent) {$name=$agent['name'];$identity=true;$phone=self::phone($agent['mobile']);$email=PublicContactEmail::normalize($agent['email']);}
                break;
            case 'authorized_agency':
                // The central directory contact is admin-confirmed; raw ERP contact_json is never public.
                $agency=$this->agencies[$listing['erp_source_id'].':'.$listing['agency_erp_link_id']]??null;
                if($agency&&(int)$agency['portal_agency_id']!==(int)$listing['portal_agency_id'])$agency=null;
                if ($agency) {$phone=self::phone($agency['phone']);$email=PublicContactEmail::normalize($agency['email']);$name=$agency['agency_name'];$identity=true;}
                break;
        }
        if (!$identity) return $empty;
        // Public seller-name display is explicitly requested. Only use this listing's linked customer.
        $empty['owner_name']=is_string($listing['owner_name'])?trim($listing['owner_name']):'';
        [$badge,$subtitle,$target,$description]=[
            'self_sell'=>['Direct Owner','Property Owner','Owner','Contact the property owner directly using the provided contact details.'],
            'shalimar_agent'=>['Official Shalimar Agent','Shalimar Properties · Agent','Agent','Contact the selected Shalimar agent directly.'],
            'authorized_agency'=>['Authorized Agency','Authorized Agency','Agency','Contact the selected authorized agency directly.'],
        ][$empty['method']];
        return array_replace($empty,['available'=>$phone!==null||$email!==null,'identity_available'=>true,'phone'=>$phone!==null?'+'.$phone:'','tel'=>$phone!==null?'tel:+'.$phone:'','whatsapp'=>$phone!==null?'https://wa.me/'.$phone:'','email'=>$email??'','mailto'=>$email!==null?'mailto:'.str_replace('%40','@',rawurlencode($email)):'','name'=>$name,'badge'=>$badge,'subtitle'=>$subtitle,'target'=>$target,'description'=>$description,'notice'=>$phone!==null||$email!==null?$notice:$empty['notice']]);
    }
}
