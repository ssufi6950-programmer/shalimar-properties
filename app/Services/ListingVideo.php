<?php
declare(strict_types=1);
namespace App\Services;

/** Only a YouTube identifier is stored; no remote fetching or arbitrary embeds. */
final class ListingVideo
{
    public static function id(mixed $url): string
    {
        if (!is_string($url) || strlen($url) > 1000) throw new \InvalidArgumentException('Enter a valid YouTube video link.');
        $url = trim($url);
        if ($url === '') return '';
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new \InvalidArgumentException('Enter a valid YouTube video link.');
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $id = '';
        if ($host === 'youtu.be') $id = substr($path, 1);
        elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {parse_str($parts['query'] ?? '', $query); $id = $query['v'] ?? '';}
            elseif (preg_match('~\A/(?:shorts|embed|live)/([^/]+)/?\z~D', $path, $match)) $id = $match[1];
        }
        if (!self::valid($id)) throw new \InvalidArgumentException('Enter a YouTube watch, Shorts or share link.');
        return $id;
    }

    public static function valid(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{11}\z/D', $id) === 1;
    }

    public static function embed(mixed $id): string
    {
        return self::valid($id) ? 'https://www.youtube-nocookie.com/embed/' . $id : '';
    }
}
