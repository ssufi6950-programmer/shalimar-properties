<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminListingDealerAssignment;
use App\Services\ListingLifecycle;
use App\Services\ListingPolicy;
use App\Services\ShalimarAgents;
use CodeIgniter\Database\BaseConnection;

/** Authenticated callback from an ERP after a resale booking has committed. */
final class CustomerResaleCompletionApi extends BaseController
{
    public function complete()
    {
        $response = $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff');

        try {
            if (! $this->request->is('post')) {
                return $response->setStatusCode(405)->setHeader('Allow', 'POST')->setJSON([
                    'success' => false, 'error' => ['code' => 'method_not_allowed'],
                ]);
            }
            $expected = (string) env('customerBookingApi.token', '');
            $authorization = trim($this->request->getHeaderLine('Authorization'));
            if ($authorization === '') $authorization = trim((string) $this->request->getServer('HTTP_AUTHORIZATION'));
            if ($expected === '' || strlen($expected) < 32
                || ! preg_match('/\ABearer ([A-Za-z0-9._~-]{32,512})\z/D', $authorization, $match)
                || ! hash_equals($expected, $match[1])) {
                return $this->failure('unauthorized', 401);
            }
            $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
            if (! str_contains($contentType, 'application/json')) return $this->failure('invalid_request', 415);
            try { $input = $this->request->getJSON(true, 8); }
            catch (\Throwable) { return $this->failure('invalid_request', 400); }
            $allowed = ['source', 'property_id', 'previous_booking_id', 'new_booking_id', 'seller_agency_id', 'seller_dealer_id', 'occurred_at'];
            if (! is_array($input) || array_is_list($input) || array_diff(array_keys($input), $allowed)) return $this->failure('invalid_request', 400);
            foreach (['property_id', 'previous_booking_id', 'new_booking_id', 'occurred_at'] as $field) {
                if ((! is_string($input[$field] ?? null) && ! is_int($input[$field] ?? null))
                    || ! preg_match('/\A[1-9][0-9]{0,17}\z/D', (string) $input[$field])) return $this->failure('invalid_request', 400);
            }
            if ((string) $input['previous_booking_id'] === (string) $input['new_booking_id']) return $this->failure('invalid_request', 400);
            if (! in_array($input['source'] ?? null, ['SGC', 'ZALMI'], true)) return $this->failure('unsupported_source', 400);
            $newAgency = $this->optionalId($input['seller_agency_id'] ?? null);
            $newDealer = $this->optionalId($input['seller_dealer_id'] ?? null);

            $db = db_connect();
            $source = (string) $input['source'];
            $sourceRow = $db->table('erp_sources')->select('id')->where('code', $source)->where('is_active', 1)->get()->getRowArray();
            if (! $sourceRow) return $this->failure('source_unavailable', 503);
            $sourceId = (int) $sourceRow['id'];
            $propertyId = (string) $input['property_id'];
            $previousBookingId = (string) $input['previous_booking_id'];
            $newBookingId = (string) $input['new_booking_id'];
            $eventKey = hash('sha256', implode('|', [$source, $propertyId, $previousBookingId, $newBookingId]));

            $db->transBegin();
            try {
                $already = $db->query('SELECT listing_id,event FROM listing_events WHERE authorization_id=? FOR UPDATE', [$eventKey])->getRowArray();
                if ($already) {
                    if (! in_array($already['event'], ['erp_resale_sold', 'erp_resale_superseded'], true)) throw new \RuntimeException('event_key_conflict');
                    $db->transCommit();
                    return $response->setJSON(['success' => true, 'already_processed' => true, 'outcome' => $already['event'] === 'erp_resale_sold' ? 'sold' : 'superseded']);
                }

                $listingSql = "SELECT l.*,s.code AS source_code FROM portal_listings l
                    JOIN erp_sources s ON s.id=l.erp_source_id AND s.is_active=1
                    JOIN customer_erp_links e ON e.portal_customer_id=l.portal_customer_id AND e.erp_source_id=l.erp_source_id
                      AND e.erp_customer_id=JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(l.owner_verification) THEN l.owner_verification ELSE '{}' END,'$.current_owner_customer_id'))
                    WHERE l.erp_property_id=? AND l.erp_source_id=?
                      AND JSON_VALID(l.owner_verification)
                      AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(l.owner_verification) THEN l.owner_verification ELSE '{}' END,'$.property_ref'))=?
                      AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(l.owner_verification) THEN l.owner_verification ELSE '{}' END,'$.current_booking_ref'))=?
                      AND " . ListingPolicy::publicSql('l') . " LIMIT 2 FOR UPDATE";
                $listings = $db->query($listingSql, [$propertyId, $sourceId, $propertyId, $previousBookingId])->getResultArray();
                if (count($listings) === 0) {
                    // A concurrent identical callback may have committed while this request waited for the listing lock.
                    $already = $db->query('SELECT listing_id,event FROM listing_events WHERE authorization_id=? FOR UPDATE', [$eventKey])->getRowArray();
                    if ($already && in_array($already['event'], ['erp_resale_sold', 'erp_resale_superseded'], true)) {
                        $db->transCommit();
                        return $response->setJSON(['success' => true, 'already_processed' => true, 'outcome' => $already['event'] === 'erp_resale_sold' ? 'sold' : 'superseded']);
                    }
                }
                if (count($listings) !== 1) throw new ApiFailure(count($listings) > 1 ? 'ambiguous_listing' : 'listing_not_found', count($listings) > 1 ? 409 : 404);
                $listing = $listings[0];
                $oldRepresentative = $this->representative($db, $listing, $sourceId);
                if ($oldRepresentative === null) throw new ApiFailure('representative_unavailable', 409);

                $oldAgency = $oldRepresentative['agency_id'];
                $oldDealer = $oldRepresentative['dealer_id'];
                $sameRepresentative = ($oldAgency === null && $oldDealer === null && $newAgency === null && $newDealer === null)
                    || ($oldAgency !== null && $oldDealer !== null && $newAgency !== null && $newDealer !== null
                        && hash_equals($oldAgency, $newAgency) && hash_equals($oldDealer, $newDealer));
                $now = date('Y-m-d H:i:s', (int) $input['occurred_at']);
                $outcome = $sameRepresentative ? 'sold' : 'superseded';
                $status = $sameRepresentative ? 'sold' : 'unlisted';
                $deal = $sameRepresentative ? 'sold' : (string) $listing['deal'];
                $payload = json_decode((string) $listing['payload'], true, 128, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) throw new \RuntimeException('invalid_listing_payload');
                $payload['status'] = $status;
                $payload['deal'] = $deal;
                $payload['resale_outcome'] = $outcome;
                $payload['resale_outcome_at'] = $now;
                $payload['resale_source'] = $source;
                $payload['resale_previous_booking_id'] = $previousBookingId;
                $payload['resale_new_booking_id'] = $newBookingId;
                $payload['resale_selected_agency_id'] = $newAgency;
                $payload['resale_selected_dealer_id'] = $newDealer;
                $values = ['status' => $status, 'deal' => $deal, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'record_version' => (int) $listing['record_version'] + 1, 'updated_at' => $now];
                $db->table('portal_listings')->where('id', $listing['id'])->where('record_version', $listing['record_version'])->update($values);
                if ($db->affectedRows() !== 1) throw new ApiFailure('listing_changed', 409);
                $next = array_replace($listing, $values);
                (new ListingLifecycle($db))->record($sameRepresentative ? 'erp_resale_sold' : 'erp_resale_superseded', $listing, $next, null, null, $eventKey);
                if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('commit_failed');
                return $response->setJSON(['success' => true, 'already_processed' => false, 'outcome' => $outcome, 'listing_id' => (string) $listing['id'], 'occurred_at' => $now]);
            } catch (\Throwable $error) {
                $db->transRollback();
                $db->resetTransStatus();
                if ($error instanceof ApiFailure) return $this->failure($error->apiCode, $error->httpStatus);
                if ($error instanceof \RuntimeException && $error->getMessage() === 'event_key_conflict') return $this->failure('idempotency_conflict', 409);
                log_message('error', 'ERP resale completion callback failed: source={source} type={type}.', ['source' => $source, 'type' => get_class($error)]);
                return $this->failure('service_unavailable', 503);
            }
        } catch (\Throwable $error) {
            log_message('error', 'ERP resale completion endpoint failed: type={type}.', ['type' => get_class($error)]);
            return $this->failure('service_unavailable', 503);
        }
    }

    private function representative(BaseConnection $db, array $listing, int $sourceId): ?array
    {
        if ($listing['selling_method'] === 'shalimar_agent') {
            if ((int) $listing['agent_source_id'] !== $sourceId || ! $listing['portal_agent_id'] || ! $listing['agent_erp_link_id']) return null;
            $agent = $db->query(ShalimarAgents::eligibleSql() . ' AND a.id=? AND l.id=? AND l.erp_source_id=? LIMIT 1', [$listing['portal_agent_id'], $listing['agent_erp_link_id'], $sourceId])->getRowArray();
            if (! $agent) return null;
            return ['agency_id' => (string) $agent['erp_agency_id'], 'dealer_id' => (string) $agent['erp_ledger_id']];
        }
        if ($listing['selling_method'] === 'authorized_agency') {
            if (! $listing['portal_agency_id'] || ! $listing['agency_erp_link_id']) return null;
            $agency = $db->query("SELECT a.id AS portal_agency_id,l.id AS agency_link_id,l.erp_source_id,l.erp_agency_id
                FROM authorized_agencies a JOIN agency_erp_links l ON l.portal_agency_id=a.id
                WHERE a.id=? AND l.id=? AND l.erp_source_id=? AND l.mapping_status='mapped' AND l.present=1 LIMIT 1",
                [$listing['portal_agency_id'], $listing['agency_erp_link_id'], $sourceId])->getRowArray();
            if (! $agency) return null;
            $assignment = (new AdminListingDealerAssignment($db))->detail((int) $listing['id']);
            $assigned = $assignment['assignment'] ?? null;
            if (! $assigned || ($assigned['source_code'] ?? null) !== ($listing['source_code'] ?? null)) return null;
            $dealer = $db->query("SELECT d.erp_ledger_id FROM agency_dealer_memberships m
                JOIN agency_dealers d ON d.id=m.agency_dealer_id AND d.erp_source_id=m.erp_source_id
                WHERE m.id=? AND m.portal_agency_id=? AND m.agency_erp_link_id=? AND m.erp_source_id=? AND m.erp_agency_id=?
                  AND m.membership_status='current' AND d.present=1 LIMIT 1",
                [$assigned['membership_id'], $agency['portal_agency_id'], $agency['agency_link_id'], $sourceId, $agency['erp_agency_id']])->getRowArray();
            if (! $dealer) return null;
            return ['agency_id' => (string) $agency['erp_agency_id'], 'dealer_id' => (string) $dealer['erp_ledger_id']];
        }
        if ($listing['selling_method'] === 'self_sell') return ['agency_id' => null, 'dealer_id' => null];
        return null;
    }

    private function optionalId($value): ?string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') return null;
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A[1-9][0-9]{0,17}\z/D', (string) $value)) throw new ApiFailure('invalid_request', 400);
        return (string) $value;
    }

    private function failure(string $code, int $status)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store, private')->setJSON(['success' => false, 'error' => ['code' => $code]]);
    }
}

final class ApiFailure extends \RuntimeException
{
    public function __construct(public readonly string $apiCode, public readonly int $httpStatus) { parent::__construct($apiCode); }
}
