<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;

final class AgencyPortalListings
{
    public function __construct(private BaseConnection $db) {}

    private function activeQuery(int $agencyId): BaseBuilder
    {
        return $this->db->table('portal_listings l')
            ->select('l.id,l.project_slug,l.status,l.deal,l.listing_purpose,l.review_state,l.payload,l.asking_price,l.erp_source_id,l.erp_property_id,l.portal_agency_id,l.agency_erp_link_id,s.name AS source_name,s.code AS source_code,p.name AS project_name')
            ->join('agency_erp_links al', 'al.id=l.agency_erp_link_id AND al.portal_agency_id=l.portal_agency_id AND al.erp_source_id=l.erp_source_id')
            ->join('authorized_agencies ag', 'ag.id=l.portal_agency_id')
            ->join('erp_project_mappings m', 'm.id=l.mapping_id AND m.project_slug=l.project_slug AND m.erp_source_id=l.erp_source_id')
            ->join('erp_sources s', 's.id=m.erp_source_id')
            ->join('erp_heads h', 'h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id')
            ->join('erp_subheads sh', 'sh.erp_source_id=m.erp_source_id AND sh.erp_head_id=m.erp_head_id AND sh.erp_subhead_id=m.erp_subhead_id')
            ->join('portal_projects p', 'p.slug=m.project_slug')
            ->where('l.selling_method', 'authorized_agency')->where('l.portal_agency_id', $agencyId)
            ->where('ag.portal_active', 1)->where('ag.agency_type', 'external')->where('ag.is_shalimar_agency', 0)->whereIn('s.code', ['SGC','ZALMI'])
            ->where(ListingPolicy::publicSql(),null,false)
            ->where('al.mapping_status', 'mapped')->where('al.present', 1)->where('s.is_active', 1)
            ->where(AgencyDirectory::resaleAuthorizationSql('al'), null, false)
            ->where(ErpVisibility::mappingSql(), null, false);
    }

    private function decorate(array $row): array
    {
        $payload = json_decode((string)$row['payload'], true);
        if (!is_array($payload)) $payload = [];
        // Whitelist public fields. Never return poster/owner verification/admin payload data.
        $row['public'] = [
            'reference' => self::publicText($payload['ref'] ?? ('SP-' . $row['id']), 100),
            'title' => self::publicText($payload['title'] ?? '', 255),
            'plot' => self::publicText($payload['plot_no'] ?? '', 100),
            'size' => self::publicText($payload['size_label'] ?? '', 100),
            'type' => self::publicText($payload['category'] ?? '', 80),
            'description' => self::publicText($payload['description'] ?? '', 5000),
            'negotiable' => in_array($payload['negotiable'] ?? null, [true,1,'1','yes'], true),
            'published_at' => self::publicText($payload['published_at'] ?? '', 40),
            'images' => self::approvedImages($payload['images'] ?? [], $payload['images_status'] ?? ''),
        ];
        unset($row['payload']);
        return $row;
    }

    private static function publicText($value, int $max): string
    {
        if (!is_scalar($value)) return '';
        return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
    }

    private static function approvedImages($images, $status): array
    {
        if ($status !== 'approved' || !is_array($images) || !array_is_list($images)) return [];
        $out = [];
        foreach ($images as $image) {
            if (!is_string($image)) continue;
            if (preg_match('/\Aresale-photo:([a-f0-9]{64})\z/D', $image, $m)) $out[] = ['url' => site_url('media/resale/' . $m[1])];
            elseif (preg_match('/\A[0-9]{8}-[a-f0-9]{16}\.(?:jpg|png|webp)\z/D', $image)) $out[] = ['url' => \App\Libraries\PortalLinks::publicSite('uploads/listings/' . rawurlencode($image))];
            if (count($out) >= 6) break;
        }
        return $out;
    }

    public function count(int $agencyId): int
    {
        return (int)(clone $this->activeQuery($agencyId))->countAllResults();
    }

    public function search(int $agencyId, array $filters): array
    {
        $query = $this->activeQuery($agencyId);
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $search = mb_substr($search, 0, 100, 'UTF-8'); $like = '%' . $this->db->escapeLikeString($search) . '%';
            $query->groupStart()->where('CAST(l.id AS CHAR) LIKE ' . $this->db->escape($like) . " ESCAPE '!'", null, false)
                ->orWhere("JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.ref')) LIKE " . $this->db->escape($like) . " ESCAPE '!'", null, false)
                ->orWhere("JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.plot_no')) LIKE " . $this->db->escape($like) . " ESCAPE '!'", null, false)->groupEnd();
        }
        $source = is_string($filters['source'] ?? null) && in_array($filters['source'], ['SGC','ZALMI'], true) ? $filters['source'] : '';
        if (in_array($source, ['SGC','ZALMI'], true)) $query->where('s.code', $source);
        $total = (int)(clone $query)->countAllResults();
        $page = max(1, min(100000, is_scalar($filters['page'] ?? null) ? (int)$filters['page'] : 1));
        $perPage = in_array($filters['per_page'] ?? null, ['10','20','50'], true) ? (int)$filters['per_page'] : 10;
        $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages);
        $rows = $query->orderBy('l.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['rows' => array_map(fn($row) => $this->decorate($row), $rows), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages, 'filters' => ['q' => $search, 'source' => $source, 'per_page' => $perPage]];
    }

    public function find(int $agencyId, int $listingId): ?array
    {
        $row = $this->activeQuery($agencyId)->where('l.id', $listingId)->get()->getRowArray();
        return $row ? $this->decorate($row) : null;
    }
}
