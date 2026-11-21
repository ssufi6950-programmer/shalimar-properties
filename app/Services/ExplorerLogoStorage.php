<?php
declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;

final class ExplorerLogoStorage
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function url(?string $reference): string
    {
        if (!is_string($reference) || !preg_match('/\Aexplorer-logo:([a-f0-9]{64})\.(?:jpg|png|webp)\z/D', $reference, $match)) return '';
        return \App\Libraries\PortalLinks::portal('api/property-explorer/images/' . $match[1]);
    }

    public static function save(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Choose a JPEG, PNG or WebP logo up to 5 MB.');
        }
        $raw = file_get_contents($file->getTempName());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw ?: '');
        if (!isset(self::TYPES[$mime])) throw new InvalidArgumentException('Choose a JPEG, PNG or WebP logo.');
        $size = @getimagesizefromstring($raw ?: '');
        if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 20000000) {
            throw new InvalidArgumentException('The logo image is invalid or too large.');
        }
        $directory = WRITEPATH . 'uploads/explorer-logos/';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Logo storage is unavailable.');
        }
        $id = bin2hex(random_bytes(32));
        $extension = self::TYPES[$mime];
        $path = $directory . $id . '.' . $extension;
        try {
            if (file_put_contents($path, $raw) === false) throw new \RuntimeException('The logo could not be saved.');
            return 'explorer-logo:' . $id . '.' . $extension;
        } catch (\Throwable $error) {
            if (is_file($path)) @unlink($path);
            throw $error;
        }
    }

    public static function delete(?string $reference): void
    {
        if (!is_string($reference) || !preg_match('/\Aexplorer-logo:([a-f0-9]{64})\.(jpg|png|webp)\z/D', $reference, $match)) return;
        $path = WRITEPATH . 'uploads/explorer-logos/' . $match[1] . '.' . $match[2];
        if (is_file($path)) @unlink($path);
    }

    public static function serve(string $id): ?array
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $id)) return null;
        foreach (self::TYPES as $mime => $extension) {
            $path = WRITEPATH . 'uploads/explorer-logos/' . $id . '.' . $extension;
            if (is_file($path)) return ['path' => $path, 'mime' => $mime];
        }
        return null;
    }
}
