<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
use App\Libraries\CustomerSecrets;
use App\Exceptions\CustomerFlowException;

final class ResalePhotos
{
 public function __construct(private BaseConnection $db){}
 private function root():string{return rtrim(WRITEPATH,'/\\').DIRECTORY_SEPARATOR.'resale-photos';}
 private function path(array $row):string
 {
  if(!preg_match('/\A[a-f0-9]{64}\z/D',$row['id'])||!in_array($row['extension'],['jpg','png','webp'],true))throw new \RuntimeException('Invalid image storage reference.');
  return $this->root().DIRECTORY_SEPARATOR.$row['id'].'.'.$row['extension'];
 }
 public static function ids($value):array
 {
  if(!is_array($value)||!array_is_list($value)||count($value)>config('CustomerPortal')->photoMaximumCount)throw new \InvalidArgumentException('Choose a valid number of property photos.');
  $seen=[];foreach($value as$id){if(!is_string($id)||!preg_match('/\A(?:[a-f0-9]{64}|legacy-[a-f0-9]{64})\z/D',$id)||isset($seen[$id]))throw new \InvalidArgumentException('Choose valid unique photos.');$seen[$id]=true;}return $value;
 }
 public static function marker(string $id):string{return 'resale-photo:'.$id;}
 public static function legacyImages(?array $listing):array
 {
  $payload=$listing?json_decode($listing['payload'],true):[];$out=[];
  foreach($payload['images']??[]as$image)if(is_string($image)&&preg_match('/\A[0-9]{8}-[a-f0-9]{16}\.(?:jpg|png|webp)\z/D',$image))$out['legacy-'.hash('sha256',$image)]=$image;
  return $out;
 }
 public function selection(int $customer,array $ids,string $key,?array $listing=null,?string $binding=null):array
 {
  $images=[];$legacy=self::legacyImages($listing);$payload=$listing?json_decode($listing['payload'],true):[];
  foreach(self::ids($ids)as$id){
   if(isset($legacy[$id])){$images[]=$legacy[$id];continue;}
   $sql=$this->db->table('resale_photos')->where('id',$id)->where('portal_customer_id',$customer)->getCompiledSelect();$photo=$this->db->query($sql.' FOR UPDATE')->getRowArray();
   $retained=$photo&&$listing&&(int)$photo['listing_id']===(int)$listing['id']&&in_array(self::marker($id),$payload['images']??[],true);
   $staged=$photo&&$photo['listing_id']===null&&hash_equals($photo['submission_key'],$key)&&(int)$photo['expires_at']>time();
   if($staged&&$binding!==null)$staged=hash_equals($photo['session_hash'],(new CustomerSecrets(config('CustomerPortal')->identityKey))->digest('photo_session',$binding));
   if((!$retained&&!$staged)||!is_file($this->path($photo)))throw new \InvalidArgumentException('A selected photo is unavailable or belongs to another draft. Upload or review photos again.');
   $images[]=self::marker($id);
  }
  return $images;
 }
 public function upload($file,int $customer,string $key,string $binding):array
 {
  $cfg=config('CustomerPortal');
  if(!in_array(strtolower($file->getClientExtension()),['jpg','jpeg','png','webp'],true))throw new \InvalidArgumentException('Use a JPEG, PNG or WebP file extension.');
  if(!$file->isValid()||$file->getSize()>$cfg->photoMaximumBytes||$file->getSize()<1)throw new \InvalidArgumentException('Upload a valid image within the configured size limit.');
  $raw=file_get_contents($file->getTempName());$info=@getimagesizefromstring($raw);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!$info||!isset($types[$mime])||$info['mime']!==$mime||$info[0]<50||$info[1]<50||$info[0]*$info[1]>$cfg->photoMaximumPixels)throw new \InvalidArgumentException('Use a valid JPEG, PNG or WebP image with safe dimensions.');
  $image=@imagecreatefromstring($raw);if(!$image)throw new \InvalidArgumentException('The uploaded image cannot be decoded.');
  $id=bin2hex(random_bytes(32));$row=['id'=>$id,'extension'=>$types[$mime]];$path=$this->path($row);
  try{
   if(!is_dir($this->root())&&!mkdir($this->root(),0700,true)&&!is_dir($this->root()))throw new \RuntimeException('Image storage is unavailable.');
   if($mime==='image/jpeg')$ok=imagejpeg($image,$path,88);elseif($mime==='image/png'){imagealphablending($image,false);imagesavealpha($image,true);$ok=imagepng($image,$path,7);}else$ok=imagewebp($image,$path,88);
   if(!$ok||filesize($path)>$cfg->photoMaximumBytes)throw new \InvalidArgumentException('The processed image exceeds the size limit.');
   @chmod($path,0600);$this->db->table('resale_photos')->insert($row+['portal_customer_id'=>$customer,'submission_key'=>$key,'session_hash'=>(new CustomerSecrets($cfg->identityKey))->digest('photo_session',$binding),'mime'=>$mime,'checksum'=>hash_file('sha256',$path),'bytes'=>filesize($path),'width'=>$info[0],'height'=>$info[1],'created_at'=>time(),'expires_at'=>time()+$cfg->photoStagingLifetime]);
   if(!$this->db->affectedRows())throw new \RuntimeException('Photo metadata could not be saved.');
  }catch(\Throwable $e){if(is_file($path))unlink($path);throw $e;}finally{imagedestroy($image);}
  return ['id'=>$id,'url'=>site_url('customer/resale/photos/'.$id)];
 }
 public function attach(int $customer,int $listing,array $ids):void
 {
  foreach($ids as$id)if(!str_starts_with($id,'legacy-'))$this->db->table('resale_photos')->where('id',$id)->where('portal_customer_id',$customer)->where('listing_id',null)->update(['listing_id'=>$listing]);
 }
 public function customerFile(string $id,int $customer,string $binding):array
 {
  $row=$this->db->table('resale_photos')->where('id',$id)->where('portal_customer_id',$customer)->get()->getRowArray();
  if(!$row)throw new CustomerFlowException(404,'Image unavailable.');
  if($row['listing_id']===null){if((int)$row['expires_at']<=time()||!hash_equals($row['session_hash'],(new CustomerSecrets(config('CustomerPortal')->identityKey))->digest('photo_session',$binding)))throw new CustomerFlowException(404,'Image unavailable.');}
  elseif(!$this->db->table('portal_listings')->where('id',$row['listing_id'])->where('portal_customer_id',$customer)->countAllResults())throw new CustomerFlowException(404,'Image unavailable.');
  return $this->file($row);
 }
 public function publicFile(string $id):array
 {
  $row=$this->db->query('SELECT ph.* FROM resale_photos ph JOIN portal_listings l ON l.id=ph.listing_id AND l.portal_customer_id=ph.portal_customer_id JOIN erp_project_mappings m ON m.id=l.mapping_id AND m.project_slug=l.project_slug AND (l.erp_source_id IS NULL OR m.erp_source_id=l.erp_source_id)'.ErpVisibility::joins()." WHERE ph.id=? AND ".ErpVisibility::mappingSql().' AND '.ListingPolicy::publicSql()." AND BINARY COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.images_status')),'approved')=BINARY 'approved' AND JSON_CONTAINS(JSON_EXTRACT(l.payload,'$.images'),JSON_QUOTE(?))",[$id,self::marker($id)])->getRowArray();
  if(!$row)throw new CustomerFlowException(404,'Image unavailable.');return $this->file($row);
 }
 private function file(array $row):array
 {
  $path=$this->path($row);$real=realpath($path);$base=realpath($this->root());
  if(!$real||!$base||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($real)||(int)$row['bytes']!==filesize($real)||!hash_equals($row['checksum'],hash_file('sha256',$real))||(new \finfo(FILEINFO_MIME_TYPE))->file($real)!==$row['mime'])throw new CustomerFlowException(404,'Image unavailable.');
  return ['path'=>$real,'mime'=>$row['mime']];
 }
 public function items(?array $listing):array
 {
  if(!$listing)return [];$payload=json_decode($listing['payload'],true);$out=[];
  $legacy=array_flip(self::legacyImages($listing));
  foreach($payload['images']??[]as$image){
   if(!is_string($image))continue;
   if(preg_match('/\Aresale-photo:([a-f0-9]{64})\z/D',$image,$m))$out[]=['id'=>$m[1],'url'=>site_url('customer/resale/photos/'.$m[1])];
   elseif(isset($legacy[$image]))$out[]=['id'=>$legacy[$image],'url'=>\App\Libraries\PortalLinks::publicSite('uploads/listings/'.rawurlencode($image))];
  }return $out;
 }
 public function previewItems(array $ids,?array $listing=null):array
 {
  helper('url');$legacy=self::legacyImages($listing);$out=[];
  foreach($ids as$id)$out[]=['id'=>$id,'url'=>isset($legacy[$id])?\App\Libraries\PortalLinks::publicSite('uploads/listings/'.rawurlencode($legacy[$id])):site_url('customer/resale/photos/'.$id)];return $out;
 }
 public function prune(int $limit=100):int
 {
  $rows=$this->db->table('resale_photos')->where('listing_id',null)->where('expires_at <',time())->limit(max(1,min(1000,$limit)))->get()->getResultArray();$n=0;
  foreach($rows as$row){$this->db->transBegin();try{$current=$this->db->query('SELECT * FROM resale_photos WHERE id=? FOR UPDATE',[$row['id']])->getRowArray();if($current&&$current['listing_id']===null&&(int)$current['expires_at']<time()){$path=$this->path($current);if(is_file($path)&&!unlink($path))throw new \RuntimeException('Expired image could not be removed.');$this->db->table('resale_photos')->where('id',$row['id'])->delete();$n++;}$this->db->transCommit();}catch(\Throwable $e){$this->db->transRollback();$this->db->resetTransStatus();throw $e;}}return $n;
 }
}
