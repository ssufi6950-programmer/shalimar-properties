<?php
namespace App\Services;

/** Safe public summary of completed publication proof; never returns private proof fields. */
final class PublicationVerification
{
    public const LABEL = 'Ownership verified at publication';

    public static function summary(array $row): ?array
    {
        $payload=json_decode($row['payload']??'{}',true);
        if(is_array($payload)&&($payload['approval_mode']??null)==='manual')return null;
        return self::submission($row);
    }

    /** Completed customer proof, separate from a public claim about admin publication. */
    public static function submission(array $row): ?array
    {
        $proof = json_decode($row['owner_verification'] ?? '', true);
        if (is_array($proof) && ($proof['verification_method'] ?? null) === 'current_booking') {
            return self::currentBookingSubmission($row, $proof);
        }
        if ((int) ($row['portal_customer_id'] ?? 0) < 1
            || !is_string($row['resale_verification_id'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $row['resale_verification_id'])
            || (string) $row['portal_customer_id'] !== (string) ($row['otp_customer_id'] ?? '')
            || ($row['otp_delivery_state'] ?? null) !== 'sent'
            || (int) ($row['otp_verified_at'] ?? 0) < 1
            || (int) ($row['otp_consumed_at'] ?? 0) < (int) $row['otp_verified_at']
            || !is_string($row['submission_key'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $row['submission_key'])
            || !hash_equals($row['submission_key'], (string) ($row['otp_submission_key'] ?? ''))
        ) return null;

        $proof = json_decode($row['owner_verification'] ?? '', true);
        if (!is_array($proof)
            || ($proof['property_ref'] ?? null) !== (string) ($row['erp_property_id'] ?? '')
            || ($proof['ownership_match'] ?? null) !== true
            || ($proof['current_booking'] ?? null) !== true
            || ($proof['booking_status'] ?? null) !== 'active'
            || ($proof['resale_eligible'] ?? null) !== true
            || ($proof['head_smp_active'] ?? null) !== 'yes'
            || ($proof['subhead_smp_active'] ?? null) !== 'yes'
            || !is_string($proof['registered_mobile_hash'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $proof['registered_mobile_hash'])
            || !is_string($proof['verified_at'] ?? null)
        ) return null;

        try { $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $proof['verified_at']); }
        catch (\ValueError $e) { return null; }
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || abs($date->getTimestamp() - (int) $row['otp_consumed_at']) > 90
            || $date->getTimestamp() > time() + 90
        ) return null;

        $date = $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
        return ['label' => self::LABEL, 'datetime' => $date->format(DATE_ATOM), 'date' => $date->format('d M Y, g:i A').' PKT'];
    }

    /** A real server booking check is distinct from a sent/verified SMS challenge. */
    private static function currentBookingSubmission(array $row, array $proof): ?array
    {
        if ((int) ($row['portal_customer_id'] ?? 0) < 1
            || ($row['resale_verification_id'] ?? null) !== null
            || ($proof['portal_customer_id'] ?? null) !== (int) $row['portal_customer_id']
            || ($proof['erp_source_id'] ?? null) !== (int) ($row['erp_source_id'] ?? 0)
            || !is_string($row['submission_key'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $row['submission_key'])
            || ($proof['submission_key'] ?? null) !== $row['submission_key']
            || ($proof['property_ref'] ?? null) !== (string) ($row['erp_property_id'] ?? '')
            || !is_string($proof['current_booking_ref'] ?? null)
            || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $proof['current_booking_ref'])
            || !is_string($proof['current_owner_customer_id'] ?? null)
            || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $proof['current_owner_customer_id'])
            || ($proof['ownership_match'] ?? null) !== true
            || ($proof['current_booking'] ?? null) !== true
            || ($proof['booking_status'] ?? null) !== 'active'
            || ($proof['resale_eligible'] ?? null) !== true
            || ($proof['head_smp_active'] ?? null) !== 'yes'
            || ($proof['subhead_smp_active'] ?? null) !== 'yes'
            || array_key_exists('registered_mobile', $proof)
            || !is_string($proof['registered_mobile_hash'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $proof['registered_mobile_hash'])
            || !is_int($proof['submission_verified_at'] ?? null)
            || $proof['submission_verified_at'] <= 0
            || !is_string($proof['verified_at'] ?? null)) {
            return null;
        }
        try {
            $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $proof['verified_at']);
        } catch (\ValueError $error) {
            return null;
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || abs($date->getTimestamp() - $proof['submission_verified_at']) > 90
            || $date->getTimestamp() > time() + 90) {
            return null;
        }
        $date = $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
        return ['label' => 'Ownership checked at submission', 'datetime' => $date->format(DATE_ATOM), 'date' => $date->format('d M Y, g:i A') . ' PKT'];
    }
}
