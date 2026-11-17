<?php
// Retired public tool: do not bootstrap the old form or calculation code.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
echo 'This page is no longer available.';
exit;
