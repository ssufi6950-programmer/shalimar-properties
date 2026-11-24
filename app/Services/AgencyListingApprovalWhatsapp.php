<?php
declare(strict_types=1);

namespace App\Services;

use App\Libraries\CustomerIdentity;
use CodeIgniter\Database\BaseConnection;
use Config\Whatsapp;

final class AgencyListingApprovalWhatsappUncertainException extends \RuntimeException {}

/** Persistent WhatsApp template notification for approved Authorized Agency listings. */
final class AgencyListingApprovalWhatsapp
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

    private function publicAgency(array $listing): ?array
    {
        try {
            $agency = (new AgencyDirectory($this->db))->requireEligible(
                (int)$listing['erp_source_id'], (int)$listing['agency_erp_link_id']
            );
            return (int)$agency['portal_agency_id'] === (int)$listing['portal_agency_id'] ? $agency : null;
        } catch (\InvalidArgumentException $error) {
            return null;
        }
    }

    /** Queue with approval in the local portal transaction; never sends here. */
    public function queue(array $listing): void
    {
        if (($listing['selling_method'] ?? '') !== 'authorized_agency') return;
        if (($listing['status'] ?? '') !== 'live' || ($listing['review_state'] ?? '') !== 'approved') {
            throw new \LogicException('Only approved listings can queue an agency WhatsApp notification.');
        }

        $payload = json_decode((string) ($listing['payload'] ?? '{}'), true, 128, JSON_THROW_ON_ERROR);
        $partyId = (int)($listing['portal_agency_id'] ?? 0);
        $agency = $this->publicAgency($listing);
        $agencyName = self::clean($agency['agency_name'] ?? '', 180) ?: 'Authorized Agency';

        $plot = self::clean($payload['plot_no'] ?? '', 100) ?: 'Property ' . (string) ($listing['erp_property_id'] ?? $listing['id']);
        $category = self::clean($payload['category'] ?? ErpPropertyClassification::listingType($payload) ?? '', 100);
        $reference = self::clean(ListingReference::display($listing), 100);
        $propPart = trim($plot . ' ' . $category);
        $property = trim($propPart . ($reference !== '' ? ' (' . $reference . ')' : ''));
        if ($property === '') {
            $property = 'SP-' . (string) $listing['id'];
        }

        $templateName = $this->settings->agencyStatusTemplateName ?? 'agency_property_status_update';
        $languageCode = $this->settings->agencyStatusLanguageCode ?? 'en_US';

        $now = date('Y-m-d H:i:s');
        $insert = $this->db->table('listing_agency_whatsapp')->insert([
            'listing_id' => $listing['id'],
            'listing_version' => $listing['record_version'],
            'portal_agency_id' => $partyId > 0 ? $partyId : null,
            'template_name' => $templateName,
            'language_code' => $languageCode,
            'template_var_1' => $agencyName,
            'template_var_2' => $property,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$insert) throw new \RuntimeException('Agency WhatsApp notification could not be queued.');
    }

    public function statusFor(array $listing): ?array
    {
        if (!$this->db->tableExists('listing_agency_whatsapp')) return null;
        return $this->db->table('listing_agency_whatsapp')
            ->select('id,listing_version,status,attempts,test_mode,recipient_mask,last_error,attempted_at,accepted_at,wamid')
            ->where('listing_id', $listing['id'])->where('listing_version', $listing['record_version'])
            ->get()->getRowArray();
    }

    /** Sends once after approval; only known failures can be explicitly retried. */
    public function dispatch(array $listing, bool $retry = false, ?int $expectedAttempts = null): ?array
    {
        $job = $this->statusFor($listing);
        if (!$job || (int) $this->db->transDepth > 0) return $job;
        $lock = 'agency_listing_wa_' . substr(hash('sha256', $this->db->getDatabase() . ':' . $job['id']), 0, 38);
        if ((int) $this->db->query('SELECT GET_LOCK(?,0) AS held', [$lock])->getRow()->held !== 1) return $job;
        try {
            $job = $this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->get()->getRowArray();
            if (!in_array($job['status'], $retry ? ['pending', 'failed'] : ['pending'], true)
                || ($retry && ($expectedAttempts !== null && $expectedAttempts !== (int) $job['attempts']))) {
                return $this->statusFor($listing);
            }

            $current = $this->db->table('portal_listings')->where('id', $job['listing_id'])->get()->getRowArray();
            if (!$current || !ListingPolicy::active($current) || $current['selling_method'] !== 'authorized_agency'
                || (int) $current['record_version'] !== (int) $job['listing_version']) {
                $this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->update([
                    'status' => 'skipped',
                    'last_error' => 'listing_status_changed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return $this->statusFor($listing);
            }

            $agency = $this->publicAgency($current);
            $mobile = PublicListingContact::phone($agency['phone'] ?? null, true);
            if ($mobile === null) {
                $this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->update([
                    'status' => 'failed',
                    'last_error' => 'no_agency_phone_number',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return $this->statusFor($listing);
            }

            $now = date('Y-m-d H:i:s');
            if (!$this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->where('status', $job['status'])
                ->where('attempts', $job['attempts'])->update([
                    'status' => 'sending',
                    'attempts' => (int) $job['attempts'] + 1,
                    'attempted_at' => $now,
                    'updated_at' => $now,
                    'last_error' => null,
                ]) || $this->db->affectedRows() !== 1) {
                throw new \RuntimeException('Agency WhatsApp notification attempt could not be claimed.');
            }

            $providerAccepted = false;
            try {
                $recipient = (new JazzCustomerSms($this->smsSettings))->resolveNotificationRecipient($mobile);
                $this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->update([
                    'test_mode' => (int) $this->smsSettings->testMode,
                    'recipient_mask' => CustomerIdentity::mask($recipient),
                ]);

                $wamid = $this->sendTemplate($recipient, $job);
                $providerAccepted = true;

                $acceptedAt = date('Y-m-d H:i:s');
                if (!$this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->update([
                    'status' => 'accepted',
                    'wamid' => $wamid,
                    'accepted_at' => $acceptedAt,
                    'updated_at' => $acceptedAt,
                ])) {
                    throw new AgencyListingApprovalWhatsappUncertainException('Provider accepted notification but status could not be stored.');
                }

                log_message('error', 'Agency assignment WhatsApp accepted: listing={listing} notification={notification} wamid={wamid}.', [
                    'listing' => $listing['id'], 'notification' => $job['id'], 'wamid' => $wamid,
                ]);
            } catch (\Throwable $e) {
                $this->db->table('listing_agency_whatsapp')->where('id', $job['id'])->update([
                    'status' => ($providerAccepted || $e instanceof AgencyListingApprovalWhatsappUncertainException) ? 'uncertain' : 'failed',
                    'last_error' => ($providerAccepted || $e instanceof AgencyListingApprovalWhatsappUncertainException) ? 'provider_result_uncertain' : 'delivery_not_confirmed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                log_message('error', 'Agency assignment WhatsApp failed: listing={listing} notification={notification} type={type}.', [
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
            throw new \RuntimeException('Agency WhatsApp service is not configured.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $job['template_name'],
                'language' => ['code' => $job['language_code']],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $job['template_var_1']],
                        ['type' => 'text', 'text' => $job['template_var_2']],
                    ],
                ]],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $url = 'https://graph.facebook.com/' . $settings->apiVersion . '/' . $settings->phoneNumberId . '/messages';

        $handle = curl_init($url);
        if ($handle === false) throw new \RuntimeException('WhatsApp transport unavailable.');
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $settings->accessToken, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $rawPayload,
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

        if ($ok === false || $errno !== 0) {
            throw new AgencyListingApprovalWhatsappUncertainException('WhatsApp transport result is uncertain.');
        }

        $data = json_decode($body, true);
        $wamid = is_array($data) ? ($data['messages'][0]['id'] ?? null) : null;

        // Log into whatsapp_logs table
        try {
            $this->db->table('whatsapp_logs')->insert([
                'mobile' => $recipient,
                'otp' => null,
                'type' => 'agency_listing_approval',
                'template_name' => $job['template_name'],
                'message' => 'Agency: ' . $job['template_var_1'] . ' | Property: ' . $job['template_var_2'],
                'payload' => $rawPayload,
                'responce' => $body,
                'response' => $body,
                'wamid' => $wamid,
                'status' => ($http >= 200 && $http < 300 && is_string($wamid)) ? 'accepted' : 'failed',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $ignored) {}

        if ($http >= 500 || ($http >= 200 && $http < 300 && (!is_string($wamid) || !preg_match('/\Awamid\.[A-Za-z0-9+\/_=-]{1,252}\z/D', $wamid)))) {
            throw new AgencyListingApprovalWhatsappUncertainException('WhatsApp provider response is ambiguous.');
        }

        if ($http < 200 || $http >= 300) {
            $code = is_array($data) && isset($data['error']['code']) && is_numeric($data['error']['code']) ? (int) $data['error']['code'] : 0;
            log_message('error', 'Agency WhatsApp rejected: listing={id} http_status={status} meta_code={code}.', ['id' => $job['listing_id'], 'status' => $http, 'code' => $code]);
            throw new \RuntimeException('WhatsApp provider did not accept the notification.');
        }

        try {
            (new WhatsappDeliveryLog())->record($wamid, 'accepted');
        } catch (\Throwable $ignored) {}

        return $wamid;
    }
}
