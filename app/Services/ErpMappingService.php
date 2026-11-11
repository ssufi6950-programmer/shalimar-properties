<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;
final class ErpMappingService
{
    public function __construct(private BaseConnection $db) {}
    public function options(): array
    {
        return $this->db->query("SELECT sh.erp_source_id,sh.erp_head_id,sh.erp_subhead_id,h.name AS head_name,sh.name AS subhead_name,s.name AS source_name FROM erp_subheads sh JOIN erp_heads h ON h.erp_source_id=sh.erp_source_id AND h.erp_head_id=sh.erp_head_id JOIN erp_sources s ON s.id=sh.erp_source_id WHERE s.is_active=1 AND h.present=1 AND sh.present=1 AND BINARY h.smp_active=BINARY 'yes' AND BINARY sh.smp_active=BINARY 'yes' ORDER BY s.name,h.name,sh.name")->getResultArray();
    }
    public function create(int $source,string $head,string $subhead,string $project): void
    {
        if (!$this->db->transBegin()) throw new \RuntimeException('Could not start a catalog transaction.');
        try {
            $s=$this->db->query('SELECT * FROM erp_sources WHERE id=? FOR UPDATE',[$source])->getRowArray();
            $pair=$this->db->query('SELECT h.smp_active AS head_active,sh.smp_active AS subhead_active,h.present AS head_present,sh.present AS subhead_present FROM erp_subheads sh JOIN erp_heads h ON h.erp_source_id=sh.erp_source_id AND h.erp_head_id=sh.erp_head_id WHERE sh.erp_source_id=? AND sh.erp_head_id=? AND sh.erp_subhead_id=? FOR UPDATE',[$source,$head,$subhead])->getRowArray();
            if (!$s || !(int)$s['is_active'] || !$pair || !(int)$pair['head_present'] || !(int)$pair['subhead_present'] || !ErpVisibility::eligible($pair['head_active'],$pair['subhead_active'])) throw new \InvalidArgumentException('Only current yes / yes Head/Subhead combinations can be mapped.');
            if (!$this->db->table('portal_projects')->where('slug',$project)->countAllResults()) throw new \InvalidArgumentException('Choose an existing local project.');
            if (!$this->db->table('erp_project_mappings')->insert(['erp_source_id'=>$source,'erp_head_id'=>$head,'erp_subhead_id'=>$subhead,'project_slug'=>$project,'is_active'=>1]) || !$this->db->transStatus()) throw new \InvalidArgumentException('This Head/Subhead combination is already mapped or could not be saved.');
            if (!$this->db->transCommit()) throw new \RuntimeException('Could not commit the catalog transaction.');
        } catch (\Throwable $e) { $this->db->transRollback(); $this->db->resetTransStatus();throw $e; }
    }
    public function setActive(int $id,bool $active): void
    {
        // Existing mapping remains editable even when either ERP flag is no.
        if (!$this->db->table('erp_project_mappings')->where('id',$id)->countAllResults()) throw new \InvalidArgumentException('Mapping not found.');
        $this->db->table('erp_project_mappings')->where('id',$id)->update(['is_active'=>$active?1:0]);
    }
    public function assignListing(int $listingId,int $mappingId): void
    {
        if (!$this->db->transBegin()) throw new \RuntimeException('Could not start a catalog transaction.');
        try {
            $mapping=$this->db->query('SELECT * FROM erp_project_mappings WHERE id=? FOR UPDATE',[$mappingId])->getRowArray();
            $listing=$this->db->query('SELECT * FROM portal_listings WHERE id=? FOR UPDATE',[$listingId])->getRowArray();
            if (!$mapping || !$listing || $mapping['project_slug']!==$listing['project_slug']) throw new \InvalidArgumentException('The listing and mapping must belong to the same local project.');
            $this->db->table('portal_listings')->where('id',$listingId)->update(['mapping_id'=>$mappingId]);
            if (!$this->db->transStatus()) throw new \RuntimeException('Listing mapping could not be saved.');
            if (!$this->db->transCommit()) throw new \RuntimeException('Could not commit the catalog transaction.');
        } catch (\Throwable $e) { $this->db->transRollback(); $this->db->resetTransStatus();throw $e; }
    }
}
