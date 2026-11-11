<?php
namespace App\Libraries;

final class PortalLinks
{
    /** Public website links support both the local sibling layout and the live html document root. */
    public static function publicSite(string $path = ''): string
    {
        $settings = config('App');
        $configured = $settings->publicSiteURL;
        if ($configured !== '') {
            $parts = parse_url($configured);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['query']) || isset($parts['fragment'])) {
                throw new \RuntimeException('The public website URL is invalid.');
            }
            return rtrim($configured, '/') . '/' . ltrim($path, '/');
        }
        $parts = parse_url(base_url());
        $parent = str_replace('\\', '/', dirname(rtrim($parts['path'] ?? '', '/')));
        if ($parent === '.' || $parent === '/') $parent = '';
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . rtrim($parent, '/') . '/html/' . ltrim($path, '/');
    }

    public static function media(string $id):string
    {
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$id))throw new \InvalidArgumentException('Invalid image reference.');
        return substr(self::customerEntry(),0,-strlen('/customer/post-property')).'/media/resale/'.$id;
    }
    /** Use only the configured local path, never a visitor-controlled host or return URL. */
    public static function customerEntry(): string
    {
        return self::portal('customer/post-property');
    }

    public static function portal(string $route): string
    {
        $root=dirname(__DIR__,2);
        require_once $root.'/vendor/codeigniter4/framework/system/Config/DotEnv.php';
        (new \CodeIgniter\Config\DotEnv($root))->load();
        $base=$_ENV['app.baseURL']??$_SERVER['app.baseURL']??getenv('app.baseURL');
        $path=is_string($base)?parse_url($base,PHP_URL_PATH):null;
        $path=$path===null?'/':$path;
        if (!is_string($base)||$base===''||!is_string($path)||!preg_match('#\A/(?!/)[A-Za-z0-9._%/-]*\z#D',$path)) {
            throw new \RuntimeException('The customer portal URL is unavailable.');
        }
        return rtrim($path,'/').'/'.ltrim($route,'/');
    }
}
