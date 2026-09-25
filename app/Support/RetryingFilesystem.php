<?php

namespace App\Support;

use ErrorException;
use Illuminate\Filesystem\Filesystem;

/**
 * Laravel's Filesystem with a replace() that survives Windows file locking.
 *
 * replace() writes a temporary file and renames it over the target, which is
 * how Blade stores a compiled view. On Windows the rename fails with "Access
 * is denied (code: 5)" while another process has the target open. On a fresh
 * install that is routine: no view is compiled yet, and the dashboard sends
 * several Livewire requests at once, each compiling the same views. A virus
 * scanner opening the new file has the same effect. Linux allows the rename,
 * so it never happens there.
 *
 * So the rename is retried for a moment. If it still fails but the target
 * already holds exactly what was to be written, as when another request just
 * compiled the same view, the write is done and the temporary file is
 * dropped. Anything else fails as before.
 *
 * `view:clear` empties the folder, temporary files included, and installing
 * or enabling a module runs it while other requests may be compiling. A
 * temporary file gone that way is written again before the next attempt.
 *
 * Bound as the application's `files` in AppServiceProvider::register(),
 * before anything resolves the Blade compiler.
 */
class RetryingFilesystem extends Filesystem
{
    /** Attempts, and the pause between them: about a second in all. */
    protected int $attempts = 20;

    protected int $pauseMicroseconds = 50_000;

    /** Why the last rename failed, as PHP put it. */
    protected ?string $renameError = null;

    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));
        $this->renameError = null;

        $this->writeTemporary($tempPath, $content, $mode);

        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            if ($this->renameFile($tempPath, $path)) {
                return;
            }

            if ($this->holds($path, $content)) {
                @unlink($tempPath);

                return;
            }

            clearstatcache(true, $tempPath);

            if (! is_file($tempPath)) {
                $this->writeTemporary($tempPath, $content, $mode);
            }

            if ($attempt < $this->attempts) {
                usleep($this->pauseMicroseconds);
            }
        }

        $error = $this->renameError ?? "rename({$tempPath}, {$path}) failed";
        @unlink($tempPath);

        throw new ErrorException($error);
    }

    protected function writeTemporary(string $tempPath, string $content, ?int $mode): void
    {
        file_put_contents($tempPath, $content);

        @chmod($tempPath, $mode ?? 0777 - umask());
    }

    /**
     * The warning is caught here, not with @: Laravel's error handler takes it
     * either way, so error_get_last() would never see it.
     */
    protected function renameFile(string $from, string $to): bool
    {
        set_error_handler(function (int $level, string $message): bool {
            $this->renameError = $message;

            return true;
        });

        try {
            return rename($from, $to);
        } finally {
            restore_error_handler();
        }
    }

    /** Whether $path already contains exactly $content. */
    protected function holds(string $path, string $content): bool
    {
        clearstatcache(true, $path);

        return is_file($path) && @filesize($path) === strlen($content) && @file_get_contents($path) === $content;
    }
}
