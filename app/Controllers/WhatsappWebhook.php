<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\WhatsappDeliveryLog;

class WhatsappWebhook extends Controller
{
    private string $verifyToken = 'c120790ddb575b176661eb7ec1ccb2a7ba9ee2d4fd1908c2914182410686413f';

    /**
     * Webhook verification from Meta (GET request)
     */
    public function verify(): ResponseInterface
    {
        $mode = $this->request->getGet('hub_mode') ?? $this->request->getGet('hub.mode');
        $token = $this->request->getGet('hub_verify_token') ?? $this->request->getGet('hub.verify_token');
        $challenge = $this->request->getGet('hub_challenge') ?? $this->request->getGet('hub.challenge');

        $configuredToken = config('Whatsapp')->verifyToken ?? $this->verifyToken;

        if ($mode === 'subscribe' && $token !== null && hash_equals($configuredToken, (string) $token)) {
            return $this->response->setStatusCode(200)->setBody((string) $challenge);
        }

        return $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    /**
     * Webhook event notifications from Meta (POST request)
     */
    public function receive(): ResponseInterface
    {
        $raw = $this->request->getBody();
        if (empty($raw)) {
            return $this->response->setStatusCode(200)->setBody('EVENT_RECEIVED');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || ($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return $this->response->setStatusCode(200)->setBody('EVENT_RECEIVED');
        }

        try {
            $db = db_connect();
            $entries = $payload['entry'] ?? [];

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];

                    // 1. Handle Status Updates (sent, delivered, read, failed)
                    if (!empty($value['statuses']) && is_array($value['statuses'])) {
                        foreach ($value['statuses'] as $statusEvent) {
                            $wamid = (string) ($statusEvent['id'] ?? '');
                            $status = (string) ($statusEvent['status'] ?? 'unknown');
                            $recipient = (string) ($statusEvent['recipient_id'] ?? '');
                            $timestamp = $statusEvent['timestamp'] ?? null;
                            $errorCodes = [];

                            if (!empty($statusEvent['errors']) && is_array($statusEvent['errors'])) {
                                foreach ($statusEvent['errors'] as $err) {
                                    if (isset($err['code'])) {
                                        $errorCodes[] = (int) $err['code'];
                                    }
                                }
                            }

                            // Update existing log row if matched by wamid
                            $updated = false;
                            if ($wamid !== '') {
                                $existing = $db->table('whatsapp_logs')
                                    ->where('wamid', $wamid)
                                    ->get()
                                    ->getRowArray();

                                if ($existing) {
                                    $db->table('whatsapp_logs')
                                        ->where('id', $existing['id'])
                                        ->update([
                                            'status'     => $status,
                                            'responce'   => $raw,
                                            'response'   => $raw,
                                            'updated_at' => date('Y-m-d H:i:s'),
                                        ]);
                                    $updated = true;
                                }
                            }

                            // If not updated an existing row, insert as a new status event log
                            if (!$updated) {
                                $db->table('whatsapp_logs')->insert([
                                    'mobile'        => $recipient,
                                    'otp'           => null,
                                    'wamid'         => $wamid,
                                    'type'          => 'webhook_status',
                                    'template_name' => null,
                                    'message'       => 'Delivery event: ' . $status,
                                    'payload'       => $raw,
                                    'responce'      => $raw,
                                    'response'      => $raw,
                                    'status'        => $status,
                                    'created_at'    => date('Y-m-d H:i:s'),
                                    'updated_at'    => date('Y-m-d H:i:s'),
                                ]);
                            }

                            // Also write to WhatsappDeliveryLog service if available
                            try {
                                (new WhatsappDeliveryLog())->record($wamid, $status, $errorCodes, $timestamp !== null ? (string) $timestamp : null);
                            } catch (\Throwable $e) {
                                // Ignore delivery log file write error
                            }
                        }
                    }

                    // 2. Handle Inbound Messages (User replies on WhatsApp)
                    if (!empty($value['messages']) && is_array($value['messages'])) {
                        foreach ($value['messages'] as $msg) {
                            $from = (string) ($msg['from'] ?? '');
                            $msgId = (string) ($msg['id'] ?? '');
                            $msgType = (string) ($msg['type'] ?? 'text');
                            $bodyText = '';

                            if ($msgType === 'text') {
                                $bodyText = (string) ($msg['text']['body'] ?? '');
                            } else {
                                $bodyText = '[' . $msgType . ' message]';
                            }

                            $db->table('whatsapp_logs')->insert([
                                'mobile'        => $from,
                                'otp'           => null,
                                'wamid'         => $msgId,
                                'type'          => 'inbound_message',
                                'template_name' => null,
                                'message'       => $bodyText,
                                'payload'       => $raw,
                                'responce'      => $raw,
                                'response'      => $raw,
                                'status'        => 'received',
                                'created_at'    => date('Y-m-d H:i:s'),
                                'updated_at'    => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'WhatsappWebhook receive error: ' . $e->getMessage());
        }

        return $this->response->setStatusCode(200)->setBody('EVENT_RECEIVED');
    }
}
