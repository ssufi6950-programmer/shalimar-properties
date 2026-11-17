<?php

namespace App\Services;

/** Trusted presentation assets, snapshotted with every saved editor version. */
final class ProjectPageAssets
{
    public static function snapshot(string $html, array $document): array
    {
        $css = file_get_contents(ROOTPATH . 'public/assets/projects/project-page.css');
        $js = file_get_contents(ROOTPATH . 'public/assets/projects/project-page.js');

        if ($css === false || $js === false) {
            throw new \RuntimeException('Project page assets could not be loaded.');
        }

        $title = htmlspecialchars($document['name'], ENT_QUOTES, 'UTF-8');
        $page = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title><style>' . $css . '</style></head>'
            . '<body><main class="smp-project-page">' . $html . '</main>'
            . '<script>' . $js . '</script></body></html>';

        return [
            'asset_version' => 1,
            'css' => $css,
            'js' => $js,
            'page_html' => $page,
        ];
    }

    public static function publicImageUrl(string $reference): string
    {
        if (str_starts_with($reference, 'https://')) {
            return $reference;
        }

        if (str_starts_with($reference, 'project-image:')) {
            return site_url('api/projects/images/' . substr($reference, 14));
        }

        return $reference === '' ? '' : \App\Libraries\PortalLinks::publicSite($reference);
    }

    public static function publicLink(string $reference): string
    {
        if ($reference === '' || str_starts_with($reference, '#') || str_starts_with($reference, 'https://')) {
            return $reference;
        }

        return \App\Libraries\PortalLinks::publicSite($reference);
    }
}
