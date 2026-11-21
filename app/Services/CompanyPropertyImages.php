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
            $uploadError=$file->getError();
            if(in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)){
                $serverLimit=(string)ini_get('upload_max_filesize');
                throw new \InvalidArgumentException('The selected photo exceeds the live server upload limit'.($serverLimit!==''?' of '.$serverLimit:'').'. Choose a smaller image and try again.');
            }
            if($uploadError===UPLOAD_ERR_PARTIAL)throw new \InvalidArgumentException('The photo upload was interrupted before completion. Select the image and try again.');
            if(in_array($uploadError,[UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE,UPLOAD_ERR_EXTENSION],true))throw new \InvalidArgumentException('The server could not store the uploaded photo. Please contact support.');
            if(!$file->isValid()||$file->getSize()<1)throw new \InvalidArgumentException('The selected photo is empty or was not uploaded correctly.');
            if($file->getSize()>$cfg->photoMaximumBytes)throw new \InvalidArgumentException('The selected photo exceeds the application limit of '.self::sizeLabel($cfg->photoMaximumBytes).'. Choose a smaller image.');
            $temporary=$file->getTempName();
            if($temporary===''||!is_file($temporary)||!is_readable($temporary))throw new \InvalidArgumentException('The uploaded photo could not be read from temporary storage. Select it again and retry.');
            $bytes=@file_get_contents($temporary);
            if(!is_string($bytes)||$bytes==='')throw new \InvalidArgumentException('The uploaded photo could not be read. Select it again and retry.');
            $info=@getimagesizefromstring($bytes);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            try{$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);}catch(\Throwable $error){throw new \InvalidArgumentException('The uploaded file type could not be verified. Export it as JPEG, PNG or WebP and try again.');}
            if(!is_string($mime)||!$info||!isset($extensions[$mime])||($info['mime']??null)!==$mime||$info[0]<50||$info[1]<50)throw new \InvalidArgumentException('Use a valid JPEG, PNG or WebP image of at least 50 x 50 pixels.');
            $image=@imagecreatefromstring($bytes);if(!$image)throw new \InvalidArgumentException('The image is damaged or cannot be decoded. Export it again as JPEG, PNG or WebP.');
            $name=date('Ymd').'-'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];$path=$directory.'/'.$name;
            try{
                if(!is_dir($directory)&&!@mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('Image storage unavailable.');
                $written[]=$path;
                if($mime==='image/png'){imagealphablending($image,false);imagesavealpha($image,true);$ok=@imagepng($image,$path,7);}elseif($mime==='image/webp')$ok=@imagewebp($image,$path,88);else$ok=@imagejpeg($image,$path,88);
                if(!$ok||!is_file($path))throw new \RuntimeException('The processed image could not be saved. Please contact support.');
                $processedSize=@filesize($path);if(!is_int($processedSize)||$processedSize>$cfg->photoMaximumBytes)throw new \InvalidArgumentException('The processed image exceeds the limit of '.self::sizeLabel($cfg->photoMaximumBytes).'. Choose a smaller image.');
                $images[]=$name;
            }finally{imagedestroy($image);}
        }
        return $images;
    }
    private static function sizeLabel(int $bytes):string{return rtrim(rtrim(number_format($bytes/1048576,1),'0'),'.').' MB';}
}
