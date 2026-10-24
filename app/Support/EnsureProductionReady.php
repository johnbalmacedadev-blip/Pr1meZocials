<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shared-hosting bootstrap: after git pull, the first web request
 * creates storage folders, runs migrations, and seeds if needed.
 */
class EnsureProductionReady
{
    private static bool $attempted = false;

    public static function run(): void
    {
        if (self::$attempted || app()->runningInConsole()) {
            return;
        }

        self::$attempted = true;

        $lockPath = storage_path('framework/install.lock');
        if (is_file($lockPath)) {
            return;
        }

        foreach ([
            storage_path('app/public'),
            storage_path('app/private'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            public_path('storage'),
        ] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            if (! Schema::hasTable('users') || User::query()->doesntExist()) {
                Artisan::call('db:seed', ['--force' => true]);
            }

            @file_put_contents($lockPath, date('c').PHP_EOL);
        } catch (Throwable $exception) {
            @file_put_contents(
                storage_path('logs/deploy-bootstrap.log'),
                '['.date('c').'] '.$exception->getMessage().PHP_EOL,
                FILE_APPEND
            );
        }
    }
}
