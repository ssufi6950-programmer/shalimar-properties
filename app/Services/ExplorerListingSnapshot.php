<?php
declare(strict_types=1);
namespace App\Services;

/** Exact inventory identities, captured server-side before listing approval. */
final class ExplorerListingSnapshot
{
    public const COLUMNS = ['block_no','floor_no','sector','tower','apartment_type','aprt_catagory','category','dimension_type','nature','no_of_floor','sale_type','file_no_series'];

    public static function supports(string $field): bool
    {
        return str_starts_with($field, 'pro_inventory.') && in_array(substr($field, 14), self::COLUMNS, true);
    }

    public static function capture(mixed $raw, int $source, string $property, string $head, string $subhead): ?array
    {
        if ($raw === null) return null; // Older API deployments remain compatible.
        if (!is_array($raw) || ($raw['version'] ?? null) !== 1 || !is_array($raw['fields'] ?? null)
            || (isset($raw['source_id']) && (string)$raw['source_id'] !== (string)$source)
            || (string)($raw['property_id'] ?? '') !== $property || (string)($raw['head_id'] ?? '') !== $head
            || (string)($raw['subhead_id'] ?? '') !== $subhead || count($raw['fields']) > count(self::COLUMNS)) {
            throw new \RuntimeException('Invalid inventory filter snapshot scope.');
        }
        $fields=[];
        foreach ($raw['fields'] as $field=>$value) {
            if (!is_string($field) || !self::supports($field) || (!is_string($value) && !is_int($value))) throw new \RuntimeException('Invalid inventory filter snapshot field.');
            $value=(string)$value;
            if (!mb_check_encoding($value,'UTF-8') || mb_strlen($value)>255 || preg_match('/[\x00-\x1f\x7f]/',$value)) throw new \RuntimeException('Invalid inventory filter snapshot value.');
            if (trim($value)!=='') $fields[$field]=$value;
        }
        return ['version'=>1,'source_id'=>(string)$source,'property_id'=>$property,'head_id'=>$head,'subhead_id'=>$subhead,'fields'=>$fields];
    }
}
