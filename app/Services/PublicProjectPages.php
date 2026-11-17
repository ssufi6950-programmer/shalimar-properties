<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class PublicProjectPages
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** Apply the same local publication/source/Head/Subhead gate as the public site. */
    private function visibleRows(?string $slug = null): array
    {
        $sql = 'SELECT DISTINCT p.slug, p.name, p.payload FROM erp_project_mappings m'
            . ErpVisibility::joins()
            . ' WHERE ' . ErpVisibility::mappingSql();
        $parameters = [];

        if ($slug !== null) {
            $sql .= ' AND p.slug = ?';
            $parameters[] = $slug;
        }

        return $this->db->query($sql . ' ORDER BY p.slug', $parameters)->getResultArray();
    }

    private static function content(array $row, bool $documentOnly = false): ?array
    {
        $payload = json_decode($row['payload'], true, 128, JSON_THROW_ON_ERROR);
        $saved = $payload['_admin_editor'] ?? null;

        if (!is_array($saved) || !is_array($saved['document'] ?? null)) {
            return null;
        }

        if (!$documentOnly) {
            if (($saved['asset_version'] ?? null) !== 1) {
                return null;
            }
            foreach (['html', 'css', 'js', 'page_html'] as $field) {
                if (!is_string($saved[$field] ?? null)) {
                    return null;
                }
            }
        }

        // Explicit public projection: never expose the full DB payload or updated_by.
        return [
            'slug' => $row['slug'],
            'name' => $saved['document']['name'] ?? $row['name'],
            'template' => $saved['template'],
            'version' => $saved['version'],
            'updated_at' => $saved['updated_at'],
            'payload' => $documentOnly ? ['document' => $saved['document']] : [
                'document' => $saved['document'],
                'html' => $saved['html'],
                'css' => $saved['css'],
                'js' => $saved['js'],
                'page_html' => $saved['page_html'],
            ],
        ];
    }

    public function all(bool $documentOnly = false): array
    {
        $result = [];

        foreach ($this->visibleRows() as $row) {
            $content = self::content($row, $documentOnly);
            if ($content !== null) {
                $result[] = $content;
            }
        }

        return $result;
    }

    public function find(string $slug, bool $documentOnly = false): ?array
    {
        $rows = $this->visibleRows(ProjectPageEditor::slug($slug));
        return $rows ? self::content($rows[0], $documentOnly) : null;
    }

    public function hasImage(string $id): bool
    {
        $reference = 'project-image:' . $id;

        foreach ($this->all(true) as $project) {
            $document = $project['payload']['document'];
            if (($document['cover'] ?? '') === $reference || ($document['logo'] ?? '') === $reference) {
                return true;
            }
            foreach ($document['gallery'] ?? [] as $image) {
                if (($image['src'] ?? '') === $reference) {
                    return true;
                }
            }
        }

        return false;
    }
}
