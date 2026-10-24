<?php

/**
 * Browser installer for shared hosting without SSH.
 * Visit: https://your-domain/pr1mesocials/public/install.php
 *    or: https://your-domain/pr1mesocials/install.php (via root rewrite)
 *
 * Delete this file after a successful install.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root.'/.env';
$examplePath = $root.'/.env.example';
$autoload = $root.'/vendor/autoload.php';
$lockPath = $root.'/storage/framework/install.lock';

header('Content-Type: text/html; charset=UTF-8');

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function writeEnv(string $path, array $values): void
{
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }

    $keys = array_keys($values);
    $seen = [];

    foreach ($lines as $index => $line) {
        if (! str_contains($line, '=')) {
            continue;
        }
        [$key] = explode('=', $line, 2);
        $key = trim($key);
        if (array_key_exists($key, $values)) {
            $raw = $values[$key];
            $lines[$index] = $key.'='.(preg_match('/\s|#|"|\'/', (string) $raw) ? '"'.addcslashes((string) $raw, '"\\').'"' : $raw);
            $seen[$key] = true;
        }
    }

    foreach ($keys as $key) {
        if (! isset($seen[$key])) {
            $raw = $values[$key];
            $lines[] = $key.'='.(preg_match('/\s|#|"|\'/', (string) $raw) ? '"'.addcslashes((string) $raw, '"\\').'"' : $raw);
        }
    }

    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
}

$vendorReady = is_file($autoload);
$alreadyInstalled = is_file($lockPath) && is_file($envPath);
$error = null;
$success = null;
$logs = [];

$defaults = [
    'APP_NAME' => 'Prime Z',
    'APP_URL' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/public'),
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'thevclo_primez_creative_ops',
    'DB_USERNAME' => 'thevclo_pr1mez',
    'DB_PASSWORD' => '',
];

// Normalize APP_URL if script is under /public
$defaults['APP_URL'] = preg_replace('#/public$#', '', $defaults['APP_URL']) ?: $defaults['APP_URL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $alreadyInstalled) {
    $appUrl = trim((string) ($_POST['APP_URL'] ?? ''));
    $dbHost = trim((string) ($_POST['DB_HOST'] ?? '127.0.0.1'));
    $dbPort = trim((string) ($_POST['DB_PORT'] ?? '3306'));
    $dbName = trim((string) ($_POST['DB_DATABASE'] ?? ''));
    $dbUser = trim((string) ($_POST['DB_USERNAME'] ?? ''));
    $dbPass = (string) ($_POST['DB_PASSWORD'] ?? '');

    $defaults = array_merge($defaults, [
        'APP_URL' => $appUrl,
        'DB_HOST' => $dbHost,
        'DB_PORT' => $dbPort,
        'DB_DATABASE' => $dbName,
        'DB_USERNAME' => $dbUser,
        'DB_PASSWORD' => $dbPass,
    ]);

    try {
        if (! $vendorReady) {
            throw new RuntimeException('vendor/ is missing. Pull the latest repo (includes vendor) or upload the vendor folder via File Manager.');
        }

        if ($appUrl === '' || $dbName === '' || $dbUser === '') {
            throw new RuntimeException('APP_URL, database name, and database user are required.');
        }

        if (! is_file($envPath)) {
            if (! is_file($examplePath)) {
                throw new RuntimeException('.env.example is missing.');
            }
            if (! @copy($examplePath, $envPath)) {
                throw new RuntimeException('Could not create .env — make the project folder writable.');
            }
            $logs[] = 'Created .env from .env.example';
        }

        writeEnv($envPath, [
            'APP_NAME' => 'Prime Z',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ]);
        $logs[] = 'Wrote database and APP_URL settings into .env';

        foreach (['storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $full = $root.'/'.$dir;
            if (! is_dir($full) && ! @mkdir($full, 0775, true) && ! is_dir($full)) {
                throw new RuntimeException('Could not create '.$dir);
            }
            @chmod($full, 0775);
        }
        $logs[] = 'Ensured storage and cache folders exist';

        require $autoload;
        /** @var \Illuminate\Foundation\Application $app */
        $app = require $root.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        if (! filled(config('app.key'))) {
            $kernel->call('key:generate', ['--force' => true]);
            $logs[] = 'Generated APP_KEY';
        } else {
            $logs[] = 'APP_KEY already set';
        }

        $kernel->call('config:clear');
        $kernel->call('migrate', ['--force' => true]);
        $logs[] = 'Ran database migrations';

        try {
            $kernel->call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
            $logs[] = 'Ran database seeders';
        } catch (Throwable $seedError) {
            $logs[] = 'Seeders skipped/partial: '.$seedError->getMessage();
        }

        try {
            $kernel->call('storage:link');
            $logs[] = 'Linked public storage';
        } catch (Throwable $linkError) {
            $logs[] = 'Storage link skipped: '.$linkError->getMessage();
        }

        @file_put_contents($lockPath, date('c').PHP_EOL);
        $success = 'Install finished. Open the site home page, then delete public/install.php for security.';
        $alreadyInstalled = true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prime Z Installer</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; margin: 0; padding: 2rem; }
        .card { max-width: 34rem; margin: 0 auto; background: #fff; border: 1px solid #e4e4e7; border-radius: 1rem; padding: 1.5rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; }
        p { color: #52525b; line-height: 1.45; }
        label { display: block; font-size: .875rem; font-weight: 600; margin: .9rem 0 .35rem; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #d4d4d8; border-radius: .6rem; padding: .65rem .75rem; font: inherit; }
        button { margin-top: 1.25rem; width: 100%; border: 0; border-radius: .6rem; background: #284cb8; color: #fff; font: inherit; font-weight: 600; padding: .8rem; cursor: pointer; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: .75rem; border-radius: .6rem; }
        .err { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; padding: .75rem; border-radius: .6rem; }
        .warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; padding: .75rem; border-radius: .6rem; }
        ul { margin: .75rem 0 0; padding-left: 1.1rem; color: #3f3f46; }
        code { background: #f4f4f5; padding: .1rem .35rem; border-radius: .3rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Prime Z installer</h1>
    <p>Use this once on shared hosting when you cannot run Composer or Artisan over SSH.</p>

    <?php if (! $vendorReady): ?>
        <p class="err"><strong>vendor/</strong> is missing. Pull/upload the latest repository that includes the <code>vendor</code> folder, then reload this page.</p>
    <?php endif; ?>

    <?php if ($alreadyInstalled && $success === null): ?>
        <p class="warn">Already installed (<code>storage/framework/install.lock</code> exists). Delete that lock file only if you intentionally need to re-run setup.</p>
        <p><a href="../">Go to the app</a></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="ok"><?= h($success) ?></p>
        <ul><?php foreach ($logs as $line): ?><li><?= h($line) ?></li><?php endforeach; ?></ul>
        <p><a href="<?= h(rtrim($defaults['APP_URL'], '/').'/') ?>">Open Prime Z</a></p>
    <?php elseif (! $alreadyInstalled): ?>
        <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <label for="APP_URL">App URL</label>
            <input id="APP_URL" name="APP_URL" required value="<?= h($defaults['APP_URL']) ?>">

            <label for="DB_HOST">DB host</label>
            <input id="DB_HOST" name="DB_HOST" required value="<?= h($defaults['DB_HOST']) ?>">

            <label for="DB_PORT">DB port</label>
            <input id="DB_PORT" name="DB_PORT" required value="<?= h($defaults['DB_PORT']) ?>">

            <label for="DB_DATABASE">DB name</label>
            <input id="DB_DATABASE" name="DB_DATABASE" required value="<?= h($defaults['DB_DATABASE']) ?>">

            <label for="DB_USERNAME">DB user</label>
            <input id="DB_USERNAME" name="DB_USERNAME" required value="<?= h($defaults['DB_USERNAME']) ?>">

            <label for="DB_PASSWORD">DB password</label>
            <input id="DB_PASSWORD" name="DB_PASSWORD" type="password" value="<?= h($defaults['DB_PASSWORD']) ?>">

            <button type="submit" <?= $vendorReady ? '' : 'disabled' ?>>Install Prime Z</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
