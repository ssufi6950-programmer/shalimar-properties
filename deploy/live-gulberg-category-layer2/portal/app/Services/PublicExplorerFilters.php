<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Public filter resolver. It reads saved configuration and local listing snapshots only. */
final class PublicExplorerFilters
{
    /** Only source fields with a verified, source/property-bound local listing snapshot. */
    private const LISTING_FIELDS = [
        'pro_inventory.sector' => 'sector',
        'pro_inventory.block_no' => 'block',
    ];

    public function __construct(private PDO $db) {}

    public function options(string $slug): array
    {
        $mode = PropertyExplorer::mode($slug);
        if ($mode === null) return [];
        [$kind, $code] = $mode;
        $query = $this->db->prepare('SELECT DISTINCT m.erp_source_id,m.erp_head_id,s.code FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id JOIN erp_heads h ON h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id WHERE m.project_slug=? AND m.is_active=1 AND s.is_active=1 AND h.present=1');
        $query->execute([$slug]);
        $scopes = $query->fetchAll(PDO::FETCH_ASSOC);
        if (!$scopes) return [];
        if (count($scopes) !== 1 || $scopes[0]['code'] !== $code) throw new RuntimeException('Explorer project scope is ambiguous.');
        $scope = $scopes[0];
        $field = PropertyExplorer::layer2Field($slug);
        if ($field === null) return [];
        $sql = 'SELECT f.* FROM portal_explorer_filters f WHERE f.project_slug=? AND f.erp_source_id=? AND f.erp_head_id=? AND f.source_field=? AND f.parent_filter_id IS NULL AND f.is_visible=1';
        if ($field === 'item_sublocation.id') $sql .= ' AND EXISTS (SELECT 1 FROM erp_project_mappings mapped WHERE mapped.project_slug=f.project_slug AND mapped.erp_source_id=f.erp_source_id AND mapped.erp_head_id=f.erp_head_id AND mapped.is_active=1 AND BINARY CAST(mapped.erp_subhead_id AS CHAR)=BINARY f.source_value)';
        $query = $this->db->prepare($sql . ' ORDER BY f.display_order,f.id');
        $query->execute([$slug, $scope['erp_source_id'], $scope['erp_head_id'], $field]);
        $out = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $value = PropertyExplorer::value($row['source_value']);
            if ($value === null || ($field === 'item_sublocation.id' && !preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', $value))) continue;
            $key = PropertyExplorer::key($slug, (string) $row['erp_source_id'], (string) $row['erp_head_id'], $field, $value);
            $out[$key] = ['key' => $key, 'filter_id' => (string) $row['id'], 'project' => $slug,
                'source_id' => (string) $row['erp_source_id'], 'head_id' => (string) $row['erp_head_id'],
                'source_field' => $field, 'kind' => match ($field) { 'pro_inventory.category' => 'category', 'pro_inventory.sector' => 'sector', default => 'subhead' }, 'value' => $value,
                'label' => $row['display_name'], 'icon' => isset(PropertyExplorer::ICONS[$row['icon']]) ? $row['icon'] : 'fa-layer-group'];
        }
        return $out;
    }

    /**
     * Resolve a public path without trusting labels or browser-supplied field names.
     * $tree is a dot-separated sequence of saved child row IDs below the Stage B root.
     */
    public function state(string $slug, string $rootKey, string $tree): array
    {
        $roots = $this->options($slug);
        if ($rootKey === '') {
            if ($tree !== '') throw new InvalidArgumentException('A child path requires its Layer 2 selection.');
            return ['roots'=>$roots,'row'=>$roots,'path'=>[],'root'=>null,'chosen'=>null,'tree'=>[],'unsupported'=>false];
        }
        $root = $roots[$rootKey] ?? $this->legacySubheadRoot($slug, $rootKey);
        if (!$root) throw new InvalidArgumentException('This filter is unavailable for the selected project.');

        $ids = [];
        if ($tree !== '') {
            if (!preg_match('/\A[1-9][0-9]{0,17}(?:\.[1-9][0-9]{0,17})*\z/D', $tree)) {
                throw new InvalidArgumentException('The selected filter path is invalid.');
            }
            foreach (explode('.', $tree) as $id) {
                if (isset($ids[$id])) throw new InvalidArgumentException('The selected filter path contains a cycle.');
                $ids[$id] = $id;
            }
        }

        $path = [[
            'id'=>$root['filter_id'], 'parent_filter_id'=>null,
            'project_slug'=>$slug, 'erp_source_id'=>$root['source_id'], 'erp_head_id'=>$root['head_id'],
            'source_field'=>$root['source_field'], 'source_value'=>$root['value'],
            'display_name'=>$root['label'], 'icon'=>$root['icon'],
        ]];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = $this->db->prepare('SELECT id,project_slug,erp_source_id,erp_head_id,parent_filter_id,source_field,source_value,display_name,icon,is_visible,display_order FROM portal_explorer_filters WHERE project_slug=? AND erp_source_id=? AND erp_head_id=? AND id IN (' . $placeholders . ')');
            $query->execute([$slug, $root['source_id'], $root['head_id'], ...array_values($ids)]);
            $found = [];
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $found[(string)$row['id']] = $row;
            if (count($found) !== count($ids)) throw new InvalidArgumentException('A selected child filter no longer exists in this project.');
            $parent = (string)$root['filter_id'];
            foreach ($ids as $id) {
                $row = $found[$id] ?? null;
                if (!$row || (int)$row['is_visible'] !== 1 || (string)$row['parent_filter_id'] !== $parent
                    || $row['project_slug'] !== $slug || (string)$row['erp_source_id'] !== $root['source_id']
                    || (string)$row['erp_head_id'] !== $root['head_id'] || !self::matchable((string)$row['source_field'])
                    || PropertyExplorer::value($row['source_value']) === null) {
                    throw new InvalidArgumentException('A selected filter is hidden, unsupported or outside its saved parent path.');
                }
                $path[] = $row;
                $parent = $id;
            }
        }

        $chosenRow = $path[array_key_last($path)];
        $childrenQuery = $this->db->prepare('SELECT id,project_slug,erp_source_id,erp_head_id,parent_filter_id,source_field,source_value,display_name,icon,is_visible,display_order FROM portal_explorer_filters WHERE project_slug=? AND erp_source_id=? AND erp_head_id=? AND parent_filter_id=? AND is_visible=1 ORDER BY display_order,id');
        $childrenQuery->execute([$slug, $root['source_id'], $root['head_id'], $chosenRow['id']]);
        $children = [];
        $hasUnsupported = false;
        foreach ($childrenQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!self::matchable((string)$row['source_field']) || PropertyExplorer::value($row['source_value']) === null) {
                $hasUnsupported = true;
                continue;
            }
            $children[] = self::childOption($row);
        }
        $chosen = end($path);
        return [
            'roots'=>$roots,
            'row'=>$rootKey === '' ? $roots : $children,
            'path'=>$path,
            'root'=>$root,
            'chosen'=>[
                'id'=>(string)$chosen['id'],
                'label'=>(string)$chosen['display_name'],
                'icon'=>(string)$chosen['icon'],
                'source_field'=>(string)$chosen['source_field'],
                'value'=>(string)$chosen['source_value'],
            ],
            'tree'=>array_values($ids),
            'unsupported'=>$hasUnsupported,
        ];
    }

    private static function matchable(string $field): bool
    {
        return $field === 'item_sublocation.id' || ExplorerListingSnapshot::supports($field);
    }

    /** Keep old Gulberg Subhead URLs resolving, without showing those filters in the UI. */
    private function legacySubheadRoot(string $slug, string $key): ?array
    {
        if ($slug !== 'gulberg-city') return null;
        $query = $this->db->prepare('SELECT f.*,s.code FROM portal_explorer_filters f JOIN erp_sources s ON s.id=f.erp_source_id JOIN erp_project_mappings mapped ON mapped.project_slug=f.project_slug AND mapped.erp_source_id=f.erp_source_id AND mapped.erp_head_id=f.erp_head_id AND mapped.is_active=1 AND BINARY CAST(mapped.erp_subhead_id AS CHAR)=BINARY f.source_value WHERE f.project_slug=? AND f.source_field=? AND f.parent_filter_id IS NULL AND f.is_visible=1 ORDER BY f.id LIMIT 1000');
        $query->execute([$slug, 'item_sublocation.id']);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['code'] !== 'SGC' || !preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', (string)$row['source_value'])) continue;
            $candidate = PropertyExplorer::key($slug, (string)$row['erp_source_id'], (string)$row['erp_head_id'], 'item_sublocation.id', (string)$row['source_value']);
            if (!hash_equals($candidate, $key)) continue;
            return ['key'=>$candidate,'filter_id'=>(string)$row['id'],'project'=>$slug,'source_id'=>(string)$row['erp_source_id'],'head_id'=>(string)$row['erp_head_id'],
                'source_field'=>'item_sublocation.id','kind'=>'subhead','value'=>(string)$row['source_value'],'label'=>(string)$row['display_name'],
                'icon'=>isset(PropertyExplorer::ICONS[$row['icon']])?$row['icon']:'fa-layer-group'];
        }
        return null;
    }

    private static function childOption(array $row): array
    {
        return [
            'id'=>(string)$row['id'],
            'parent_id'=>(string)$row['parent_filter_id'],
            'label'=>(string)$row['display_name'],
            'icon'=>isset(PropertyExplorer::ICONS[$row['icon']]) ? $row['icon'] : 'fa-layer-group',
            'source_field'=>(string)$row['source_field'],
            'value'=>(string)$row['source_value'],
        ];
    }

    /** Build AND predicates for every saved ancestor; recheck visibility and parent links at query time. */
    public static function pathPredicate(array $explorer): array
    {
        $mode = is_string($explorer['project'] ?? null) ? PropertyExplorer::mode($explorer['project']) : null;
        $path = $explorer['path'] ?? null;
        if ($mode === null || !is_array($path) || !array_is_list($path) || !$path) {
            throw new InvalidArgumentException('Invalid public filter path.');
        }
        $source = $mode[1];
        $expectedSource = $explorer['source_id'] ?? null;
        $expectedHead = $explorer['head_id'] ?? null;
        if (!is_string($expectedSource) || !preg_match('/\A[0-9]{1,18}\z/D', $expectedSource)
            || !is_string($expectedHead) || !preg_match('/\A[0-9]{1,18}\z/D', $expectedHead)) {
            throw new InvalidArgumentException('Invalid public filter scope.');
        }

        $sql = ' AND m.erp_source_id=? AND m.erp_head_id=? AND BINARY s.code=BINARY ?';
        $parameters = [$expectedSource, $expectedHead, $source];
        $conditions = [];
        $previous = null;
        foreach ($path as $index => $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $node['id'])
                || !is_string($node['source_field'] ?? null) || !is_string($node['source_value'] ?? null)
                || PropertyExplorer::value($node['source_value']) === null) {
                throw new InvalidArgumentException('Invalid public filter identity.');
            }
            $field = $node['source_field'];
            $value = $node['source_value'];
            $expectedField = $index === 0 ? PropertyExplorer::layer2Field($explorer['project']) : null;
            $legacyGulbergRoot = $index === 0 && $explorer['project'] === 'gulberg-city' && $field === 'item_sublocation.id';
            if (($index === 0 && $field !== $expectedField && !$legacyGulbergRoot) || ($index > 0 && !self::matchable($field))) {
                throw new InvalidArgumentException('Unsupported public filter field.');
            }
            if (isset($conditions[$field]) && $conditions[$field] !== $value) {
                throw new InvalidArgumentException('The selected filter path contradicts itself.');
            }
            $conditions[$field] = $value;

            $parentSql = $index === 0 ? 'selected.parent_filter_id IS NULL' : 'selected.parent_filter_id=?';
            $sql .= ' AND EXISTS (SELECT 1 FROM portal_explorer_filters selected WHERE selected.id=? AND selected.project_slug=l.project_slug AND selected.erp_source_id=m.erp_source_id AND selected.erp_head_id=m.erp_head_id AND ' . $parentSql . ' AND selected.is_visible=1 AND selected.source_field=? AND BINARY selected.source_value=BINARY ?)';
            $parameters[] = $node['id'];
            if ($index > 0) $parameters[] = $previous;
            $parameters[] = $field;
            $parameters[] = $value;

            if ($field === 'item_sublocation.id') {
                if (!preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', $value)) throw new InvalidArgumentException('Invalid Sub Location identity.');
                $sql .= ' AND BINARY CAST(m.erp_subhead_id AS CHAR)=BINARY ?';
                $parameters[] = $value;
            } else {
                $snapshot="JSON_EXTRACT(l.payload,'$._explorer_snapshot')";
                $pathJson='$._explorer_snapshot.fields."'.$field.'"';
                $snapshotMatch="JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.version'))='1' AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.source_id'))=BINARY CAST(m.erp_source_id AS CHAR) AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.property_id'))=BINARY l.erp_property_id AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.head_id'))=BINARY CAST(m.erp_head_id AS CHAR) AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.subhead_id'))=BINARY CAST(m.erp_subhead_id AS CHAR) AND JSON_TYPE(JSON_EXTRACT(l.payload,?))='STRING' AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,?))=BINARY ?";
                $sql .= " AND l.erp_source_id=m.erp_source_id AND l.erp_property_id IS NOT NULL AND (($snapshotMatch)";
                array_push($parameters,$pathJson,$pathJson,$value);
                // Legacy Sector/Block are usable only when no new authoritative snapshot exists.
                if(isset(self::LISTING_FIELDS[$field])){
                    $legacyPath='$.' . self::LISTING_FIELDS[$field];
                    $sql.=" OR (($snapshot IS NULL OR JSON_TYPE($snapshot)='NULL') AND JSON_TYPE(JSON_EXTRACT(l.payload,?)) IN ('STRING','INTEGER') AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,?))=BINARY ?)";
                    array_push($parameters,$legacyPath,$legacyPath,$value);
                }
                $sql.=')';
            }
            $previous = $node['id'];
        }
        return [$sql, $parameters];
    }

    /** Recheck the saved identity/visibility inside the count and results queries. */
    public static function predicate(array $option): array
    {
        $mode = is_string($option['project'] ?? null) ? PropertyExplorer::mode($option['project']) : null;
        if ($mode === null) throw new InvalidArgumentException('Invalid Explorer project.');
        $legacyGulbergRoot = $option['project'] === 'gulberg-city' && ($option['source_field'] ?? null) === 'item_sublocation.id';
        $field = $legacyGulbergRoot ? 'item_sublocation.id' : PropertyExplorer::layer2Field($option['project']);
        if ($field === null || ($option['source_field'] ?? null) !== $field) throw new InvalidArgumentException('Invalid Explorer source field.');
        foreach (['filter_id', 'source_id', 'head_id'] as $id) {
            if (!is_string($option[$id] ?? null) || !preg_match('/\A[0-9]{1,18}\z/D', $option[$id])) throw new InvalidArgumentException('Invalid Explorer identity.');
        }
        $value = PropertyExplorer::value($option['value'] ?? null);
        if ($value === null) throw new InvalidArgumentException('Invalid Explorer value.');
        $sql = ' AND m.erp_source_id=? AND m.erp_head_id=? AND BINARY s.code=BINARY ?'
            . ' AND EXISTS (SELECT 1 FROM portal_explorer_filters selected WHERE selected.id=? AND selected.project_slug=l.project_slug AND selected.erp_source_id=m.erp_source_id AND selected.erp_head_id=m.erp_head_id AND selected.parent_filter_id IS NULL AND selected.is_visible=1 AND selected.source_field=? AND BINARY selected.source_value=BINARY ?)';
        $parameters = [$option['source_id'], $option['head_id'], $mode[1], $option['filter_id'], $field, $value];
        if ($field === 'item_sublocation.id') {
            if (!preg_match('/\A(?:0|[1-9][0-9]{0,17})\z/D', $value)) throw new InvalidArgumentException('Invalid Explorer Sub Location.');
            $sql .= ' AND BINARY CAST(m.erp_subhead_id AS CHAR)=BINARY ?';
        } else {
            $snapshotPath = '$._explorer_snapshot.fields."' . $field . '"';
            $sql .= " AND l.erp_source_id=m.erp_source_id AND l.erp_property_id IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.version'))='1'"
                . " AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.source_id'))=BINARY CAST(m.erp_source_id AS CHAR)"
                . " AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.property_id'))=BINARY l.erp_property_id"
                . " AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.head_id'))=BINARY CAST(m.erp_head_id AS CHAR)"
                . " AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$._explorer_snapshot.subhead_id'))=BINARY CAST(m.erp_subhead_id AS CHAR)"
                . " AND JSON_TYPE(JSON_EXTRACT(l.payload,?))='STRING' AND BINARY JSON_UNQUOTE(JSON_EXTRACT(l.payload,?))=BINARY ?";
            array_push($parameters, $snapshotPath, $snapshotPath, $value);
            return [$sql, $parameters];
        }
        $parameters[] = $value;
        return [$sql, $parameters];
    }
}
