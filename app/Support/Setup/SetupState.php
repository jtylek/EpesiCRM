<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Whether this installation has been set up — Epesi's "is the Base module
 * installed" check that sent a fresh install to FirstRun.
 *
 * The marker file written by the Installer answers it with no query. An
 * installation that predates the wizard (or was built with `migrate --seed`
 * or `import:legacy`) has no marker, so the fallback is "any user exists":
 * the wizard's own first act is creating one, so an empty users table is
 * the one state that genuinely needs it.
 */
class SetupState
{
    protected static ?bool $installed = null;

    public static function isInstalled(): bool
    {
        if (static::$installed === true) {
            return true;
        }

        if (is_file(static::markerPath())) {
            return static::$installed = true;
        }

        try {
            // Not cached when false: the next request after setup has to see
            // the change, and a failed connection may just be a database
            // that isn't configured yet.
            return static::$installed = DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public static function databaseReady(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('users') && DB::getSchemaBuilder()->hasTable('modules');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Module setup pages (phase 2, Epesi's post_install forms) still waiting
     * for the administrator.
     */
    public static function finishPending(): bool
    {
        return (bool) (static::marker()['finish_pending'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function writeMarker(array $data): void
    {
        File::ensureDirectoryExists(dirname(static::markerPath()));
        File::put(static::markerPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        static::$installed = true;
    }

    public static function markFinished(): void
    {
        static::writeMarker([...static::marker(), 'finish_pending' => false, 'finished_at' => now()->toIso8601String()]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function marker(): array
    {
        $path = static::markerPath();

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    public static function markerPath(): string
    {
        return (string) config('setup.marker_path');
    }

    /** For tests: forget the per-process answer. */
    public static function flush(): void
    {
        static::$installed = null;
    }
}
