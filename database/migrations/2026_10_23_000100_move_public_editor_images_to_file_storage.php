<?php

use App\Support\Files\PublicEditorImages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Images pasted into a rich editor used to be kept on the public disk and
 * linked as /storage/..., which a host without the public/storage symlink
 * doesn't serve. This moves them into the file storage once
 * (PublicEditorImages; `php artisan filestorage:move-public-images` runs it
 * again). A failure is logged rather than stopping the update: the texts it
 * didn't reach still point at the public disk, and the command can be rerun.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $result = app(PublicEditorImages::class)->run();

            if ($result['records'] + $result['history'] > 0 || $result['missing'] !== []) {
                Log::info('Moved images pasted into rich editors into the file storage.', $result);
            }
        } catch (Throwable $e) {
            Log::warning('Could not move images pasted into rich editors into the file storage; run php artisan filestorage:move-public-images.', ['exception' => $e]);
        }
    }

    public function down(): void
    {
        // The public copies are gone; the images stay in the file storage.
    }
};
