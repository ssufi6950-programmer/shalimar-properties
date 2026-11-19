<?php
declare(strict_types=1);
namespace SmpApi;

if (!defined('SMP_API_BOOTSTRAPPED')) { http_response_code(404); exit; }

/** Authenticated metadata only. CustomerDatabase allows prepared SELECTs only. */
final class FilterOptionsController
{
    public const SOURCE = 'SGC';

    public static function show(): void
    {
        try {
            if (strlen($_SERVER['QUERY_STRING'] ?? '') > 4096) throw new CustomerApiFailure('invalid_request', 'Request too large.', 413);
            $result = self::read(new CustomerDatabase(), $_GET);
            JsonResponse::send(200, $result);
        } catch (CustomerApiFailure $error) {
            JsonResponse::send($error->status, ['error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]]);
        } catch (\Throwable $error) {
            error_log('[SMP API] Filter metadata unavailable: type=' . get_class($error));
            JsonResponse::send(503, ['error' => ['code' => 'filter_metadata_unavailable', 'message' => 'Filter metadata is temporarily unavailable. Check API schema/configuration.']]);
        }
    }

    public static function read($db, array $input): array
    {
        $head = $input['head'] ?? null;
        $kind = $input['kind'] ?? null;
        if (!is_string($head) || !preg_match('/\A[0-9]{1,18}\z/D', $head)
            || !in_array($kind, ['sector', 'sublocation', 'category'], true) || ($kind === 'sector' && self::SOURCE !== 'SGC')) {
            throw new CustomerApiFailure('invalid_scope', 'Invalid metadata scope.', 400);
        }
        $headTable = ApiEnvironment::get('SMP_API_HEAD_TABLE') ?: 'item_location';
        $subTable = ApiEnvironment::get('SMP_API_SUBHEAD_TABLE') ?: 'item_sublocation';
        $parent = ApiEnvironment::get('SMP_API_PARENT_COLUMN') ?: 'location_id';
        foreach ([$headTable, $subTable, $parent] as $identifier) {
            if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D', $identifier)) throw new \RuntimeException('Invalid configured identifier.');
        }
        $db->requireColumns($headTable, ['id']);
        if (!$db->select("SELECT id FROM `$headTable` WHERE id=? LIMIT 1", [$head])) throw new CustomerApiFailure('head_not_found', 'Mapped Head not found.', 422);
        if ($kind === 'sector') {
            // Deliberately no sub_head/company/booking/status constraint: all sectors in this Head.
            $db->requireColumns('pro_inventory', ['head', 'sector']);
            $columns = $db->columns('pro_inventory');
            if (!in_array($columns['sector'] ?? '', ['varchar','char','text','tinytext','mediumtext','longtext','int','bigint','smallint','tinyint','mediumint'], true)) throw new \RuntimeException('Unsupported sector type.');
            $rows = $db->select("SELECT DISTINCT BINARY CONVERT(sector USING utf8mb4) AS value FROM pro_inventory WHERE head=? AND sector IS NOT NULL AND CONVERT(sector USING utf8mb4) REGEXP '[^[:space:]]' ORDER BY value LIMIT 1001", [$head]);
            $field = 'pro_inventory.sector';
        } elseif ($kind === 'category') {
            // Gulberg category values are scoped to the mapped project Sub Locations.
            $ids = $input['subheads'] ?? null;
            if (!is_string($ids) || !preg_match('/\A[0-9]{1,18}(?:,[0-9]{1,18}){0,99}\z/D', $ids)) throw new CustomerApiFailure('invalid_scope', 'Mapped Sub Location IDs required.', 400);
            $ids = array_values(array_unique(explode(',', $ids)));
            $db->requireColumns('pro_inventory', ['head', 'sub_head', 'category']);
            $columns = $db->columns('pro_inventory');
            if (!in_array($columns['category'] ?? '', ['varchar','char','text','tinytext','mediumtext','longtext','enum','int','bigint','smallint','tinyint','mediumint'], true)) throw new \RuntimeException('Unsupported inventory category type.');
            $rows = $db->select("SELECT DISTINCT BINARY CONVERT(category USING utf8mb4) AS value FROM pro_inventory WHERE head=? AND sub_head IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND category IS NOT NULL AND CONVERT(category USING utf8mb4) REGEXP '[^[:space:]]' ORDER BY value LIMIT 1001", array_merge([$head], $ids));
            $field = 'pro_inventory.category';
        } else {
            $ids = $input['subheads'] ?? null;
            if (!is_string($ids) || !preg_match('/\A[0-9]{1,18}(?:,[0-9]{1,18}){0,99}\z/D', $ids)) throw new CustomerApiFailure('invalid_scope', 'Mapped Sub Location IDs required.', 400);
            $ids = array_values(array_unique(explode(',', $ids)));
            $db->requireColumns($subTable, ['id', 'name', $parent]);
            $rows = $db->select("SELECT CAST(id AS CHAR) AS value,name AS label FROM `$subTable` WHERE `$parent`=? AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id LIMIT 1001', array_merge([$head], $ids));
            $field = 'item_sublocation.id';
        }
        if (count($rows) > 1000) throw new CustomerApiFailure('too_many_options', 'Metadata exceeds the 1000-option limit; no partial response was returned.', 422);
        $options = [];
        foreach ($rows as $row) {
            $value = (string) $row['value'];
            if (trim($value) === '') continue;
            if (strlen($value) > 1020 || preg_match('//u', $value) !== 1 || count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY)) > 255) throw new \RuntimeException('Source value cannot be stored exactly.');
            $label = (string) ($row['label'] ?? $value);
            if ($kind !== 'sector' && $kind !== 'category' && trim($label) === '') continue;
            if (strlen($label) > 1020 || preg_match('//u', $label) !== 1 || count(preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY)) > 255) throw new \RuntimeException('Invalid source label.');
            $options[] = ['value' => $value, 'label' => $label];
        }
        return ['api_version' => 'v1', 'complete' => true, 'source' => self::SOURCE, 'head' => $head, 'source_field' => $field, 'options' => $options];
    }
}
