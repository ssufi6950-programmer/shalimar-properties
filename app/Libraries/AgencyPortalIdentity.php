<?php
namespace App\Libraries;

final class AgencyPortalIdentity
{
    public static function phone(string $value): ?string
    {
        try { return CustomerIdentity::mobile($value); }
        catch (\InvalidArgumentException $e) { return null; }
    }

    public static function custom(string $value): string
    {
        $value = trim($value);
        if (strlen($value) < 3 || strlen($value) > 50 || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $value)) {
            throw new \DomainException('Use 3–50 letters, numbers, dots, underscores or hyphens for the username.');
        }
        return $value;
    }

    public static function normalized(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 100) return 'invalid:' . hash('sha256', $value);
        if (($phone = self::phone($value)) !== null) return 'phone:' . $phone;
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{2,49}\z/D', $value)) return 'user:' . strtolower($value);
        return 'invalid:' . hash('sha256', $value);
    }
}
