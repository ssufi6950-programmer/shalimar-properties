<?php

namespace App\Services;

/** Only public agency logos from the known ERP customer-images folder, never owner photos. */
final class AgencyLogo
{
    public static function filename($value): string
    {
        if (!is_string($value)) return '';
        $value = trim($value);
        return strlen($value) <= 255
            && preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9_. -]*\.(?:jpe?g|png|webp|gif)\z/iD', $value)
            && !str_contains($value, '..') ? $value : '';
    }

    public static function url(string $source, $filename): string
    {
        $filename = self::filename($filename);
        $origin = match ($source) {
            'SGC' => 'https://sgcacc.com',
            'ZALMI' => 'https://zalmidevelopments.com/ERP786-ZALMIDEV',
            default => '',
        };
        return $filename !== '' && $origin !== '' ? $origin . '/customer-images/' . rawurlencode($filename) : '';
    }

    public static function cachedUrl(string $source, $filename): string
    {
        $key = AgencyLogoCache::key($source, $filename);
        $file = $key !== '' ? AgencyLogoCache::directory() . '/' . $key . '.png' : '';
        return $file !== '' && is_file($file) ? 'agency-logo.php?id=' . $key . '&v=' . substr(hash_file('sha256', $file), 0, 16) : '';
    }
}
