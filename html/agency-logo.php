<?php
declare(strict_types=1);

// Static cached agency artwork only. No database, session, credentials or upstream request.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/\A[a-f0-9]{64}\z/D', $id)) { http_response_code(404); exit; }
$file = dirname(__DIR__) . '/writable/agency-logos/' . $id . '.png';
if (!is_file($file) || !is_readable($file)) { http_response_code(404); exit; }
$etag = '"' . hash_file('sha256', $file) . '"';
header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Length: ' . filesize($file));
if ($method === 'GET') readfile($file);
