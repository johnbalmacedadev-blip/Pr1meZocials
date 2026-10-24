<?php

/**
 * Shared-hosting entry when the web root is the project folder (not /public).
 */

$publicIndex = __DIR__.'/public/index.php';
$autoload = __DIR__.'/vendor/autoload.php';

if (! is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing vendor/autoload.php. Pull the latest GitHub main branch (vendor is included).\n";
    exit;
}

if (! is_file($publicIndex)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing public/index.php — the Laravel public folder is incomplete.\n";
    exit;
}

require $publicIndex;
