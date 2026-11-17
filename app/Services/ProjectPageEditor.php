<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/** Admin drafts live beside the original public payload; saving is not publication. */
final class ProjectPageEditor
{
    public const TEMPLATES = [
        'shalimar-smart-city' => 'Smart City / Community',
        'gulberg-city' => 'Gulberg / Homes & Apartments',
        'zalmi-x' => 'Zalmi / Commercial Tower',
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    public static function slug(string $id): string
    {
        $id = ['PRJ-001' => 'shalimar-smart-city', 'PRJ-002' => 'gulberg-city', 'PRJ-003' => 'zalmi-x'][$id] ?? $id;
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $id) || strlen($id) > 160) {
            throw new InvalidArgumentException('Choose a valid project.');
        }
        return $id;
    }

    public function rows(): array
    {
        $rows = $this->db->table('portal_projects')->orderBy('name')->get()->getResultArray();
        foreach ($rows as &$row) {
            $payload = json_decode($row['payload'], true, 128, JSON_THROW_ON_ERROR);
            $row['editor'] = $payload['_admin_editor'] ?? null;
            $row['display_name'] = $row['editor']['document']['name'] ?? $row['name'];
        }
        return $rows;
    }

    public function get(string $slug): array
    {
        $row = $this->db->table('portal_projects')->where('slug', self::slug($slug))->get()->getRowArray();
        if (!$row) {
            throw new InvalidArgumentException('Project not found.');
        }
        $payload = json_decode($row['payload'], true, 128, JSON_THROW_ON_ERROR);
        $draft = $payload['_admin_editor'] ?? null;
        return [
            'slug' => $row['slug'],
            'document' => $draft['document'] ?? $this->seed($payload),
            'template' => $draft['template'] ?? null,
            'version' => (int) ($draft['version'] ?? 0),
            'updated_at' => $draft['updated_at'] ?? null,
            'published' => (bool) $row['published'],
        ];
    }

    public function templates(): array
    {
        $out = [];
        foreach (self::TEMPLATES as $slug => $label) {
            $row = $this->db->table('portal_projects')->where('slug', $slug)->get()->getRowArray();
            $payload = $row ? json_decode($row['payload'], true, 128, JSON_THROW_ON_ERROR) : ['slug' => $slug, 'name' => $label];
            $out[$slug] = ['label' => $label, 'document' => $this->seed($payload)];
        }
        return $out;
    }

    private function seed(array $payload): array
    {
        require_once ROOTPATH . 'html/includes/subprojects.php';
        $slug = $payload['slug'] ?? '';
        $media = [
            'shalimar-smart-city' => ['ssc-aerial', 'A smart, sustainable city on Mall Road', ['Underground electricity', 'Smart security system', 'EV charging points', 'Rainwater harvesting', 'Smart schools & hospitals', 'Grand Jamia Masjid', 'Food court & shopping bay', 'Planning by Meinhardt (Singapore)']],
            'gulberg-city' => ['gc-apartments', 'An approved, lived-in community in Sargodha', ['Approved society (Form-E)', 'Easy 1.5-year instalments', 'Main Jamia Masjid', 'Community centre', 'Mini market', 'Parks & play areas', '24/7 CCTV & boundary wall', 'Villas & apartment homes']],
            'zalmi-x' => ['zx-night', 'The new landmark of Pine Avenue, Lahore', ['LDA approved', 'Flagship outlets', 'Founder offices', 'High-speed lifts', 'Valet & basement parking', 'AI fire-fighting system', '24/7 power backup', 'EV charging stations']],
        ][$slug] ?? ['', '', []];
        $data = apply_subprojects([$slug => $payload])[$slug];
        $categories = [];
        foreach ($data['tree'] ?? [] as $key => $category) {
            $blocks = [];
            foreach ($category['subs'] ?? [] as $blockKey => $block) {
                $blocks[] = ['key' => (string) $blockKey, 'label' => $block['label'] ?? (string) $blockKey, 'url' => $block['url'] ?? ''];
            }
            $categories[] = ['key' => (string) $key, 'label' => $category['label'] ?? (string) $key, 'url' => $category['url'] ?? '', 'blocks' => $blocks];
        }
        $document = [];
        foreach (['name', 'city', 'location', 'developer', 'kind', 'status', 'summary', 'price_from'] as $field) {
            $document[$field] = (string) ($payload[$field] ?? '');
        }
        $document += [
            'tagline' => $media[1],
            'progress' => (int) ($payload['progress'] ?? 0),
            'cover' => $media[0] ? 'assets/images/photos/' . $media[0] . '.webp' : '',
            'logo' => [
                'shalimar-smart-city' => 'assets/images/logos/ssc-logo.webp',
                'gulberg-city' => 'assets/images/logos/gulberg-city-logo.svg',
                'zalmi-x' => 'assets/images/logos/zalmi-x.png',
            ][$slug] ?? (string) ($payload['logo'] ?? ''),
            'features' => $media[2],
            'gallery' => [],
            'categories' => $categories,
            'facts' => [],
            'actions' => [
                ['label' => 'Available units', 'url' => 'projects.php?project=' . rawurlencode($slug) . '#units'],
                ['label' => 'Resale', 'url' => 'buy.php?project=' . rawurlencode($slug)],
                ['label' => 'Prices', 'url' => 'insights.php?project=' . rawurlencode($slug)],
            ],
        ];
        $galleries = [
            'shalimar-smart-city' => ['ssc-gate-render', 'ssc-mosque', 'ssc-business-district', 'ssc-boulevard'],
            'gulberg-city' => ['gc-aerial', 'gc-homes', 'gc-villas', 'gc-boulevard'],
            'zalmi-x' => ['zx-building', 'zx-office', 'zx-lifts', 'zx-terrace'],
        ];
        foreach ($galleries[$slug] ?? [] as $photo) {
            $document['gallery'][] = ['src' => 'assets/images/photos/' . $photo . '.webp', 'alt' => $document['name']];
        }
        return self::normalize($document);
    }

    private static function text(mixed $value, int $max = 255): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('A text field is invalid or too long.');
        }
        return trim($value);
    }

    public static function url(mixed $value, bool $image = false): string
    {
        $value = self::text($value, 1000);
        if ($value === '') {
            return '';
        }
        if (preg_match('~\Ahttps://~i', $value)) {
            $parts = parse_url($value);
            if (!filter_var($value, FILTER_VALIDATE_URL) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                throw new InvalidArgumentException('Enter a valid HTTPS URL.');
            }
            return $value;
        }
        if (str_contains($value, '..') || str_contains($value, '\\') || preg_match('/[<>"\x27\s]/', $value) || str_starts_with($value, '//')) {
            throw new InvalidArgumentException('Enter a safe local path or HTTPS URL.');
        }
        $pattern = $image
            ? '~\A(?:assets/images/[a-zA-Z0-9_./-]+\.(?:webp|png|jpe?g|svg)|project-image:[a-f0-9]{64})\z~D'
            : '~\A(?:[a-zA-Z0-9_/-]+\.php(?:[?#][a-zA-Z0-9_/?=&%+.#:-]*)?|#[a-zA-Z0-9_-]+)\z~D';
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Enter a safe local path or HTTPS URL.');
        }
        return $value;
    }

    private static function items(mixed $rows, int $max): array
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > $max) {
            throw new InvalidArgumentException('A section contains too many items or invalid data.');
        }
        return $rows;
    }

    public static function normalize(array $document): array
    {
        $out = [];
        foreach (['name', 'city', 'location', 'developer', 'kind', 'status', 'summary', 'price_from', 'tagline'] as $field) {
            $out[$field] = self::text($document[$field] ?? '', $field === 'summary' ? 5000 : 255);
        }
        if ($out['name'] === '') {
            throw new InvalidArgumentException('Project name is required.');
        }
        $progress = $document['progress'] ?? null;
        if (!is_int($progress) || $progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Development progress must be between 0 and 100.');
        }
        $out['progress'] = $progress;
        $out['labels'] = [];
        foreach (['developer' => 'Developer', 'progress' => 'Development progress', 'features' => 'Features', 'categories' => 'Categories & blocks', 'gallery' => 'Project gallery'] as $field => $default) {
            $out['labels'][$field] = self::text($document['labels'][$field] ?? $default, 100);
        }
        $out['cover'] = self::url($document['cover'] ?? '', true);
        $out['logo'] = self::url($document['logo'] ?? '', true);
        $out['features'] = array_map(static fn ($value) => self::text($value), self::items($document['features'] ?? [], 40));
        $out['gallery'] = [];
        foreach (self::items($document['gallery'] ?? [], 24) as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid gallery image.');
            }
            $out['gallery'][] = ['src' => self::url($row['src'] ?? '', true), 'alt' => self::text($row['alt'] ?? '')];
        }
        $out['categories'] = [];
        $categoryKeys = [];
        foreach (self::items($document['categories'] ?? [], 30) as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid category.');
            }
            $key = self::text($row['key'] ?? '', 100);
            if ($key === '' || isset($categoryKeys[$key])) {
                throw new InvalidArgumentException('Category keys must be unique and nonempty.');
            }
            $categoryKeys[$key] = true;
            $blocks = [];
            $blockKeys = [];
            foreach (self::items($row['blocks'] ?? [], 50) as $block) {
                if (!is_array($block)) {
                    throw new InvalidArgumentException('Invalid block.');
                }
                $blockKey = self::text($block['key'] ?? '', 100);
                if ($blockKey === '' || isset($blockKeys[$blockKey])) {
                    throw new InvalidArgumentException('Block keys must be unique and nonempty.');
                }
                $blockKeys[$blockKey] = true;
                $blocks[] = ['key' => $blockKey, 'label' => self::text($block['label'] ?? ''), 'url' => self::url($block['url'] ?? '')];
            }
            $out['categories'][] = ['key' => $key, 'label' => self::text($row['label'] ?? ''), 'url' => self::url($row['url'] ?? ''), 'blocks' => $blocks];
        }
        foreach (['facts', 'actions'] as $field) {
            $out[$field] = [];
            foreach (self::items($document[$field] ?? [], $field === 'facts' ? 12 : 8) as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Invalid project item.');
                }
                $out[$field][] = $field === 'facts'
                    ? ['label' => self::text($row['label'] ?? ''), 'value' => self::text($row['value'] ?? '')]
                    : ['label' => self::text($row['label'] ?? ''), 'url' => self::url($row['url'] ?? '')];
            }
        }
        return $out;
    }

    public function save(?string $slug, array $input, int $actor, callable $render): array
    {
        $creating = $slug === null;
        $slug = self::slug($slug ?? (is_string($input['slug'] ?? null) ? $input['slug'] : ''));
        $template = $input['template'] ?? null;
        if (!is_string($template) || !isset(self::TEMPLATES[$template])) {
            throw new InvalidArgumentException('Select a project template first.');
        }
        if (!is_int($input['version'] ?? null) || $input['version'] < 0 || !is_array($input['document'] ?? null)) {
            throw new InvalidArgumentException('Invalid project document.');
        }
        $document = self::normalize($input['document']);
        $this->db->transBegin();
        try {
            $row = $this->db->query('SELECT * FROM portal_projects WHERE slug = ? FOR UPDATE', [$slug])->getRowArray();
            if (($creating && $row) || (!$creating && !$row)) {
                throw new InvalidArgumentException($creating ? 'This project slug already exists.' : 'Project not found.');
            }
            $payload = $row ? json_decode($row['payload'], true, 128, JSON_THROW_ON_ERROR) : ['slug' => $slug, 'name' => $document['name'], 'active' => false, 'order' => 99];
            $previous = $payload['_admin_editor'] ?? [];
            if ((int) ($previous['version'] ?? 0) !== $input['version']) {
                throw new \App\Exceptions\CustomerFlowException(409, 'This project was saved in another tab. Reload before saving again.', 'project_conflict');
            }
            $html = $render($document, $template);
            if (!is_string($html) || strlen($html) > 1048576) {
                throw new RuntimeException('Project HTML could not be rendered.');
            }
            $payload['_admin_editor'] = [
                'template' => $template,
                'document' => $document,
                'html' => $html,
                'version' => $input['version'] + 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ] + ProjectPageAssets::snapshot($html, $document);
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $saved = $row
                ? $this->db->table('portal_projects')->where('slug', $slug)->update(['payload' => $encoded])
                : $this->db->table('portal_projects')->insert(['slug' => $slug, 'name' => $document['name'], 'published' => 0, 'payload' => $encoded]);
            if (!$saved) {
                throw new RuntimeException('Project could not be saved.');
            }
            (new AdminAuditService($this->db))->record($creating ? 'project.editor_created' : 'project.editor_saved', 'projects', $actor, 'portal_project', $slug, null, ['name' => $document['name'], 'slug' => $slug, 'version' => $input['version'] + 1]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Project audit could not be saved.');
            }
            $this->db->transCommit();
            return ['slug' => $slug, 'version' => $input['version'] + 1, 'updated_at' => $payload['_admin_editor']['updated_at']];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
