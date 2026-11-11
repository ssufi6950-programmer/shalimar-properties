<?php
namespace App\Services;
final class ErpVisibility
{
    public static function eligible($head, $subhead): bool
    {
        return $head === 'yes' && $subhead === 'yes';
    }
    /** Shared by CI4 and the existing public PHP pages. No remote calls. */
    public static function mappingSql(string $m='m'): string
    {
        return "$m.is_active=1 AND s.is_active=1 AND h.present=1 AND sh.present=1
            AND BINARY h.smp_active=BINARY 'yes' AND BINARY sh.smp_active=BINARY 'yes'
            AND p.published=1";
    }
    public static function joins(string $m='m'): string
    {
        return " JOIN erp_sources s ON s.id=$m.erp_source_id
            JOIN erp_heads h ON h.erp_source_id=$m.erp_source_id AND h.erp_head_id=$m.erp_head_id
            JOIN erp_subheads sh ON sh.erp_source_id=$m.erp_source_id AND sh.erp_head_id=$m.erp_head_id AND sh.erp_subhead_id=$m.erp_subhead_id
            JOIN portal_projects p ON p.slug=$m.project_slug ";
    }
}
