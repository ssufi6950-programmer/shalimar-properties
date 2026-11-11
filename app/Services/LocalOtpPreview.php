<?php
namespace App\Services;

/** Session-bound temporary test preview; all-host mode requires an explicit configuration flag. */
final class LocalOtpPreview
{
    public static function allowed(bool $enabled, string $baseURL, array $server, bool $allHosts = false): bool
    {
        return $enabled
            && ($allHosts || (
            in_array(strtolower((string) parse_url($baseURL, PHP_URL_HOST)), ['localhost', '127.0.0.1', '[::1]', '::1'], true)
            && in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)
            && preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?\z/Di', $server['HTTP_HOST'] ?? '') === 1));
    }

    private static function enabled(): bool
    {
        $settings = config('CustomerPortal');
        return self::allowed($settings->showLocalOtp || $settings->showOtpPreview, config('App')->baseURL, $_SERVER, $settings->showOtpPreview);
    }

    public static function remember(string $purpose, string $id, string $otp, int $expires, string $note = ''): void
    {
        if (!self::enabled()) return;
        session()->set('local_otp_'.$purpose, ['id' => $id, 'code' => $otp, 'expires' => $expires, 'note' => $note]);
    }

    public static function fields(string $purpose, string $id): array
    {
        if (!self::enabled()) {
            session()->remove('local_otp_'.$purpose);
            return [];
        }
        $preview = session()->get('local_otp_'.$purpose);
        if (!is_array($preview) || ($preview['id'] ?? null) !== $id) return [];
        if (($preview['expires'] ?? 0) <= time()) {
            self::forget($purpose, $id);
            return [];
        }
        return ['local_test_otp' => $preview['code'], 'local_test_otp_note' => $preview['note'] ?? ''];
    }

    public static function forget(string $purpose, string $id): void
    {
        if ((session()->get('local_otp_'.$purpose)['id'] ?? null) === $id) session()->remove('local_otp_'.$purpose);
    }
}
