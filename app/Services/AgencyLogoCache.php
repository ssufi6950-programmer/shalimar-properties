<?php

namespace App\Services;

/** Download public agency images during synchronization, never during public page requests. */
final class AgencyLogoCache
{
    public function __construct(private $fetch = null)
    {
    }

    public static function directory(): string
    {
        return rtrim(defined('WRITEPATH') ? WRITEPATH : dirname(__DIR__, 2) . '/writable/', '/\\') . '/agency-logos';
    }

    public static function key(string $source, $filename): string
    {
        return AgencyLogo::url($source, $filename) !== '' ? hash('sha256', $source . ':' . AgencyLogo::filename($filename)) : '';
    }

    public function store(string $source, $filename, bool $forceRefresh = false): bool
    {
        $key = self::key($source, $filename);
        if ($key === '') return false;
        $file = self::directory() . '/' . $key . '.png';
        if (!$forceRefresh && is_file($file) && filemtime($file) > time() - 86400) return true;

        try {
            $url = AgencyLogo::url($source, $filename);
            $bytes = $this->fetch ? ($this->fetch)($url) : $this->download($url);
            if (!is_string($bytes) || strlen($bytes) > 2097152) return false;
            $info = @getimagesizefromstring($bytes);
            if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
                || $info[0] * $info[1] > 10000000) return false;
            $image = @imagecreatefromstring($bytes);
            if (!$image) return false;
            $scale = min(1, 512 / max($info[0], $info[1]));
            $thumbnail = imagecreatetruecolor(max(1, (int) round($info[0] * $scale)), max(1, (int) round($info[1] * $scale)));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, imagesx($thumbnail), imagesy($thumbnail), $info[0], $info[1]);
            imagedestroy($image);
            if (!is_dir(self::directory()) && !mkdir(self::directory(), 0755, true) && !is_dir(self::directory())) {
                imagedestroy($thumbnail);
                return false;
            }
            $temporary = tempnam(self::directory(), 'logo-');
            if ($temporary === false) { imagedestroy($thumbnail); return false; }
            try {
                if (!imagepng($thumbnail, $temporary, 6)) return false;
                chmod($temporary, 0644);
                if (!rename($temporary, $file) && is_file($file)) {
                    // Keep the previous cache recoverable if the replacement rename fails.
                    $previous = tempnam(self::directory(), 'logo-prev-');
                    if ($previous === false) return false;
                    unlink($previous);
                    if (!rename($file, $previous)) return false;
                    if (!rename($temporary, $file)) {
                        rename($previous, $file);
                        return false;
                    }
                    unlink($previous);
                }
                return is_file($file);
            } finally {
                imagedestroy($thumbnail);
                if (is_file($temporary)) unlink($temporary);
            }
        } catch (\Throwable $error) {
            // Image availability must not change agency authorization or fail a complete directory sync.
            return false;
        }
    }

    private function download(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $raw = $_ENV['erp.publicAddressOverrides'] ?? $_SERVER['erp.publicAddressOverrides'] ?? getenv('erp.publicAddressOverrides');
        $overrides = is_string($raw) ? json_decode($raw, true) : [];
        $addresses = $overrides[$host] ?? null;
        if (!is_array($addresses)) {
            $addresses = [];
            foreach (dns_get_record($host, DNS_A) ?: [] as $record) if (isset($record['ip'])) $addresses[] = $record['ip'];
        }
        if (!$addresses) return null;
        foreach ($addresses as $address) if (!is_string($address) || !ErpConnectionService::isPublicAddress($address)) return null;
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RESOLVE => [$host . ':443:' . $addresses[0]],
            CURLOPT_HTTPHEADER => ['Accept: image/png,image/jpeg,image/webp,image/gif'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2097152) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $ok !== false && $status === 200 ? $body : null;
    }
}
