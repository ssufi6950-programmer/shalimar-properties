<?php
declare(strict_types=1);
namespace App\Libraries;

/** Canonical URLs use server configuration, never the request Host header. */
final class PublicWebsiteUrl
{
    public static function image(string $image):string
    {
        if(preg_match('~\Ahttps?://~i',$image))return $image;
        $base=self::make('index.php');$parts=parse_url($base);
        if(str_starts_with($image,'/'))return $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'').$image;
        return substr($base,0,-strlen('index.php')).$image;
    }
    public static function make(string $path,array $query=[]):string
    {
        // sitemap.php can use this helper before the catalog initializes its PDO connection.
        static $loaded=false;
        if(!$loaded){(new \CodeIgniter\Config\DotEnv(dirname(__DIR__,2)))->load();$loaded=true;}
        $env=static fn($key)=>$_ENV[$key]??$_SERVER[$key]??getenv($key);
        $base=$env('app.publicSiteURL');
        if(!$base){$portal=parse_url((string)$env('app.baseURL'));$parent=dirname(rtrim($portal['path']??'','/'));$base=($portal['scheme']??'').'://'.($portal['host']??'').(isset($portal['port'])?':'.$portal['port']:'').($parent==='/'?'':$parent).'/html/';}
        $parts=parse_url($base);
        if(!$parts||!in_array($parts['scheme']??'',['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!preg_match('/\A[a-z0-9-]+\.php\z/D',$path))throw new \RuntimeException('Public website URL configuration is invalid.');
        return rtrim($base,'/').'/'.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
    }
}
