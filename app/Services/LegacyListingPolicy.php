<?php
namespace App\Services;

/** Legacy catalog writers cannot replace customer-specific publication authorization. */
final class LegacyListingPolicy
{
    public const MESSAGE = 'Legacy publication and reactivation are disabled. New listings require current-booking OTP, final ownership verification and admin review. Use the protected Admin Property Listings review workflow for approval. Unsafe legacy approval routes remain disabled.';

    public static function blocks(string $action): bool
    {
        return in_array($action, ['approve', 'reopen', 'reactivate', 'republish'], true);
    }

    public static function assertCatalogUpdate(?array $previous, array $next): void
    {
        // The legacy database status columns use a case-insensitive, space-padding collation.
        $state = static fn($value): string => strtolower(rtrim((string)$value));
        $active = static fn(array $row): bool => in_array($state($row['status'] ?? ''), ['live', 'approved', 'active'], true)
            && in_array($state($row['deal'] ?? 'available'), ['available', 'active'], true);
        if ($previous && (($next['_listing_purpose'] ?? ($previous['listing_purpose'] ?? null)) !== ($previous['listing_purpose'] ?? null)
            || (($next['purpose'] ?? '') === 'rent') !== (($previous['listing_purpose'] ?? null) === 'rent'))) {
            throw new \InvalidArgumentException('An existing listing purpose cannot be changed through the legacy catalog.');
        }
        if ($active($next) && (!$previous || !$active($previous) || ($previous['review_state'] ?? null) !== 'approved')) {
            throw new \InvalidArgumentException(self::MESSAGE);
        }
    }
}
