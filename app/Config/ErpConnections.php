<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ErpConnections extends BaseConfig
{
    /** Exact DNS hostnames approved by a deployment administrator. No wildcards or IP literals. */
    public array $allowedHosts = [];
    /** Source code => approved relative health path. Empty until a real endpoint is provided. */
    public array $healthPaths = [];
    /** Reference ID => auth type and environment-variable NAMES; never actual secrets. */
    public array $credentialRefs = [];
    /** Exact approved hostname => verified PUBLIC addresses; optional split-DNS workaround. */
    public array $publicAddressOverrides = [];
    /** Implemented agency endpoints; live hosts require the matching API upload. */
    public array $agencyPaths = ['SGC'=>'/agencies.php','ZALMI'=>'/agencies.php'];
    public array $dealerPaths = ['SGC'=>'/dealers.php'];
    public int $connectTimeout = 3;
    public int $requestTimeout = 8;

    public function __construct()
    {
        parent::__construct();
        foreach (['allowedHosts','healthPaths','credentialRefs','publicAddressOverrides','agencyPaths','dealerPaths'] as $property) {
            $value = env('erp.'.$property);
            if ($value !== null) {
                $decoded = json_decode((string)$value, true);
                // Invalid deployment configuration fails closed.
                $this->{$property} = is_array($decoded) ? $decoded : [];
            }
        }
    }
}
