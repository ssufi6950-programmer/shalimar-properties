<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CurrentBookingApi;
use App\Services\ShalimarAgents;

/** Authenticated, read-only lookup of an owner listing and its source-specific representative. */
final class CustomerBookingVerificationApi extends BaseController
{
    public function verify()
    {
        $response = $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff');

        try {
            if (! $this->request->is('post')) {
                return $response->setStatusCode(405)->setHeader('Allow', 'POST')->setJSON([
                    'success' => false,
                    'error' => ['code' => 'method_not_allowed', 'message' => 'Use POST for this endpoint.'],
                ]);
            }

            $expected = (string) env('customerBookingApi.token', '');
            $authorization = trim($this->request->getHeaderLine('Authorization'));
            if ($authorization === '') $authorization = trim($this->request->getServer('HTTP_AUTHORIZATION') ?? '');
            if ($expected === '' || strlen($expected) < 32
                || ! preg_match('/\ABearer ([A-Za-z0-9._~-]{32,512})\z/D', $authorization, $match)
                || ! hash_equals($expected, $match[1])) {
                return $response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'error' => ['code' => 'unauthorized', 'message' => 'API authentication failed.'],
                ]);
            }

            $contentType = $this->request->getHeaderLine('Content-Type');
            if ($contentType === '') $contentType = (string) ($this->request->getServer('CONTENT_TYPE') ?? '');
            if (strpos(strtolower($contentType), 'application/json') === false) {
                return $this->failure('invalid_request', 'Send the request as JSON.', 415);
            }
            try {
                $input = $this->request->getJSON(true, 8);
            } catch (\Throwable $error) {
                return $this->failure('invalid_request', 'Send a valid JSON object.', 400);
            }
            $isList = is_array($input) && ($input === [] || array_keys($input) === range(0, count($input) - 1));
            if (! is_array($input) || $isList
                || array_diff(array_keys($input), ['customer_id', 'property_id', 'booking_id', 'source'])) {
                return $this->failure('invalid_request', 'Send customer_id, property_id, booking_id and source.', 400);
            }
            foreach (['customer_id', 'property_id', 'booking_id'] as $field) {
                if ((! is_string($input[$field] ?? null) && ! is_int($input[$field] ?? null))
                    || ! preg_match('/\A[1-9][0-9]{0,17}\z/D', (string) $input[$field])) {
                    return $this->failure('invalid_request', 'All supplied IDs must be positive numeric identifiers.', 400);
                }
            }
            $sourceCode = $input['source'] ?? null;
            if (! is_string($sourceCode) || ! in_array($sourceCode, ['SGC', 'ZALMI'], true)) {
                return $this->failure('unsupported_source', 'source must be SGC or ZALMI.', 400);
            }

            $db = db_connect();
            $customerId = (string) $input['customer_id'];
            $propertyId = (string) $input['property_id'];
            $bookingId = (string) $input['booking_id'];
            // The calling ERP knows its own customer ID, not the Portal's
            // internal primary key. Resolve it through the source-qualified link.
            $identity = $db->query(
                "SELECT e.portal_customer_id,e.erp_customer_id,e.erp_source_id
                 FROM customer_erp_links e
                 JOIN portal_customers c ON c.id=e.portal_customer_id AND c.status='active'
                 JOIN erp_sources s ON s.id=e.erp_source_id AND s.is_active=1
                 WHERE e.erp_customer_id=? AND s.code=? LIMIT 1",
                [$customerId, $sourceCode]
            )->getRowArray();
            if (! $identity) {
                return $this->failure('booking_mismatch', 'No active Portal customer identity matches this ERP customer and source.', 404);
            }
            $portalCustomerId = (int) $identity['portal_customer_id'];
            $sourceId = (int) $identity['erp_source_id'];
            $listings = $db->query(
                "SELECT l.id,l.portal_customer_id,l.erp_source_id,l.erp_property_id,l.selling_method,
                        l.portal_agent_id,l.agent_erp_link_id,l.agent_source_id,
                        l.portal_agency_id,l.agency_erp_link_id,l.owner_verification,s.code AS source_code
                 FROM portal_listings l JOIN erp_sources s ON s.id=l.erp_source_id
                 WHERE l.portal_customer_id=? AND l.erp_property_id=? AND s.code=?
                   AND l.deal IN ('available','active')
                   AND l.status NOT IN ('sold','cancelled','rejected','unlisted')
                 ORDER BY l.created_at DESC,l.id DESC LIMIT 2",
                [$portalCustomerId, $propertyId, $sourceCode]
            )->getResultArray();
            if (count($listings) === 0) {
                return $this->failure('booking_mismatch', 'No single active Portal listing matches this customer, property and ERP source.', 404);
            }
            if (count($listings) > 1) {
                return $this->failure('ambiguous_listing', 'More than one active listing matches this property. The caller must identify which listing it is checking.', 409);
            }
            $listing = $listings[0];
            $savedProof = json_decode((string) ($listing['owner_verification'] ?? ''), true);
            if (! is_array($savedProof)
                || (string) ($savedProof['property_ref'] ?? '') !== $propertyId
                || (string) ($savedProof['current_booking_ref'] ?? '') !== $bookingId
                || (string) ($savedProof['current_owner_customer_id'] ?? '') !== $customerId
                || (string) $listing['portal_customer_id'] !== (string) $portalCustomerId
                || (string) $listing['erp_source_id'] !== (string) $sourceId) {
                return $this->failure('booking_mismatch', 'The customer, property and booking do not match the Portal ownership submission for this ERP source.', 404);
            }

