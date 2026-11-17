<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class DealerProperties
{
    public function __construct(private BaseConnection $db) {}
    private function query(array $user)
    {
        $member=(new AgencyPortalDealers($this->db))->currentQuery((int)$user['portal_agency_id'])->select('1',false)
            ->where('m.id',(int)$user['agency_dealer_membership_id'])->where('m.erp_source_id=pl.erp_source_id',null,false)
            ->where('m.agency_erp_link_id=pl.agency_erp_link_id',null,false)->getCompiledSelect();
        return (new AgencyPortalListings($this->db))->assignmentQuery((int)$user['portal_agency_id'])
            ->join('portal_listing_dealer_assignments x',"x.portal_listing_id=pl.id AND x.portal_agency_id=pl.portal_agency_id AND x.erp_source_id=pl.erp_source_id AND x.agency_erp_link_id=pl.agency_erp_link_id AND x.assignment_status='active'")
            ->join('agency_dealer_portal_accounts da','da.agency_dealer_membership_id=x.agency_dealer_membership_id AND da.portal_agency_id=x.portal_agency_id')
            ->where('da.id',(int)$user['id'])->where('da.auth_version',(int)$user['auth_version'])->where('da.enabled',1)->where('da.revoked_at',null)->where('da.must_change_password',0)
            ->where('x.agency_dealer_membership_id',(int)$user['agency_dealer_membership_id'])
            ->where('EXISTS ('.$member.')',null,false)->select('x.assigned_at');
    }
    public function count(array $user): int {return $this->query($user)->countAllResults();}
    private function present(array $row): array
    {
        $decorated=(new AgencyPortalListings($this->db))->decorate($row);
        return ['id'=>(int)$row['id'],'project'=>$row['project_name'],'purpose'=>$row['listing_purpose'],'price'=>$row['asking_price'],'source'=>$row['source_code'],'assigned_at'=>$row['assigned_at'],'public'=>$decorated['public']];
    }
    public function search(array $user,int $page): array
    {
        $query=$this->query($user);$total=(clone $query)->countAllResults();$pages=max(1,(int)ceil($total/20));$page=min($pages,max(1,min(100000,$page)));
        $rows=array_map(fn($row)=>$this->present($row),$query->orderBy('pl.id','DESC')->limit(20,($page-1)*20)->get()->getResultArray());return compact('rows','total','page','pages');
    }
    public function find(array $user,int $listing): array
    {
        $row=$this->query($user)->where('pl.id',$listing)->get()->getRowArray();if(!$row)throw new DomainException('Assigned property unavailable.');return $this->present($row);
    }
    public function media(array $user,int $listing,int $index): array
    {
        $row=$this->find($user,$listing);$url=$row['public']['images'][$index]['url']??'';
        if(preg_match('~/media/resale/([a-f0-9]{64})\z~D',$url,$m))return (new ResalePhotos($this->db))->publicFile($m[1]);
        if(preg_match('~/uploads/listings/([0-9]{8}-[a-f0-9]{16}\.(?:jpg|png|webp))\z~D',$url,$m)){
            $root=realpath(ROOTPATH.'html/uploads/listings');$path=realpath(ROOTPATH.'html/uploads/listings/'.$m[1]);
            if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path)){$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);if(in_array($mime,['image/jpeg','image/png','image/webp'],true))return ['path'=>$path,'mime'=>$mime];}
        }
        throw new DomainException('Image unavailable.');
    }
}
