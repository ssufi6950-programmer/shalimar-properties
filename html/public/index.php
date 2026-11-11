<?php
// Public web entry when html/ is the document root and private application files
// remain one directory above it. The original entry keeps the same CI4 boot,
// session, CSRF, routing, authentication and writable paths.
require dirname(__DIR__, 2) . '/public/index.php';
