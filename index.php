<?php

/**
 * Shared-hosting entry when the web root is the project folder (not /public).
 */

$publicIndex = __DIR__.'/public/index.php';
$autoload = __DIR__.'/vendor/autoload.php';
$envPath = __DIR__.'/.env';
$installLock = __DIR__.'/storage/framework/install.lock';

if (! is_file($autoload) || ! is_file($envPath) || ! is_file($installLock)) {
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $installUrl = $scheme.'://'.$host.$base.'/install.php';

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup required</title></head><body style="font-family:system-ui;padding:2rem">';
    echo '<h1>Prime Z needs a one-time browser install</h1>';
    if (! is_file($autoload)) {
        echo '<p>The <code>vendor</code> folder is missing. Pull/upload the latest GitHub code that includes <code>vendor</code> and <code>public/build</code>, then continue.</p>';
    }
    echo '<p><a href="'.htmlspecialchars($installUrl, ENT_QUOTES, 'UTF-8').'">Open the installer</a></p>';
    echo '<p>Or open <code>/public/install.php</code> directly in File Manager / browser.</p>';
    echo '</body></html>';
    exit;
}

if (! is_file($publicIndex)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing public/index.php — the Laravel public folder is incomplete.\n";
    exit;
}

require $publicIndex;
