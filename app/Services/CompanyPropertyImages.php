<?php
declare(strict_types=1);
namespace App\Services;
final class CompanyPropertyImages
{
    /** Existing generated legacy image references remain compatible with public/agency cards. */
    public static function save(array $files,array $previous,mixed $remove,array &$written):array
    {
        if(!is_array($remove)||count($remove)>20)throw new \InvalidArgumentException('Invalid removed images.');
        foreach($remove as$image)if(!is_string($image)||!in_array($image,$previous,true))throw new \InvalidArgumentException('An image does not belong to this listing.');
        $images=array_values(array_filter($previous,static fn($image)=>is_string($image)&&preg_match('/\A[0-9]{8}-[a-f0-9]{16}\.(?:jpg|png|webp)\z/D',$image)&&!in_array($image,$remove,true)));
        $files=array_values(array_filter($files,static fn($file)=>$file->getError()!==UPLOAD_ERR_NO_FILE));
        $cfg=config('CustomerPortal');if(count($files)+count($images)>$cfg->adminPhotoMaximumCount)throw new \InvalidArgumentException('Too many images. Maximum '.$cfg->adminPhotoMaximumCount.'.');
        $directory=ROOTPATH.'html/uploads/listings';
        foreach($files as$file){
            if(!$file->isValid()||$file->getSize()<1||$file->getSize()>$cfg->photoMaximumBytes)throw new \InvalidArgumentException('Upload a valid photo within the configured size limit.');
            $bytes=file_get_contents($file->getTempName());$info=@getimagesizefromstring($bytes);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if(!$info||!isset($extensions[$mime])||$info['mime']!==$mime||$info[0]<50||$info[1]<50)throw new \InvalidArgumentException('Use a valid JPEG, PNG or WebP image of at least 50 ? 50 pixels.');
            $image=@imagecreatefromstring($bytes);if(!$image)throw new \InvalidArgumentException('Image cannot be decoded.');
            $name=date('Ymd').'-'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];$path=$directory.'/'.$name;
            try{
                if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('Image storage unavailable.');
                $written[]=$path;
                if($mime==='image/png'){imagealphablending($image,false);imagesavealpha($image,true);$ok=imagepng($image,$path,7);}elseif($mime==='image/webp')$ok=imagewebp($image,$path,88);else$ok=imagejpeg($image,$path,88);
                if(!$ok||filesize($path)>$cfg->photoMaximumBytes)throw new \InvalidArgumentException('Processed image exceeds the size limit.');
                $images[]=$name;
            }finally{imagedestroy($image);}
        }
        return $images;
    }
}
