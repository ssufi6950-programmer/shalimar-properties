<?php
namespace Config;

final class DealerPortal extends \CodeIgniter\Config\BaseConfig
{
    /** Dedicated server-only key, at least 32 random characters. Never generated on demand. */
    public string $rateLimitKey = '';
    public int $temporaryLifetime = 86400;
    public int $idleTimeout = 1800;
    public int $maximumLifetime = 28800;
    protected function getEnvValue(string $property,string $prefix,string $shortPrefix)
    {
        foreach(['dealerPortal.'.$property,'dealerPortal_'.str_replace('.','_',$property)] as $key){
            if(array_key_exists($key,$_ENV))return $_ENV[$key];
            if(array_key_exists($key,$_SERVER))return $_SERVER[$key];
        }
        return parent::getEnvValue($property,$prefix,$shortPrefix);
    }
}
