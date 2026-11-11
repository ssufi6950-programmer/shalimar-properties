<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\CustomerFlowException;

/** Admin adapter over the same private upload storage. Removals never delete historical files. */
final class AdminListingPhotos
{
    public function __construct(private BaseConnection $db){}
    public function manifest(array $listing):array
    {
        $payload=json_decode($listing['payload'],true,128,JSON_THROW_ON_ERROR);$images=$payload['images']??[];
        if(!is_array($images)||!array_is_list($images))throw new \InvalidArgumentException('The saved photo set is invalid.');
        $out=[];$seen=[];
        foreach($images as$image){
            if(is_string($image)&&preg_match('~\Aassets/images/listing-(?:house|apartment|plot|commercial|office|farmhouse)\.svg\z~D',$image))continue;
            $entry=['reference'=>is_string($image)?$image:'invalid','id'=>null,'valid'=>false];$path=null;$photo=null;
            if(is_string($image)&&preg_match('/\Aresale-photo:([a-f0-9]{64})\z/D',$image,$m)){
                $entry['id']=$m[1];$photo=$this->db->table('resale_photos')->where('id',$m[1])->where('listing_id',$listing['id'])->where('portal_customer_id',$listing['portal_customer_id'])->get()->getRowArray();
                if($photo&&in_array($photo['extension'],['jpg','png','webp'],true))$path=WRITEPATH.'resale-photos/'.$m[1].'.'.$photo['extension'];
            }elseif(is_string($image)&&preg_match('/\A[0-9]{8}-[a-f0-9]{16}\.(?:jpg|png|webp)\z/D',$image)){
                $entry['id']='legacy-'.hash('sha256',$image);$path=ROOTPATH.'html/uploads/listings/'.$image;
            }
            if($path&&is_file($path)){
                $real=realpath($path);$base=realpath($photo?WRITEPATH.'resale-photos':ROOTPATH.'html/uploads/listings');
                if($real&&$base&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)){
                    $entry['checksum']=hash_file('sha256',$real);$entry['bytes']=filesize($real);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($real);$size=@getimagesize($real);
                    $entry['valid']=in_array($mime,['image/jpeg','image/png','image/webp'],true)&&$size&&$size['mime']===$mime&&$size[0]*$size[1]<=config('CustomerPortal')->photoMaximumPixels;
                    if($photo){$entry['stored_checksum']=$photo['checksum'];$entry['valid']=$entry['valid']&&hash_equals($photo['checksum'],$entry['checksum'])&&(int)$photo['bytes']===$entry['bytes']&&$photo['mime']===$mime;}
                }
            }
            if(isset($seen[$entry['reference']]))$entry['valid']=false;$seen[$entry['reference']]=true;$out[]=$entry;
        }
        return$out;
    }
    public function fingerprint(array $listing):string{return hash('sha256',ListingLifecycle::fingerprint($listing).json_encode($this->manifest($listing),JSON_THROW_ON_ERROR));}
    public function requirePublishable(array $listing):void
    {
        $photos=$this->manifest($listing);if(count($photos)>config('CustomerPortal')->photoMaximumCount)throw new \InvalidArgumentException('The final photo set exceeds the photo limit.');
        foreach($photos as$photo)if(!$photo['valid'])throw new \InvalidArgumentException('A reviewed photo is missing, altered or not linked to this listing. Remove or replace it before approval.');
    }
    public function items(array $listing):array
    {
        return array_map(static fn($p)=>['id'=>$p['id'],'valid'=>(bool)$p['valid'],'url'=>$p['id']?site_url('admin/listings/'.$listing['id'].'/photos/'.$p['id']):null],$this->manifest($listing));
    }
    public function file(array $listing,string $id):array
    {
        $match=null;foreach($this->manifest($listing)as$photo)if($photo['id']===$id&&$photo['valid'])$match=$photo;
        if(!$match)throw new CustomerFlowException(404,'Image unavailable.');
        if(str_starts_with($id,'legacy-')){$path=ROOTPATH.'html/uploads/listings/'.$match['reference'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);}
        else{$row=$this->db->table('resale_photos')->where('id',$id)->where('listing_id',$listing['id'])->get()->getRowArray();$path=WRITEPATH.'resale-photos/'.$id.'.'.$row['extension'];$mime=$row['mime'];}
        return ['path'=>$path,'mime'=>$mime];
    }
    public function references(array $listing,array $ids):array
    {
        ResalePhotos::ids($ids);$allowed=[];foreach($this->manifest($listing)as$photo)if($photo['id'])$allowed[$photo['id']]=$photo['reference'];$out=[];
        foreach($ids as$id){if(!isset($allowed[$id]))throw new \InvalidArgumentException('A selected photo does not belong to this reviewed listing.');$out[]=$allowed[$id];}return$out;
    }
    public static function placeholder(array $payload):array
    {
        $category=$payload['category']??'plot';if(!in_array($category,['house','apartment','plot','commercial','office','farmhouse'],true))$category='plot';return ['assets/images/listing-'.$category.'.svg'];
    }
}
