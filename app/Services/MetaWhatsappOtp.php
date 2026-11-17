<?php

namespace App\Services;

use App\Libraries\CustomerIdentity;
use Config\Whatsapp;
use InvalidArgumentException;
use RuntimeException;

/** The caller supplies the destination after applying the shared SMS test routing. */
class MetaWhatsappOtp implements CustomerSmsGateway
{
    public function __construct(private Whatsapp $settings)
    {
    }

    public function sendCode(string $verifiedMobile, string $otp): void
    {
        if (!preg_match('/\A[0-9]{6}\z/D', $otp)) {
            throw new InvalidArgumentException('Invalid OTP format.');
        }

        $mobile = CustomerIdentity::mobile($verifiedMobile);

        if (
            !$this->settings->enabled
            || $this->settings->accessToken === ''
            || preg_match('/[\x00-\x20\x7f]/', $this->settings->accessToken)
            || !preg_match('/\A[0-9]{5,25}\z/D', $this->settings->phoneNumberId)
            || !preg_match('/\Av[0-9]{1,3}\.[0-9]{1,2}\z/D', $this->settings->apiVersion)
            || !preg_match('/\A[a-z0-9_]{1,512}\z/D', $this->settings->templateName)
            || !preg_match('/\A[a-z]{2,3}(?:_[A-Z]{2})?\z/D', $this->settings->languageCode)
        ) {
            throw new RuntimeException('WhatsApp OTP delivery is not configured.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $mobile,
            'type' => 'template',
            'template' => [
                'name' => $this->settings->templateName,
                'language' => ['code' => $this->settings->languageCode],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = 'https://graph.facebook.com/' . $this->settings->apiVersion
            . '/' . $this->settings->phoneNumberId . '/messages';

        $reply = $this->performRequest($url, $rawPayload, $mobile);

        if ($this->settings->logResponses) {
            $this->logResponse($reply, $mobile, $otp);
        }

        $data = json_decode($reply['body'], true);

        if (
            $reply['status'] < 200
            || $reply['status'] >= 300
            || !is_array($data)
            || isset($data['error'])
            || !is_string($data['messages'][0]['id'] ?? null)
            || $data['messages'][0]['id'] === ''
        ) {
            $code = $data['error']['code'] ?? null;
            $subcode = $data['error']['error_subcode'] ?? null;

            log_message(
                'error',
                'WhatsApp OTP rejected: http_status={http_status} meta_code={meta_code} meta_subcode={meta_subcode}.',
                [
                    'http_status' => (string) $reply['status'],
                    'meta_code' => is_int($code) ? (string) $code : 'none',
                    'meta_subcode' => is_int($subcode) ? (string) $subcode : 'none',
                ]
            );

            throw new RuntimeException('WhatsApp OTP delivery failed.');
        }

        (new WhatsappDeliveryLog())->record($data['messages'][0]['id'], 'accepted');
    }

    private function logResponse(array $reply, string $mobile, string $otp): void
    {
        // Preserve the provider response, masking sensitive values if echoed.
        $body = str_replace(
            [$this->settings->accessToken, $mobile, '0' . substr($mobile, 2), $otp],
            ['[TOKEN REDACTED]', '[PHONE REDACTED]', '[PHONE REDACTED]', '[OTP REDACTED]'],
            $reply['body']
        );

        // Encode as one line so provider text cannot inject additional log entries.
        $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        // Error level makes this temporary diagnostic visible with production logging.
        log_message(
            'error',
            'WhatsApp OTP response: http_status={http_status} body={response_body}',
            [
                'http_status' => (string) $reply['status'],
                'response_body' => $encodedBody === false ? '"[unreadable response]"' : $encodedBody,
            ]
        );
    }

    protected function performRequest(string $url, string $payload, string $mobile): array
    {
        $curl = curl_init($url);
        $body = '';

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->settings->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 16384) {
                    return 0;
                }

                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        try {
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($ok === false) {
                log_message(
                    'error',
                    'WhatsApp OTP transport failed: curl_errno={curl_errno} http_status={http_status}.',
                    [
                        'curl_errno' => (string) curl_errno($curl),
                        'http_status' => (string) $status,
                    ]
                );

                throw new RuntimeException('WhatsApp OTP delivery failed.');
            }

            return ['status' => $status, 'body' => $body];
        } finally {
            curl_close($curl);
        }
    }
}
