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

// If Apache still routed through /public/index.php, normalize SCRIPT_NAME so
// subdirectory installs resolve /login instead of /pr1mesocials/login.
foreach (['SCRIPT_NAME', 'PHP_SELF'] as $serverKey) {
    if (! empty($_SERVER[$serverKey]) && str_contains((string) $_SERVER[$serverKey], '/public/')) {
        $_SERVER[$serverKey] = str_replace('/public/', '/', (string) $_SERVER[$serverKey]);
    }
}

require $publicIndex;
