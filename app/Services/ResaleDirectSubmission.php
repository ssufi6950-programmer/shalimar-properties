<?php

namespace App\Services;

use App\Exceptions\CustomerFlowException;
use App\Libraries\CustomerSecrets;
use CodeIgniter\Database\BaseConnection;

/** A signed-in customer's submission requires fresh booking ownership, without issuing an OTP. */
final class ResaleDirectSubmission
{
    public function __construct(private BaseConnection $db, private CurrentBookingProvider $booking)
    {
    }

    public function submit(array $user, array $property, array $identity, array $input, string $key,
        string $binding, string $ip, ?array $context = null): array
    {
        $customer = (int) $user['id'];
        if ((int) $identity['erp_source_id'] !== $property['erp_source_id']
            || (string) $identity['erp_customer_id'] !== $property['erp_customer_id']
            || !$this->db->table('customer_erp_links')->where('portal_customer_id', $customer)
                ->where('erp_source_id', $property['erp_source_id'])
                ->where('erp_customer_id', $property['erp_customer_id'])->countAllResults()) {
            throw new CustomerFlowException(403, 'Choose a property from your verified account.');
        }

        $submission = new ResaleSubmission($this->db);
        $lock = 'resale_submit_' . substr(hash('sha256', $customer . ':' . $key), 0, 45);
        if ((int) $this->db->query('SELECT GET_LOCK(?,0) AS acquired', [$lock])->getRow()->acquired !== 1) {
            throw new CustomerFlowException(429, 'Submission is already in progress. Please wait.', 'busy');
        }

        try {
            if ($existing = $submission->existing($key, $customer)) {
                return $existing;
            }
            $secrets = new CustomerSecrets(config('CustomerPortal')->identityKey);
            (new CustomerRateLimiter($this->db, $secrets))->consume([
                'resale_submit_customer' => [(string) $customer, 5],
                'resale_submit_ip' => [$ip, 10],
            ], time());

            $draft = $submission->validateDraft($input);
            $target = $context ? (new ListingLifecycle($this->db))->owned($customer, (int) $context['listing_id']) : null;
            (new ResalePhotos($this->db))->selection($customer, $draft['photos'], $key, $target, $binding);
            $submission->preview($user, $property, $draft, $target);

            try {
                $proof = CurrentBookingApi::validate(
                    ['success' => true, 'data' => $this->booking->verify($identity, $property['erp_property_id'], $property['booking'])],
                    $property['erp_property_id'], $property['erp_customer_id'], $property['booking']
                );
            } catch (\Throwable $error) {
                throw new CustomerFlowException(503, 'Current booking ownership could not be verified. Please try again later.', 'booking_verification_unavailable');
            }

            if (!$proof['resale_eligible'] || $proof['head_id'] !== $property['erp_head_id']
                || $proof['subhead_id'] !== $property['erp_subhead_id']) {
                throw new CustomerFlowException(409, 'This property is no longer eligible. Refresh your properties.', 'booking_not_eligible');
            }
            if (($property['listing_purpose'] ?? 'sale') === 'rent'
                && !ErpPropertyClassification::rentEligible(array_merge($property, ErpPropertyClassification::fromApi($proof)))) {
                throw new CustomerFlowException(409, ErpPropertyClassification::RENT_HOUSE_ONLY, 'rent_type_ineligible');
            }

            return $submission->submitWithOwnership($user, $property, $draft, $key, $proof, $context);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
}
