<?php

namespace App\Services;

/** Writes minimal WhatsApp send/delivery events outside the app's normal thresholded log. */
final class WhatsappDeliveryLog
{
    public function record(string $wamid, string $status, array $errorCodes = [], ?string $metaTimestamp = null): void
    {
        if (
            strlen($wamid) > 256
            || !preg_match('/\Awamid\.[A-Za-z0-9+\/_=-]{1,252}\z/D', $wamid)
            || !in_array($status, ['accepted', 'sent', 'delivered', 'read', 'failed'], true)
        ) {
            return;
        }

        // Keep the provider timestamp rather than trying to infer a current state from
        // webhook arrival order. Meta may retry or deliver status events out of order.
        $metaTimestamp = is_string($metaTimestamp) && preg_match('/\A[0-9]{1,12}\z/D', $metaTimestamp)
            ? $metaTimestamp
            : null;
        $safeErrorCodes = [];
        if ($status === 'failed') {
            foreach ($errorCodes as $code) {
                $code = is_int($code) ? (string) $code : $code;
                if (is_string($code) && preg_match('/\A[0-9]{1,12}\z/D', $code)) {
                    $safeErrorCodes[] = $code;
                }
            }
            $safeErrorCodes = array_slice(array_values(array_unique($safeErrorCodes)), 0, 5);
        }

        $timestampForLog = $metaTimestamp ?? 'unknown';
        $codesForLog = $status === 'failed' && $safeErrorCodes !== []
            ? implode(',', $safeErrorCodes)
            : 'none';

        // Production's configured CI logger threshold is 4; use error level so these
        // operational status events are visible there. The message is explicitly an
        // event record, and contains no phone, OTP, token, payload, or secret.
        log_message(
            'error',
            'WhatsApp delivery status event: wamid={wamid} status={status} meta_timestamp={meta_timestamp} error_codes={error_codes}.',
            [
                'wamid' => $wamid,
                'status' => $status,
                'meta_timestamp' => $timestampForLog,
                'error_codes' => $codesForLog,
            ]
        );

        $record = [
            'received_at' => date(DATE_ATOM),
            'wamid' => $wamid,
            'status' => $status,
            'meta_timestamp' => $metaTimestamp,
            'error_codes' => $safeErrorCodes,
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false || file_put_contents(WRITEPATH . 'logs/whatsapp-delivery-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            log_message('error', 'WhatsApp delivery event could not be written to its dedicated log.');
        }
    }
}
