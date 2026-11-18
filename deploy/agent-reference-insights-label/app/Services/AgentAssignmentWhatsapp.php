<?php
declare(strict_types=1);

namespace App\Services;

use App\Libraries\CustomerIdentity;
use CodeIgniter\Database\BaseConnection;
use Config\Whatsapp;

final class AgentAssignmentWhatsappUncertainException extends \RuntimeException {}

/** Persistent WhatsApp template notification for approved Shalimar Agent listings. */
final class AgentAssignmentWhatsapp
{
    public function __construct(private BaseConnection $db, private ?Whatsapp $settings = null, private ?\Config\Sms $smsSettings = null)
    {
        $this->settings ??= config('Whatsapp');
        $this->smsSettings ??= config('Sms');
    }

    private static function clean(mixed $value, int $limit): string
    {
        $value = is_string($value) ? $value : '';
        return mb_substr(trim(preg_replace('/[\x00-\x20\x7f]+/u', ' ', $value) ?? ''), 0, $limit);
    }

    /** Queue with approval in the local portal transaction; never sends here. */
    public function queue(array $listing): void
    {
        if (($listing['selling_method'] ?? '') !== 'shalimar_agent') return;
        if (($listing['status'] ?? '') !== 'live' || ($listing['review_state'] ?? '') !== 'approved') {
            throw new \LogicException('Only approved listings can queue an agent WhatsApp notification.');
        }
        $payload = json_decode((string) $listing['payload'], true, 128, JSON_THROW_ON_ERROR);
        $plot = self::clean($payload['plot_no'] ?? '', 100) ?: 'Property '.(string) $listing['erp_property_id'];
        $type = self::clean(ErpPropertyClassification::listingType($payload), 100);
        $reference = self::clean(ListingReference::display($listing), 100);
        $property = trim($plot.' '.$type).($reference !== '' ? ' ('.$reference.')' : '');
        $snapshot = is_array($payload['agent_snapshot'] ?? null) ? $payload['agent_snapshot'] : [];
        $agentName = self::clean($snapshot['name'] ?? '', 180);
        if ($agentName === '') throw new \RuntimeException('Approved listing has no assigned agent name.');
        $now = (new ShalimarAgents($this->db))->now();
        $insert = $this->db->table('listing_assignment_whatsapp')->insert([
            'listing_id' => $listing['id'], 'listing_version' => $listing['record_version'],
            'portal_agent_id' => $listing['portal_agent_id'], 'agent_erp_link_id' => $listing['agent_erp_link_id'],
            'agent_source_id' => $listing['agent_source_id'], 'template_name' => $this->settings->agentAssignmentTemplateName,
            'language_code' => $this->settings->agentAssignmentLanguageCode,
            'template_var_1' => $agentName, 'template_var_2' => $property,
            'template_var_3' => 'Shalimar Group', 'status' => 'pending', 'attempts' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        if (!$insert) throw new \RuntimeException('Agent WhatsApp notification could not be queued.');
    }

    public function statusFor(array $listing): ?array
    {
        if (!$this->db->tableExists('listing_assignment_whatsapp')) return null;
        return $this->db->table('listing_assignment_whatsapp')
            ->select('id,listing_version,status,attempts,test_mode,recipient_mask,last_error,attempted_at,accepted_at,wamid')
            ->where('listing_id', $listing['id'])->where('listing_version', $listing['record_version'])
            ->get()->getRowArray();
    }

    /** Sends once after approval; only known failures can be explicitly retried. */
    public function dispatch(array $listing, bool $retry = false, ?int $expectedAttempts = null): ?array
    {
        $job = $this->statusFor($listing);
        if (!$job || (int) $this->db->transDepth > 0) return $job;
        $lock = 'agent_assignment_wa_'.substr(hash('sha256', $this->db->getDatabase().':'.$job['id']), 0, 38);
        if ((int) $this->db->query('SELECT GET_LOCK(?,0) AS held', [$lock])->getRow()->held !== 1) return $job;
        try {
            $job = $this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->get()->getRowArray();
            if (!in_array($job['status'], $retry ? ['pending', 'failed'] : ['pending'], true)
                || ($retry && ($expectedAttempts === null || $expectedAttempts !== (int) $job['attempts']))) return $this->statusFor($listing);

            $current = $this->db->table('portal_listings')->where('id', $job['listing_id'])->get()->getRowArray();
            if (!$current || !ListingPolicy::active($current) || $current['selling_method'] !== 'shalimar_agent'
                || (int) $current['record_version'] !== (int) $job['listing_version']
                || (string) $current['portal_agent_id'] !== (string) $job['portal_agent_id']
                || (string) $current['agent_erp_link_id'] !== (string) $job['agent_erp_link_id']
                || (string) $current['agent_source_id'] !== (string) $job['agent_source_id']) {
                $this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->update([
                    'status' => 'skipped', 'last_error' => 'listing_assignment_changed',
                    'updated_at' => (new ShalimarAgents($this->db))->now(),
                ]);
                return $this->statusFor($listing);
            }

            $now = (new ShalimarAgents($this->db))->now();
            if (!$this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->where('status', $job['status'])
                ->where('attempts', $job['attempts'])->update([
                    'status' => 'sending', 'attempts' => (int) $job['attempts'] + 1,
                    'attempted_at' => $now, 'updated_at' => $now, 'last_error' => null,
                ]) || $this->db->affectedRows() !== 1) throw new \RuntimeException('WhatsApp notification attempt could not be claimed.');

            $providerAccepted = false;
            try {
                $agent = (new ShalimarAgents($this->db))->requireEligible((int) $current['erp_source_id'], (int) $job['portal_agent_id'], false, (int) $job['agent_erp_link_id']);
                if ((int) $agent['agent_erp_link_id'] !== (int) $job['agent_erp_link_id']
                    || (int) $agent['agent_source_id'] !== (int) $job['agent_source_id']) throw new \RuntimeException('Agent assignment changed.');
                $mobile = CustomerIdentity::mobile($agent['mobile']);
                $recipient = (new JazzCustomerSms($this->smsSettings))->resolveNotificationRecipient($mobile);
                $this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->update([
                    'test_mode' => (int) $this->smsSettings->testMode,
                    'recipient_mask' => CustomerIdentity::mask($recipient),
                ]);
                $wamid = $this->sendTemplate($recipient, $job);
                $providerAccepted = true;
                if (!$this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->update([
                    'status' => 'accepted', 'wamid' => $wamid, 'accepted_at' => (new ShalimarAgents($this->db))->now(),
                    'updated_at' => (new ShalimarAgents($this->db))->now(),
                ])) throw new AgentAssignmentWhatsappUncertainException('Provider accepted notification but status could not be stored.');
                log_message('error', 'Agent assignment WhatsApp accepted: listing={listing} notification={notification} wamid={wamid}.', [
                    'listing' => $listing['id'], 'notification' => $job['id'], 'wamid' => $wamid,
                ]);
            } catch (\Throwable $e) {
                $this->db->table('listing_assignment_whatsapp')->where('id', $job['id'])->update([
                    'status' => ($providerAccepted || $e instanceof AgentAssignmentWhatsappUncertainException) ? 'uncertain' : 'failed',
                    'last_error' => ($providerAccepted || $e instanceof AgentAssignmentWhatsappUncertainException) ? 'provider_result_uncertain' : 'delivery_not_confirmed',
                    'updated_at' => (new ShalimarAgents($this->db))->now(),
                ]);
                log_message('error', 'Agent assignment WhatsApp failed: listing={listing} notification={notification} type={type}.', [
                    'listing' => $listing['id'], 'notification' => $job['id'], 'type' => get_class($e),
                ]);
            }
            return $this->statusFor($listing);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function sendTemplate(string $recipient, array $job): string
    {
        $settings = $this->settings;
        if (!$settings->enabled || $settings->accessToken === '' || preg_match('/[\x00-\x20\x7f]/', $settings->accessToken)
            || !preg_match('/\A[0-9]{5,25}\z/D', $settings->phoneNumberId)
            || !preg_match('/\Av[0-9]{1,3}\.[0-9]{1,2}\z/D', $settings->apiVersion)
            || !preg_match('/\A[a-z0-9_]{1,512}\z/D', (string) $job['template_name'])
            || !preg_match('/\A[a-z]{2,3}(?:_[A-Z]{2})?\z/D', (string) $job['language_code'])) {
            throw new \RuntimeException('Agent assignment WhatsApp is not configured.');
        }
        $payload = [
            'messaging_product' => 'whatsapp', 'to' => $recipient, 'type' => 'template',
            'template' => [
                'name' => $job['template_name'], 'language' => ['code' => $job['language_code']],
                'components' => [[
                    'type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => $job['template_var_1']],
                        ['type' => 'text', 'text' => $job['template_var_2']],
                        ['type' => 'text', 'text' => $job['template_var_3']],
                    ],
                ]],
            ],
        ];
        $url = 'https://graph.facebook.com/'.$settings->apiVersion.'/'.$settings->phoneNumberId.'/messages';
        $handle = curl_init($url);
        if ($handle === false) throw new \RuntimeException('WhatsApp transport unavailable.');
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$settings->accessToken, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 16384) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $http = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);
        if ($ok === false || $errno !== 0) throw new AgentAssignmentWhatsappUncertainException('WhatsApp transport result is uncertain.');
        $data = json_decode($body, true);
        $wamid = is_array($data) ? ($data['messages'][0]['id'] ?? null) : null;
        if ($http >= 500 || ($http >= 200 && $http < 300 && (!is_string($wamid) || !preg_match('/\Awamid\.[A-Za-z0-9+\/_=-]{1,252}\z/D', $wamid)))) {
            throw new AgentAssignmentWhatsappUncertainException('WhatsApp provider response is ambiguous.');
        }
        if ($http < 200 || $http >= 300) {
            $code = is_array($data) && isset($data['error']['code']) && is_numeric($data['error']['code']) ? (int) $data['error']['code'] : 0;
            log_message('error', 'Agent assignment WhatsApp rejected: http_status={status} meta_code={code}.', ['status' => $http, 'code' => $code]);
            throw new \RuntimeException('WhatsApp provider did not accept the notification.');
        }
        return $wamid;
    }
}
