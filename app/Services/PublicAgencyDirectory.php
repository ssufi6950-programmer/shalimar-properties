<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Public local directory; authorization does not grant customer assignment or login access. */
final class PublicAgencyDirectory
{
    public function __construct(private PDO $pdo){}
    private static function normalized($value):string
    {
        return is_string($value)?mb_strtolower(trim(preg_replace('/\s+/u',' ',$value)??''),'UTF-8'):'';
    }
    public function agencies():array
    {
        $sql="SELECT l.erp_source_id,l.erp_agency_id,l.erp_agency_name,l.mapping_status,l.portal_agency_id,l.contact_json,
            s.code,a.id AS central_id,a.agency_name,a.portal_active,a.phone,a.email,a.city,a.address,a.agency_type,a.is_shalimar_agency,
            sc.erp_source_id AS internal_source_id,sc.erp_agency_id AS internal_agency_id
            FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id
            LEFT JOIN authorized_agencies a ON a.id=l.portal_agency_id AND l.mapping_status='mapped'
            LEFT JOIN shalimar_agent_source_settings sc ON sc.settings_id=1 AND sc.erp_source_id=l.erp_source_id AND sc.erp_agency_id=l.erp_agency_id
            WHERE s.code IN ('SGC','ZALMI') AND s.is_active=1 AND l.present=1 AND BINARY l.erp_authorize=BINARY 'yes'
            ORDER BY l.erp_source_id,l.erp_agency_id";
        $agencies=[];
        foreach($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)as$row){
            if((int)$row['is_shalimar_agency']===1||$row['agency_type']==='internal'
                ||($row['internal_source_id']!==null&&(string)$row['internal_source_id']===(string)$row['erp_source_id']&&(string)$row['internal_agency_id']===(string)$row['erp_agency_id'])
                ||self::normalized($row['agency_name'])==='shalimar properties'||self::normalized($row['erp_agency_name'])==='shalimar properties')continue;
            $mapped=$row['mapping_status']==='mapped'&&$row['portal_agency_id']!==null;
            $key=$mapped?'portal:'.$row['portal_agency_id']:'source:'.$row['erp_source_id'].':'.$row['erp_agency_id'];
            $metadata = json_decode($row['contact_json'] ?? '{}', true);
            $logo = AgencyLogo::cachedUrl($row['code'], is_array($metadata) ? ($metadata['logo'] ?? '') : '');
            if(!isset($agencies[$key])){
                // Publish only confirmed central business contacts, never raw ERP contact_json.
                $business=$row['central_id']!==null&&(int)$row['portal_active']===1;
                $phone=$business?PublicListingContact::phone($row['phone']??''):null;
                $mobile=$business?PublicListingContact::phone($row['phone']??'',true):null;
                $email=$business?PublicContactEmail::normalize($row['email']??''):null;
                $city=trim((string)($row['city']??''));if(ctype_digit($city))$city='';
                $name=trim((string)($row['agency_name']?:$row['erp_agency_name']));
                $agencies[$key]=['key'=>$key,'portal_agency_id'=>$row['central_id']!==null?(int)$row['central_id']:null,
                    'name'=>$name!==''?$name:'Agency','city'=>$city,'address'=>trim((string)($row['address']??'')),
                    'phone'=>$phone!==null?'+'.$phone:'','tel'=>$phone!==null?'tel:+'.$phone:'',
                    'whatsapp'=>$mobile!==null?'https://wa.me/'.$mobile:'','email'=>$email??'',
                    'mailto'=>$email!==null?'mailto:'.str_replace('%40','@',rawurlencode($email)):'','logo'=>$logo,'sources'=>[]];
            }
            // A central agency may have links in both sources; keep one available valid logo.
            if ($agencies[$key]['logo'] === '' && $logo !== '') $agencies[$key]['logo'] = $logo;
            $agencies[$key]['sources'][$row['code']]=$row['code']==='ZALMI'?'Zalmi':'SGC';
        }
        foreach($agencies as&$agency){ksort($agency['sources']);$agency['sources']=array_values($agency['sources']);}unset($agency);
        uasort($agencies,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name'])?:strcmp($a['key'],$b['key']));
        return array_values($agencies);
    }
}
