<?php
namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;

final class AgencyPortalListings
{
    public function __construct(private BaseConnection $db) {}

    private function activeQuery(int $agencyId): BaseBuilder
    {
        $agency=(new AgencyPortalAccounts($this->db))->eligibleAgency($agencyId);
        $query=$this->db->table('portal_listings pl')
            ->select('pl.id,pl.selling_method,pl.project_slug,pl.status,pl.deal,pl.listing_purpose,pl.review_state,pl.payload,pl.asking_price,pl.erp_source_id,pl.erp_property_id,pl.portal_agency_id,pl.agency_erp_link_id,s.name AS source_name,s.code AS source_code,p.name AS project_name')
            ->join('erp_project_mappings m', 'm.id=pl.mapping_id AND m.project_slug=pl.project_slug AND m.erp_source_id=pl.erp_source_id')
            ->join('erp_sources s', 's.id=m.erp_source_id')
            ->join('erp_heads h', 'h.erp_source_id=m.erp_source_id AND h.erp_head_id=m.erp_head_id')
            ->join('erp_subheads sh', 'sh.erp_source_id=m.erp_source_id AND sh.erp_head_id=m.erp_head_id AND sh.erp_subhead_id=m.erp_subhead_id')
            ->join('portal_projects p', 'p.slug=m.project_slug')
            ->whereIn('s.code', ['SGC','ZALMI'])->where('s.is_active',1)
            ->where(ListingPolicy::publicSql('pl'),null,false)
            ->where(ErpVisibility::mappingSql(),null,false);
        if(!$agency)return $query->where('1=0',null,false);
        if($agency['agency_type']==='internal'){
            // Property and agent sources are independent; retain the exact saved membership.
            // Do not depend on portal_agency_id: agent assignments intentionally leave it NULL.
            $membership=ShalimarAgents::eligibleSql('1')
                .' AND c.portal_agency_id='.$this->db->escape($agencyId)
                .' AND a.id=pl.portal_agent_id AND l.id=pl.agent_erp_link_id AND l.erp_source_id=pl.agent_source_id'
                ." AND JSON_VALID(pl.agent_snapshot) AND BINARY JSON_UNQUOTE(JSON_EXTRACT(pl.agent_snapshot,'$.erp_agency_id'))=BINARY CAST(l.erp_agency_id AS CHAR)";
            return $query->where('pl.selling_method','shalimar_agent')->where('pl.agent_review_required',0)
                ->where('EXISTS ('.$membership.')',null,false);
        }
        return $query
            ->join('agency_erp_links al','al.id=pl.agency_erp_link_id AND al.portal_agency_id=pl.portal_agency_id AND al.erp_source_id=pl.erp_source_id')
            ->join('authorized_agencies ag','ag.id=pl.portal_agency_id')
            ->where('pl.selling_method','authorized_agency')->where('pl.portal_agency_id',$agencyId)
            ->where('ag.portal_active',1)->where('ag.agency_type','external')->where('ag.is_shalimar_agency',0)
            ->where('al.mapping_status','mapped')->where('al.present',1)
            ->where(AgencyDirectory::resaleAuthorizationSql('al'),null,false);
    }

    public function decorate(array $row): array
    {
        $payload = json_decode((string)$row['payload'], true);
        if (!is_array($payload)) $payload = [];
        // Whitelist public fields. Never return poster/owner verification/admin payload data.
        $row['public'] = [
            'reference' => self::publicText($payload['ref'] ?? ('SP-' . $row['id']), 100),
            'title' => self::publicText($payload['title'] ?? '', 255),
            'plot' => self::publicText($payload['plot_no'] ?? '', 100),
            'size' => self::publicText($payload['size_label'] ?? '', 100),
            'type' => ErpPropertyClassification::listingType($payload, $row['source_code'] ?? null),
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
            $query->groupStart()->where('CAST(pl.id AS CHAR) LIKE ' . $this->db->escape($like) . " ESCAPE '!'", null, false)
                ->orWhere("JSON_UNQUOTE(JSON_EXTRACT(pl.payload,'$.ref')) LIKE " . $this->db->escape($like) . " ESCAPE '!'", null, false)
                ->orWhere("JSON_UNQUOTE(JSON_EXTRACT(pl.payload,'$.plot_no')) LIKE " . $this->db->escape($like) . " ESCAPE '!'", null, false)->groupEnd();
        }
        $source = is_string($filters['source'] ?? null) && in_array($filters['source'], ['SGC','ZALMI'], true) ? $filters['source'] : '';
        if (in_array($source, ['SGC','ZALMI'], true)) $query->where('s.code', $source);
        $total = (int)(clone $query)->countAllResults();
        $page = max(1, min(100000, is_scalar($filters['page'] ?? null) ? (int)$filters['page'] : 1));
        $perPage = in_array($filters['per_page'] ?? null, ['10','20','50'], true) ? (int)$filters['per_page'] : 10;
        $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages);
        $rows = $query->orderBy('pl.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['rows' => array_map(fn($row) => $this->decorate($row), $rows), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages, 'filters' => ['q' => $search, 'source' => $source, 'per_page' => $perPage]];
    }

    public function find(int $agencyId, int $listingId): ?array
    {
        $row = $this->activeQuery($agencyId)->where('pl.id', $listingId)->get()->getRowArray();
        return $row ? $this->decorate($row) : null;
    }

    /** Full portfolio policy, narrowed to external agency assignments. */
    public function assignmentQuery(int $agencyId): BaseBuilder
    {
        return $this->activeQuery($agencyId)->where('pl.selling_method','authorized_agency')
            ->where('pl.portal_agency_id',$agencyId);
    }
}
