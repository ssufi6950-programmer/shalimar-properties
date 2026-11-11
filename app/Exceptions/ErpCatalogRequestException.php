<?php
namespace App\Exceptions;
/** Only fixed, credential-free error descriptions may be shown in the admin UI. */
final class ErpCatalogRequestException extends \RuntimeException
{
    public static function response(int $status,?string $code): self
    {
        if ($status===403 && $code==='https_required') return new self('ERP API rejected the request: its server/proxy is not reporting HTTPS (403 https_required). Fix trusted HTTPS detection on the live server.');
        if ($status===401 && $code==='authorization_header_missing') return new self('ERP API did not receive the Authorization header (HTTP 401). Upload the API header-forwarding update, including its .htaccess file.');
        if ($status===401) return new self('ERP API rejected its bearer credentials (HTTP 401). Check that the live token hash matches the portal credential.');
        if ($status===503 && $code==='authentication_unavailable') return new self('ERP API bearer authentication is not configured on the live server (HTTP 503).');
        if ($status===503 && $code==='catalog_unconfigured') return new self('ERP API database access is not configured on the live server (HTTP 503).');
        $catalogErrors=[
            'db_access_denied'=>'ERP database login/permissions were rejected. Check the production DB credentials in the API configuration.',
            'db_not_found'=>'The API is configured for a database that does not exist on the live ERP server.',
            'db_unreachable'=>'The API could not connect to its ERP database host, port or socket.',
            'erp_config_unreadable'=>'The API cannot read the configured live ERP config.php file. Check its path and PHP file-read permissions.',
            'erp_config_ambiguous'=>'The ERP config has multiple database branches. Set the production DB values directly in the API catalog.local.php file.',
            'erp_config_dynamic'=>'The ERP config uses dynamic DB values. Set its production DB values directly in the API catalog.local.php file.',
            'erp_config_incomplete'=>'The ERP config file is missing one or more required DB constants.',
            'erp_table_missing'=>'The selected ERP database does not expose the expected Head/Subhead tables.',
            'erp_column_missing'=>'The ERP Head/Subhead schema is missing a required identifier, name or parent column.',
            'smp_active_missing'=>'The live ERP Head/Subhead table is missing the smp_active string column.',
            'smp_active_not_string'=>'The live ERP smp_active column is numeric; the integration requires the original string values.',
            'db_driver_missing'=>'The live PHP installation has neither the selected mysqli nor PDO MySQL driver.',
            'catalog_read_failed'=>'The live ERP catalog SELECT query failed. Check the ERP PHP error log.',
        ];
        if ($status===503 && isset($catalogErrors[$code??''])) return new self($catalogErrors[$code]);
        return new self('ERP catalog returned HTTP '.$status.'. Check the live API configuration and PHP error log.');
    }
}
