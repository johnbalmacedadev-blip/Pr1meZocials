<?php

/**
 * Shared-hosting entry when the web root is the project folder (not /public).
 */

$publicIndex = __DIR__.'/public/index.php';
$autoload = __DIR__.'/vendor/autoload.php';
$envPath = __DIR__.'/.env';

if (! is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Laravel is not installed yet.\n\n";
    echo "On the server, run inside this folder:\n";
    echo "  composer install --no-dev --optimize-autoloader\n";
    echo "  php artisan key:generate\n";
    echo "  php artisan migrate --force\n";
    echo "  npm ci && npm run build\n";
    exit;
}

if (! is_file($envPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing .env file.\n\n";
    echo "Copy .env.example to .env on the server, set APP_URL, APP_KEY, and DB_* credentials,\n";
    echo "then run: php artisan key:generate && php artisan config:clear\n";
    exit;
}

if (! is_file($publicIndex)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing public/index.php — the Laravel public folder is incomplete.\n";
    exit;
}

require $publicIndex;
