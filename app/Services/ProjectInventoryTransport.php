<?php
declare(strict_types=1);
namespace App\Services;

/** Server-only POST transport for the scoped ERP project inventory API. */
final class ProjectInventoryTransport extends ErpConnectionService
{
    public function fetch(array $source, array $payload): array
    {
        if (($source['auth_type'] ?? '') !== 'bearer' || (int)($source['is_active'] ?? 0) !== 1) {
            throw new \RuntimeException('The ERP source needs an active server-side bearer credential.');
        }
        $base = self::normaliseBaseUrl($source['base_url'] ?? null);
        if (!$base) throw new \RuntimeException('The ERP API base URL is not configured.');
        $host = strtolower((string)parse_url($base, PHP_URL_HOST));
        if (!in_array($host, array_map('strtolower', $this->settings->allowedHosts), true)) throw new \RuntimeException('The ERP host is not approved.');
        $path = $this->settings->projectInventoryPaths[$source['code'] ?? ''] ?? null;
        if (!is_string($path) || !preg_match('~\A/[A-Za-z0-9_-]+\.php\z~D', $path)) throw new \RuntimeException('The project inventory endpoint is not configured.');
        $addresses = $this->connectionAddresses($host);
        if (!$addresses) throw new \RuntimeException('The ERP host could not be resolved.');
        foreach ($addresses as $address) if (!self::isPublicAddress($address)) throw new \RuntimeException('The ERP destination is not public.');
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($body) > 65536) throw new \RuntimeException('The mapped inventory scope is too large.');
        $responseBody = '';
        $ip = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        $curl = curl_init($base.$path);
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge($this->authenticationHeaders($source), ['Content-Type: application/json']),
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$host.':443:'.$ip], CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 12,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody): int {
                if (strlen($responseBody) + strlen($chunk) > 2097152) return 0;
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ]);
        try {
            if (curl_exec($curl) === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) throw new \RuntimeException('The ERP project inventory request failed.');
            $data = json_decode($responseBody, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \RuntimeException('The ERP project inventory response is invalid.');
            return $data;
        } finally { curl_close($curl); }
    }
}
