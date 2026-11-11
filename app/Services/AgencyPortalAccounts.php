<?php
namespace App\Services;

use App\Libraries\AgencyPortalIdentity;
use CodeIgniter\Database\BaseConnection;

final class AgencyPortalAccounts
{
    public function __construct(private BaseConnection $db) {}

    public function find(int $agencyId): ?array
    {
        return $this->db->table('agency_portal_accounts')->select('id,portal_agency_id,username,username_type,enabled,must_change_password,temporary_expires_at,auth_version,last_login_at,created_at,updated_at')->where('portal_agency_id', $agencyId)->get()->getRowArray() ?: null;
    }

    public function agencyEligible(int $agencyId): bool
    {
        return (bool)$this->db->query("SELECT a.id FROM authorized_agencies a WHERE a.id=? AND a.portal_active=1 AND a.agency_type='external' AND a.is_shalimar_agency=0 AND EXISTS (
            SELECT 1 FROM agency_erp_links l JOIN erp_sources s ON s.id=l.erp_source_id
            WHERE l.portal_agency_id=a.id AND l.mapping_status='mapped' AND l.present=1 AND s.is_active=1 AND s.code IN ('SGC','ZALMI')
            AND ".AgencyDirectory::resaleAuthorizationSql('l')."
        )", [$agencyId])->getRowArray();
    }

    public function eligibleAgency(int $agencyId): ?array
    {
        $row = $this->db->table('authorized_agencies')->where('id', $agencyId)->get()->getRowArray();
        return $row && $this->agencyEligible($agencyId) ? $row : null;
    }

    public static function temporaryPassword(): string
    {
        // 192 bits of entropy plus the required uppercase and symbol classes.
        return 'A!' . bin2hex(random_bytes(24));
    }

    private static function passwordHash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
            defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1] : ['cost' => 12]);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
    }

    public function create(int $agencyId, int $actor, string $usernameType, ?string $customUsername): array
    {
        if (!in_array($usernameType, ['phone', 'custom'], true)) throw new \DomainException('Choose phone username or custom username.');
        $password = self::temporaryPassword(); $normalized = null;
        $this->db->transBegin();
        try {
            $agency = $this->db->query('SELECT * FROM authorized_agencies WHERE id=? FOR UPDATE', [$agencyId])->getRowArray();
            if (!$agency || !$this->agencyEligible($agencyId)) throw new \DomainException('Only active agencies with current SGC or Zalmi authorization can receive portal access.');
            if ($this->find($agencyId)) throw new \DomainException('This agency already has an account. Use Reset Password or change access status.');
            if ($usernameType === 'phone') {
                $phone = AgencyPortalIdentity::phone((string)$agency['phone']);
                if ($phone === null) throw new \DomainException('This agency has no valid Pakistani mobile number. Choose a custom username.');
                $username = trim((string)$agency['phone']);
                $normalized = 'phone:' . $phone;
            } else {
                $username = AgencyPortalIdentity::custom((string)$customUsername);
                $normalized = AgencyPortalIdentity::normalized($username);
            }
            if ($this->db->table('agency_portal_accounts')->where('normalized_identifier', $normalized)->countAllResults()) throw new \DomainException('That username is already assigned. Choose another username.');
            $now = $this->now();
            $ok = $this->db->table('agency_portal_accounts')->insert([
                'portal_agency_id' => $agencyId, 'username' => $username, 'username_type' => $usernameType,
                'normalized_identifier' => $normalized, 'password_hash' => self::passwordHash($password),
                'enabled' => 1, 'must_change_password' => 1, 'temporary_expires_at' => date('Y-m-d H:i:s', time() + 172800),
                'auth_version' => 1, 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $now, 'updated_at' => $now,
            ]);
            if (!$ok) throw new \RuntimeException('Agency portal account could not be created.');
            $accountId = (int)$this->db->insertID();
            (new AdminAuditService($this->db))->record('agency_portal.credentials_generated', 'agencies', $actor, 'agency_portal_account', $accountId, null, ['portal_agency_id' => $agencyId, 'username' => $username, 'username_type' => $usernameType]);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency portal account transaction failed.');
            return ['username' => $username, 'temporary_password' => $password];
        } catch (\Throwable $e) {
            $this->db->transRollback(); $this->db->resetTransStatus();
            if ($e instanceof \DomainException) throw $e;
            if ($normalized !== null && $this->db->table('agency_portal_accounts')->where('normalized_identifier', $normalized)->countAllResults()) throw new \DomainException('That username is already assigned. Choose another username.');
            throw $e;
        }
    }

    public function reset(int $agencyId, int $actor): array
    {
        $password = self::temporaryPassword(); $now = $this->now();
        $this->db->transBegin();
        try {
            if (!$this->eligibleAgency($agencyId)) throw new \DomainException('Only active agencies with current SGC or Zalmi authorization can receive portal access.');
            $account = $this->db->query('SELECT * FROM agency_portal_accounts WHERE portal_agency_id=? FOR UPDATE', [$agencyId])->getRowArray();
            if (!$account) throw new \DomainException('This agency does not have a portal account yet. Generate credentials first.');
            $ok = $this->db->table('agency_portal_accounts')->where('id', $account['id'])->update([
                'password_hash' => self::passwordHash($password), 'must_change_password' => 1,
                'temporary_expires_at' => date('Y-m-d H:i:s', time() + 172800), 'auth_version' => (int)$account['auth_version'] + 1,
                'updated_by' => $actor, 'updated_at' => $now,
            ]);
            if (!$ok) throw new \RuntimeException('Agency portal password could not be reset.');
            (new AdminAuditService($this->db))->record('agency_portal.password_reset', 'agencies', $actor, 'agency_portal_account', (int)$account['id'], ['portal_agency_id' => $agencyId], ['portal_agency_id' => $agencyId, 'username' => $account['username']]);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency portal reset transaction failed.');
            return ['username' => $account['username'], 'temporary_password' => $password];
        } catch (\Throwable $e) {
            $this->db->transRollback(); $this->db->resetTransStatus();
            if ($e instanceof \DomainException) throw $e;
            throw $e;
        }
    }

    public function setEnabled(int $agencyId, int $actor, bool $enabled): void
    {
        $this->db->transBegin();
        try {
            $account = $this->db->query('SELECT * FROM agency_portal_accounts WHERE portal_agency_id=? FOR UPDATE', [$agencyId])->getRowArray();
            if (!$account) throw new \DomainException('This agency does not have portal credentials.');
            if ($enabled && !$this->eligibleAgency($agencyId)) throw new \DomainException('This agency is not currently active and authorized for SGC or Zalmi.');
            if ((bool)$account['enabled'] !== $enabled) {
                $this->db->table('agency_portal_accounts')->where('id', $account['id'])->update([
                    'enabled' => (int)$enabled, 'auth_version' => (int)$account['auth_version'] + 1,
                    'updated_by' => $actor, 'updated_at' => $this->now(),
                ]);
                (new AdminAuditService($this->db))->record($enabled ? 'agency_portal.access_enabled' : 'agency_portal.access_disabled', 'agencies', $actor, 'agency_portal_account', (int)$account['id'], ['portal_agency_id' => $agencyId, 'enabled' => (int)$account['enabled']], ['portal_agency_id' => $agencyId, 'enabled' => (int)$enabled]);
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency portal status transaction failed.');
        } catch (\Throwable $e) { $this->db->transRollback(); $this->db->resetTransStatus(); throw $e; }
    }
}