            try {
                $proof = (new CurrentBookingApi(config('CustomerPortal'), $db))->verify(
                    ['erp_source_id' => $sourceId, 'erp_customer_id' => (string) $identity['erp_customer_id']],
                    $propertyId,
                    $bookingId
                );
            } catch (\Throwable $error) {
                log_message('error', 'Customer booking API ERP verification failed: source={source} type={type}.', [
                    'source' => $sourceCode,
                    'type' => get_class($error),
                ]);
                return $this->failure('erp_verification_unavailable', 'The selected ERP could not confirm this booking right now.', 503);
            }

            $representative = $this->representative($db, $listing, $sourceId);
            if ($representative === null) {
                return $this->failure('representative_mapping_missing', 'The listing has no complete representative mapping for the requested ERP source.', 409);
            }
            return $response->setJSON([
                'success' => true,
                'verified' => true,
                'source' => $sourceCode,
                'customer_id' => $customerId,
                'portal_customer_id' => (string) $portalCustomerId,
                'property_id' => $propertyId,
                'booking_id' => $proof['current_booking_ref'],
                'selling_method' => $listing['selling_method'],
                'representative' => $representative,
            ]);
        } catch (\Throwable $error) {
            log_message('error', 'Customer booking API unavailable: type={type}.', ['type' => get_class($error)]);
            return $this->failure('service_unavailable', 'Booking verification is temporarily unavailable.', 503);
        }
    }

    private function representative(\CodeIgniter\Database\BaseConnection $db, array $listing, int $sourceId): ?array
    {
        if ($listing['selling_method'] === 'shalimar_agent') {
            if ((int) $listing['agent_source_id'] !== $sourceId || ! $listing['portal_agent_id'] || ! $listing['agent_erp_link_id']) return null;
            $agent = $db->query(ShalimarAgents::eligibleSql() . ' AND a.id=? AND l.id=? AND l.erp_source_id=? LIMIT 1',
                [$listing['portal_agent_id'], $listing['agent_erp_link_id'], $sourceId])->getRowArray();
            if (! $agent) return null;
            return [
                'type' => 'shalimar_agent',
                'portal_agent_id' => (string) $agent['portal_agent_id'],
                'erp_agent_link_id' => (string) $agent['agent_erp_link_id'],
                'erp_ledger_id' => (string) $agent['erp_ledger_id'],
                'erp_dealer_id' => $agent['erp_dealer_id'] === null ? null : (string) $agent['erp_dealer_id'],
                'erp_agency_id' => (string) $agent['erp_agency_id'],
            ];
        }
        if ($listing['selling_method'] === 'authorized_agency') {
            if (! $listing['portal_agency_id'] || ! $listing['agency_erp_link_id']) return null;
            $agency = $db->query(
                "SELECT a.id AS portal_agency_id,l.id AS agency_link_id,l.erp_source_id,l.erp_agency_id
                 FROM authorized_agencies a JOIN agency_erp_links l ON l.portal_agency_id=a.id
                 WHERE a.id=? AND l.id=? AND l.erp_source_id=? AND l.mapping_status='mapped' AND l.present=1 LIMIT 1",
                [$listing['portal_agency_id'], $listing['agency_erp_link_id'], $sourceId]
            )->getRowArray();
            if (! $agency) return null;
            // A source agency can have many dealers. Return only the currently
            // assigned dealer for this listing, after reapplying the portal's
            // effective assignment policy.
            $assignment = (new \App\Services\AdminListingDealerAssignment($db))->detail((int) $listing['id']);
            $assigned = $assignment['assignment'] ?? null;
            if (! $assigned || ($assigned['source_code'] ?? null) !== ($listing['source_code'] ?? null)) return null;
            $dealer = $db->query(
                "SELECT d.id AS portal_dealer_id,d.erp_ledger_id
                 FROM agency_dealer_memberships m
                 JOIN agency_dealers d ON d.id=m.agency_dealer_id AND d.erp_source_id=m.erp_source_id
                 WHERE m.id=? AND m.portal_agency_id=? AND m.agency_erp_link_id=?
                   AND m.erp_source_id=? AND m.erp_agency_id=?
                   AND m.membership_status='current' AND d.present=1 LIMIT 1",
                [$assigned['membership_id'], $agency['portal_agency_id'], $agency['agency_link_id'], $sourceId, $agency['erp_agency_id']]
            )->getRowArray();
            if (! $dealer) return null;
            return [
                'type' => 'agency',
                'portal_agency_id' => (string) $agency['portal_agency_id'],
                'erp_agency_link_id' => (string) $agency['agency_link_id'],
                'erp_agency_id' => (string) $agency['erp_agency_id'],
                'portal_dealer_id' => (string) $dealer['portal_dealer_id'],
                'erp_dealer_ledger_id' => (string) $dealer['erp_ledger_id'],
            ];
        }
        if ($listing['selling_method'] === 'self_sell') return ['type' => 'self_sell'];
        return null;
    }

    private function failure(string $code, string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store, private')->setJSON([
            'success' => false,
            'verified' => false,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
