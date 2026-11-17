<?php
declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use PDO;

/** Local, source-scoped Layer 2 metadata. Never reads ERP inventory or contacts. */
final class PropertyExplorer
{
    public const ICONS = [
        'fa-layer-group' => 'Layers', 'fa-house' => 'Home', 'fa-building' => 'Building',
        'fa-store' => 'Shop', 'fa-tree' => 'Tree', 'fa-road' => 'Road',
        'fa-location-dot' => 'Location', 'fa-vector-square' => 'Plot',
    ];

    public function __construct(private PDO|BaseConnection $db) {}

    private function rows(string $sql, array $parameters = []): array
    {
        if ($this->db instanceof PDO) {
            $query = $this->db->prepare($sql);
            $query->execute($parameters);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->db->query($sql, $parameters)->getResultArray();
    }

    public static function mode(string $slug): ?array
    {
        return match ($slug) {
            'gulberg-city' => ['subhead', 'SGC'],
            'shalimar-smart-city' => ['sector', 'SGC'],
            'zalmi-x' => ['subhead', 'ZALMI'],
            default => null,
        };
    }

    public static function value(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        $value = (string) $value;
        return trim($value) !== '' && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= 255 ? $value : null;
    }

    public static function key(string $slug, string $source, string $head, string $kind, string $value): string
    {
        return hash('sha256', json_encode([$slug, $source, $head, $kind, $value], JSON_THROW_ON_ERROR));
    }

    public static function settings(array $payload): array
    {
        $settings = $payload['_property_explorer'] ?? [];
        if (!is_array($settings)) $settings = [];
        return [
            'version' => is_int($settings['version'] ?? null) ? $settings['version'] : 0,
            'options' => is_array($settings['options'] ?? null) ? $settings['options'] : [],
        ];
    }

    /** Admin: synced mapped subheads; sectors only from source-bound local submissions. */
    public function options(string $slug, array $payload, bool $public = false, string $purpose = 'sale'): array
    {
        $mode = self::mode($slug);
        if ($mode === null) return [];
        [$kind, $code] = $mode;
        $sector = "JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(l.payload) THEN l.payload ELSE '{}' END,'$.sector'))";
        if ($public || $kind === 'sector') {
            $from = $public ? PublicListingSearch::fromSql()
                : ' FROM portal_listings l JOIN erp_project_mappings m ON m.id=l.mapping_id AND m.project_slug=l.project_slug AND m.erp_source_id=l.erp_source_id'
                    . ErpVisibility::joins() . ' WHERE JSON_VALID(l.payload)';
            $value = $kind === 'sector' ? $sector : 'CAST(m.erp_subhead_id AS CHAR)';
            $label = $kind === 'sector' ? $sector : 'sh.name';
            $sql = "SELECT s.id AS source_id,m.erp_head_id AS head_id,BINARY $value AS source_value,BINARY $label AS source_label,COUNT(*) AS records"
                . $from . ' AND l.project_slug=? AND s.code=?';
            $parameters = [$slug, $code];
            if ($kind === 'sector') $sql .= " AND l.erp_source_id=s.id AND l.erp_property_id IS NOT NULL AND JSON_TYPE(JSON_EXTRACT(l.payload,'$.sector')) IN ('STRING','INTEGER')";
            if ($public) {
                ListingPolicy::purpose($purpose);
                $sql .= ' AND l.listing_purpose=?';
                $parameters[] = $purpose;
            }
            $sql .= ' GROUP BY s.id,m.erp_head_id,source_value,source_label';
        } else {
            $sql = 'SELECT s.id AS source_id,m.erp_head_id AS head_id,CAST(m.erp_subhead_id AS CHAR) AS source_value,sh.name AS source_label,0 AS records'
                . ' FROM erp_project_mappings m' . ErpVisibility::joins()
                . ' WHERE m.project_slug=? AND s.code=? AND h.present=1 AND sh.present=1';
            $parameters = [$slug, $code];
        }

        $saved = self::settings($payload)['options'];
        $options = [];
        foreach ($this->rows($sql, $parameters) as $row) {
            $value = self::value($row['source_value']);
            if ($value === null) continue;
            $source = (string) $row['source_id'];
            $head = (string) $row['head_id'];
            $key = self::key($slug, $source, $head, $kind, $value);
            $custom = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $label = trim((string) ($row['source_label'] ?? ''));
            if ($label === '') $label = ($kind === 'sector' ? 'Sector ' : 'Subhead ') . trim($value);
            $options[$key] ??= [
                'key' => $key, 'project' => $slug, 'source_id' => $source, 'head_id' => $head,
                'kind' => $kind, 'value' => $value, 'original_name' => $label,
                'label' => is_string($custom['label'] ?? null) && trim($custom['label']) !== '' ? $custom['label'] : $label,
                'icon' => is_string($custom['icon'] ?? null) && isset(self::ICONS[$custom['icon']]) ? $custom['icon'] : 'fa-layer-group',
                'visible' => ($custom['visible'] ?? true) !== false,
                'order' => is_int($custom['order'] ?? null) ? $custom['order'] : 0,
                'count' => 0,
            ];
            $options[$key]['count'] += (int) $row['records'];
        }
        uasort($options, static fn(array $a, array $b) => [$a['order'], $a['label'], $a['key']] <=> [$b['order'], $b['label'], $b['key']]);
        return $public ? array_filter($options, static fn(array $option) => $option['visible']) : $options;
    }

    /** Predicate is resolved server-side; labels never participate in filtering. */
    public static function predicate(array $option): array
    {
        foreach (['source_id', 'head_id'] as $field) {
            if (!is_string($option[$field] ?? null) || !preg_match('/\A[0-9]{1,18}\z/D', $option[$field])) {
                throw new InvalidArgumentException('Invalid Explorer identity.');
            }
        }
        $value = self::value($option['value'] ?? null);
        if ($value === null) throw new InvalidArgumentException('Invalid Explorer value.');
        $sql = ' AND s.id=? AND m.erp_head_id=?';
        $parameters = [$option['source_id'], $option['head_id']];
        if (($option['kind'] ?? '') === 'subhead' && preg_match('/\A[0-9]{1,18}\z/D', $value)) {
            $sql .= ' AND m.erp_subhead_id=?';
        } elseif (($option['kind'] ?? '') === 'sector') {
            $sql .= " AND l.erp_source_id=s.id AND l.erp_property_id IS NOT NULL AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.sector'))=BINARY ?";
        } else {
            throw new InvalidArgumentException('Invalid Explorer dimension.');
        }
        $parameters[] = $value;
        return [$sql, $parameters];
    }

    public static function patch(array $payload, array $option, array $input): array
    {
        $settings = self::settings($payload);
        $version = $input['version'] ?? null;
        if (!is_string($version) || !ctype_digit($version) || (int) $version !== $settings['version']) {
            throw new InvalidArgumentException('Settings changed. Reload before saving.');
        }
        $label = $input['label'] ?? null;
        $icon = $input['icon'] ?? null;
        $order = $input['order'] ?? null;
        $visible = $input['visible'] ?? null;
        if (!is_string($label) || trim($label) === '' || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 120 || preg_match('/[\x00-\x1f\x7f]/', $label)
            || !is_string($icon) || !isset(self::ICONS[$icon]) || !is_string($order) || !ctype_digit($order) || strlen($order) > 5 || (int) $order > 99999
            || !in_array($visible, ['0', '1'], true)) {
            throw new InvalidArgumentException('Enter a display name, allowed icon, Show/Hide and order between 0 and 99999.');
        }
        $settings['options'][$option['key']] = ['label' => trim($label), 'icon' => $icon, 'order' => (int) $order, 'visible' => $visible === '1'];
        $settings['version']++;
        $payload['_property_explorer'] = $settings;
        return $payload;
    }
}
