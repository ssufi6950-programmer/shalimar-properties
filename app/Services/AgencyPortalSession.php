<?php
namespace App\Services;

use App\Libraries\AgencyPortalIdentity;
use CodeIgniter\Database\BaseConnection;

final class AgencyPortalSession
{
    private bool $loaded = false;
    private ?array $account = null;
    private const IDLE_TIMEOUT = 1800;
    private const MAXIMUM_LIFETIME = 28800;
    private const LOGIN_WINDOW = 600;
    private const IP_ATTEMPTS = 10;
    private const ACCOUNT_ATTEMPTS = 5;

    public function __construct(private BaseConnection $db) {}

    private static function hashPassword(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
            defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1] : ['cost' => 12]);
    }

    private static function dummyHash(): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? '$argon2id$v=19$m=65536,t=3,p=1$Tm5yMkhmMVJPcG1XWGM1Vg$y4O78t/qwQ0pDlTaxlNRkgzs8V//ymdUhu7h7WuT8oA'
            : '$2y$12$obCB3sdXMfVEmwGyhBjaJ.3xBxAPOuleMZEJG8tPtFvlooY.wvn4i';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
    }

    private function accountIsEligible(int $agencyId): bool
    {
        return (new AgencyPortalAccounts($this->db))->agencyEligible($agencyId);
    }

    public function attempt(string $identifier, string $password, string $ip): bool
    {
        $normalized = AgencyPortalIdentity::normalized($identifier);
        $identifierHash = hash('sha256', $normalized);
        $ipHash = hash('sha256', $ip);
        // Serialize attempts for this IP across PHP workers, including unknown usernames.
        $lockName = 'agency_login_' . substr(hash('sha256', $this->db->getDatabase() . ':' . $ipHash), 0, 40);
        if ((int)$this->db->query('SELECT GET_LOCK(?, 5) AS acquired', [$lockName])->getRow('acquired') !== 1) return false;
        $since = (new \DateTimeImmutable('-' . self::LOGIN_WINDOW . ' seconds', new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $row = $this->db->query('SELECT * FROM agency_portal_accounts WHERE normalized_identifier=? FOR UPDATE', [$normalized])->getRowArray();
            $blocked = $this->db->table('agency_login_attempts')->where('attempted_at >=', $since)->where('ip_hash', $ipHash)->countAllResults() >= self::IP_ATTEMPTS
                || $this->db->table('agency_login_attempts')->where('attempted_at >=', $since)->where('identifier_hash', $identifierHash)->countAllResults() >= self::ACCOUNT_ATTEMPTS;
            $matches = password_verify($password, $row['password_hash'] ?? self::dummyHash());
            $now = $this->now();
            $valid = !$blocked && $row && (int)$row['enabled'] === 1 && $matches && $this->accountIsEligible((int)$row['portal_agency_id']);
            if ($valid && (int)$row['must_change_password'] === 1 && (!$row['temporary_expires_at'] || $row['temporary_expires_at'] < $now)) $valid = false;
            if (!$valid) {
                if (!$blocked) $this->db->table('agency_login_attempts')->insert(['ip_hash' => $ipHash, 'identifier_hash' => $identifierHash, 'attempted_at' => $now]);
                if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency login security storage failed.');
                return false;
            }
            $this->db->table('agency_portal_accounts')->where('id', $row['id'])->update(['last_login_at' => $now, 'updated_at' => $now]);
            $this->db->table('agency_login_attempts')->where('identifier_hash', $identifierHash)->delete();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency login transaction failed.');
            $this->clear();
            session()->regenerate(true);
            session()->set([
                'agency_portal_account_id' => (int)$row['id'], 'agency_portal_auth_version' => (int)$row['auth_version'],
                'agency_portal_authenticated_at' => time(), 'agency_portal_last_activity' => time(),
            ]);
            $this->loaded = false; $this->account = null;
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback(); $this->db->resetTransStatus(); throw $e;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function user(): ?array
    {
        if ($this->loaded) return $this->account;
        $this->loaded = true;
        $session = session(); $id = (int)$session->get('agency_portal_account_id');
        if (!$id) return null;
        $row = $this->db->table('agency_portal_accounts a')->select('a.*,g.agency_name,g.portal_active,g.agency_type,g.is_shalimar_agency')
            ->join('authorized_agencies g', 'g.id=a.portal_agency_id')->where('a.id', $id)->get()->getRowArray();
        $now = $this->now();
        if (!$row || (int)$row['enabled'] !== 1 || (int)$row['portal_active'] !== 1 || $row['agency_type'] !== 'external' || (int)$row['is_shalimar_agency'] !== 0
            || (int)$row['auth_version'] !== (int)$session->get('agency_portal_auth_version')
            || (int)$session->get('agency_portal_authenticated_at') <= time() - self::MAXIMUM_LIFETIME
            || (int)$session->get('agency_portal_last_activity') <= time() - self::IDLE_TIMEOUT
            || ((int)$row['must_change_password'] === 1 && (!$row['temporary_expires_at'] || $row['temporary_expires_at'] < $now))
            || !$this->accountIsEligible((int)($row['portal_agency_id'] ?? 0))) {
            $this->logout(); return null;
        }
        $session->set('agency_portal_last_activity', time());
        unset($row['password_hash'], $row['normalized_identifier']);
        $this->account = $row;
        return $row;
    }

    public function changePassword(string $current, string $password, string $confirmation): void
    {
        $account = $this->user();
        if (!$account) throw new \DomainException('Your agency session expired. Please sign in again.');
        if (strlen($password) < 12 || strlen($password) > 72 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) throw new \DomainException('Use 12–72 characters with uppercase, lowercase, a number and a symbol.');
        if (!hash_equals($password, $confirmation)) throw new \DomainException('The password confirmation does not match.');
        $this->db->transBegin();
        try {
            $row = $this->db->query('SELECT * FROM agency_portal_accounts WHERE id=? FOR UPDATE', [$account['id']])->getRowArray();
            if (!$row || (int)$row['enabled'] !== 1 || (int)$row['auth_version'] !== (int)session()->get('agency_portal_auth_version') || !$this->accountIsEligible((int)$row['portal_agency_id']) || !password_verify($current, $row['password_hash'])) throw new \DomainException('Enter your current password to continue.');
            if (password_verify($password, $row['password_hash'])) throw new \DomainException('Choose a new password different from the temporary password.');
            $version = (int)$row['auth_version'] + 1; $now = $this->now();
            $this->db->table('agency_portal_accounts')->where('id', $row['id'])->update(['password_hash' => self::hashPassword($password), 'must_change_password' => 0, 'temporary_expires_at' => null, 'auth_version' => $version, 'updated_at' => $now]);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Agency password update failed.');
            session()->regenerate(true); session()->set('agency_portal_auth_version', $version); session()->set('agency_portal_authenticated_at', time()); session()->set('agency_portal_last_activity', time());
            $this->loaded = false; $this->account = null;
        } catch (\Throwable $e) { $this->db->transRollback(); $this->db->resetTransStatus(); throw $e; }
    }

    private function clear(): void
    {
        session()->remove(['agency_portal_account_id','agency_portal_auth_version','agency_portal_authenticated_at','agency_portal_last_activity']);
    }

    public function logout(): void
    {
        $this->clear(); session()->regenerate(true); $this->loaded = true; $this->account = null;
    }
}
