<?php
declare(strict_types=1);
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/** Stage A only. Separate table; no legacy JSON, public catalog or ERP writes. */
final class ExplorerFilterBuilder
{
    public function __construct(private BaseConnection $db) {}

    public function scope(string $slug, bool $lock = false): array
    {
        $mode = PropertyExplorer::mode($slug);
        if (!$mode) throw new InvalidArgumentException('This project has no verified Layer 2 source definition.');
        $rows = $this->db->query('SELECT m.*,s.code FROM erp_project_mappings m JOIN erp_sources s ON s.id=m.erp_source_id JOIN erp_heads h ON h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id WHERE m.project_slug=? AND m.is_active=1 AND s.is_active=1 AND h.present=1' . ($lock ? ' FOR UPDATE' : ''), [$slug])->getResultArray();
        if (!$rows) throw new InvalidArgumentException('No enabled ERP source/Head mapping is available for this project.');
        $scope = $rows[0];
        $subheads = [];
        foreach ($rows as $row) {
            if ($row['code'] !== $mode[1] || (string) $row['erp_source_id'] !== (string) $scope['erp_source_id'] || (string) $row['erp_head_id'] !== (string) $scope['erp_head_id']) throw new InvalidArgumentException('Ambiguous project/source/Head mapping. Review mappings first.');
            $subheads[] = (string) $row['erp_subhead_id'];
        }
        $shared = $this->db->query('SELECT id FROM erp_project_mappings WHERE erp_source_id=? AND erp_head_id=? AND project_slug<>? AND is_active=1 LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$scope['erp_source_id'], $scope['erp_head_id'], $slug])->getRowArray();
        if ($shared) throw new InvalidArgumentException('This ERP Head is shared by multiple projects; metadata scope requires review.');
        sort($subheads, SORT_STRING);
        $source = $this->db->table('erp_sources')->where('id', $scope['erp_source_id'])->get()->getRowArray();
        return ['project' => $slug, 'source_id' => (string) $scope['erp_source_id'], 'head_id' => (string) $scope['erp_head_id'],
            'field' => PropertyExplorer::layer2Field($slug) ?? throw new InvalidArgumentException('This project has no verified Layer 2 source field.'),
            'subheads' => array_values(array_unique($subheads)), 'source' => $source];
    }

    public static function fingerprint(array $scope): string
    {
        return hash('sha256', json_encode([$scope['project'], $scope['source_id'], $scope['head_id'], $scope['field'], $scope['subheads'], $scope['source']['base_url'], $scope['source']['credential_ref']], JSON_THROW_ON_ERROR));
    }

    public static function key(array $scope, string $value): string
    {
        return PropertyExplorer::key($scope['project'], $scope['source_id'], $scope['head_id'], $scope['field'], $value);
    }

    public function saved(string $slug): array
    {
        return $this->db->table('portal_explorer_filters')->where('project_slug', $slug)->where('parent_filter_id', null)->orderBy('display_order')->orderBy('id')->get()->getResultArray();
    }

    public function fetch(array $scope, callable $transport): array
    {
        $data = $transport($scope['source'], $scope['head_id'], $scope['field'], $scope['subheads']);
        if (($data['complete'] ?? null) !== true || ($data['api_version'] ?? null) !== 'v1' || ($data['source'] ?? null) !== $scope['source']['code']
            || ($data['head'] ?? null) !== $scope['head_id'] || ($data['source_field'] ?? null) !== $scope['field']
            || !is_array($data['options'] ?? null) || !array_is_list($data['options']) || count($data['options']) > 1000) throw new RuntimeException('ERP returned invalid or incorrectly scoped filter metadata.');
        $options = [];
        foreach ($data['options'] as $row) {
            if (!is_array($row)) throw new RuntimeException('Invalid ERP option.');
            $raw = $row['value'] ?? null;
            if ($raw === null || ((is_string($raw) || is_int($raw)) && trim((string) $raw) === '')) continue;
            $value = PropertyExplorer::value($raw);
            if ($value === null) throw new RuntimeException('ERP returned an invalid/oversized source value; no partial draft was accepted.');
            if ($scope['field'] === 'item_sublocation.id' && !in_array($value, $scope['subheads'], true)) throw new RuntimeException('ERP returned a Sub Location outside the selected project mapping.');
            $label = $row['label'] ?? null;
            if (!is_string($label) || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 255) throw new RuntimeException('ERP returned an invalid source label.');
            if (trim($label) === '') continue;
            $key = self::key($scope, $value);
            if (isset($options[$key])) throw new RuntimeException('ERP returned duplicate source values.');
            $options[$key] = ['key' => $key, 'value' => $value, 'source_label' => $label];
        }
        return $options;
    }

    /** Merge for display only: a refresh does not mutate saved settings. */
    public function displayRows(string $slug, ?array $scope, array $fetched): array
    {
        $rows = [];
        if ($scope) foreach ($fetched as $key => $option) {
            $rows[$key] = $option + ['display_name' => mb_substr(trim($option['source_label']), 0, 120), 'icon' => 'fa-layer-group',
                'is_visible' => 0, 'display_order' => 0, 'record_version' => 0, 'saved' => false, 'available' => true, 'editable' => true];
        }
        foreach ($this->saved($slug) as $row) {
            $identity = ['project' => $slug, 'source_id' => (string) $row['erp_source_id'], 'head_id' => (string) $row['erp_head_id'], 'field' => $row['source_field']];
            $key = self::key($identity, $row['source_value']);
            $editable = $scope && $identity['source_id'] === $scope['source_id'] && $identity['head_id'] === $scope['head_id'] && $identity['field'] === $scope['field']
                && ($scope['field'] !== 'item_sublocation.id' || in_array($row['source_value'], $scope['subheads'], true));
            $rows[$key] = $row + ['key' => $key, 'value' => $row['source_value'], 'saved' => true, 'available' => isset($fetched[$key]), 'editable' => (bool) $editable];
        }
        uasort($rows, static fn($a, $b) => [(int) $a['display_order'], $a['display_name'], $a['key']] <=> [(int) $b['display_order'], $b['display_name'], $b['key']]);
        return $rows;
    }

    public function save(string $slug, array $input, array $draft, int $actor): int
    {
        if (!array_is_list($input) || count($input) > 1000 || !$input) throw new InvalidArgumentException('Select at least one option (up to 1000).');
        if (!$this->db->transBegin()) throw new RuntimeException('Settings transaction unavailable.');
        try {
            if (!$this->db->query('SELECT slug FROM portal_projects WHERE slug=? FOR UPDATE', [$slug])->getRowArray()) throw new InvalidArgumentException('Project not found.');
            $scope = $this->scope($slug, true);
            if (($draft['scope'] ?? '') !== self::fingerprint($scope)) throw new InvalidArgumentException('Project mapping changed. Reload and fetch again.');
            $fetched = is_array($draft['options'] ?? null) ? $draft['options'] : [];
            $existing = [];
            foreach ($this->saved($slug) as $candidate) {
                if ((string) $candidate['erp_source_id'] === $scope['source_id'] && (string) $candidate['erp_head_id'] === $scope['head_id'] && $candidate['source_field'] === $scope['field']) {
                    $existing[self::key($scope, $candidate['source_value'])] = $candidate;
                }
            }
            $seen = [];
            foreach ($input as $item) {
                if (!is_array($item) || !is_string($key = $item['key'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/D', $key) || isset($seen[$key])) throw new InvalidArgumentException('Invalid or duplicate selected option.');
                $seen[$key] = true;
                $saved = $existing[$key] ?? null;
                $value = $saved['source_value'] ?? $fetched[$key]['value'] ?? null;
                if (!is_string($value) || PropertyExplorer::value($value) === null || self::key($scope, $value) !== $key
                    || ($scope['field'] === 'item_sublocation.id' && !in_array($value, $scope['subheads'], true))) throw new InvalidArgumentException('Option is not verified for this project. Fetch again.');
                $version = $item['version'] ?? null;
                if (!is_string($version) || !ctype_digit($version) || (int) $version !== (int) ($saved['record_version'] ?? 0)) throw new InvalidArgumentException('A selected filter changed in another tab. Reload before saving.');
                // Reuse safe existing display validation; do not persist its JSON output.
                $validated = PropertyExplorer::patch([], ['key' => $key], ['version' => '0', 'label' => $item['label'] ?? null, 'icon' => $item['icon'] ?? null, 'visible' => $item['visible'] ?? null, 'order' => $item['order'] ?? null]);
                $fields = $validated['_property_explorer']['options'][$key];
                $values = ['display_name' => $fields['label'], 'icon' => $fields['icon'], 'is_visible' => $fields['visible'] ? 1 : 0, 'display_order' => $fields['order'], 'record_version' => (int) $version + 1];
                if (isset($fetched[$key])) {
                    if ($fetched[$key]['value'] !== $value) throw new RuntimeException('Source identity conflict.');
                    $values['source_label'] = $fetched[$key]['source_label'];
                    $values['last_seen_at'] = $draft['fetched_at'];
                }
                if ($saved) {
                    $ok = $this->db->table('portal_explorer_filters')->where('id', $saved['id'])->where('record_version', (int) $version)->update($values);
                    if (!$ok || $this->db->affectedRows() !== 1) throw new RuntimeException('Filter save conflict.');
                    $id = $saved['id'];
                } else {
                    $values += ['project_slug' => $slug, 'erp_source_id' => $scope['source_id'], 'erp_head_id' => $scope['head_id'], 'parent_filter_id' => null,
                        'source_field' => $scope['field'], 'source_value' => $value];
                    if (!$this->db->table('portal_explorer_filters')->insert($values)) throw new RuntimeException('Filter save failed. Refresh and retry.');
                    $id = $this->db->insertID();
                }
                (new AdminAuditService($this->db))->record('explorer.filter_saved', 'projects', $actor, 'portal_explorer_filter', $id,
                    $saved ? self::audit($saved) : null, self::audit($values + ($saved ?? [])));
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new RuntimeException('Filter/audit transaction failed.');
            return count($input);
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private static function audit(array $row): array
    {
        return array_intersect_key($row, array_flip(['project_slug','erp_source_id','erp_head_id','parent_filter_id','source_field','source_value','display_name','icon','is_visible','display_order','record_version']));
    }
}
