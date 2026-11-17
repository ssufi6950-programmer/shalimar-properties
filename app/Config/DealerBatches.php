<?php
namespace Config;

final class DealerBatches extends \CodeIgniter\Config\BaseConfig
{
    public int $maximumLinks = 10000;
    public int $checkpointLifetime = 7200;
}
