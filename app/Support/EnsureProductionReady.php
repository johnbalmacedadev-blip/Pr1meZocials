<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shared-hosting bootstrap: keep the schema current after every git pull,
 * and seed once when the database is empty.
 *
 * PHP starts fresh on every request, so this used to migrate and re-seed the
 * permission catalog (80+ queries) on every page, chat poll and upload chunk.
 * It now only runs when the migrations or seeders change, i.e. after a deploy.
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

        $fingerprintPath = storage_path('framework/deploy.fingerprint');
        $fingerprint = self::fingerprint();
        if (@file_get_contents($fingerprintPath) === $fingerprint) {
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
            // Always migrate — install.lock must not block newer migrations after a pull.
            Artisan::call('migrate', ['--force' => true]);

            // Keep permission definitions current, but never reset role assignments —
            // Team → Role permissions saves would otherwise be wiped on the next request.
            if (Schema::hasTable('permissions')) {
                Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionCatalogSeeder', '--force' => true]);
            }

            $lockPath = storage_path('framework/install.lock');
            if (! is_file($lockPath)) {
                if (! Schema::hasTable('users') || User::query()->doesntExist()) {
                    Artisan::call('db:seed', ['--force' => true]);
                } elseif (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
                    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
                }
                @file_put_contents($lockPath, date('c').PHP_EOL);
            }

            // Only remember success, so a failed migration is retried on the next request.
            @file_put_contents($fingerprintPath, $fingerprint);
        } catch (Throwable $exception) {
            @file_put_contents(
                storage_path('logs/deploy-bootstrap.log'),
                '['.date('c').'] '.$exception->getMessage().PHP_EOL,
                FILE_APPEND
            );
        }
    }

    /**
     * Changes whenever a pull adds or edits a migration, a seeder, or the permission map.
     */
    private static function fingerprint(): string
    {
        $files = array_merge(
            glob(database_path('migrations/*.php')) ?: [],
            glob(database_path('seeders/*.php')) ?: [],
            [app_path('Support/PageAccess.php')],
        );

        $parts = [];
        foreach ($files as $file) {
            $parts[] = basename($file).':'.@filesize($file).':'.@filemtime($file);
        }

        return md5(implode('|', $parts));
    }
}
