<?php

namespace App\Console\Commands;

use App\Support\Files\InlineImages;
use Illuminate\Console\Command;

/**
 * The daily clean-up of images pasted into rich-text editors
 * (InlineImages::prune()): uploads no record was saved with, and images of
 * records deleted without their model's events. Scheduled in routes/console.php.
 */
class PruneInlineImages extends Command
{
    protected $signature = 'filestorage:prune-inline-images
        {--older-than=24 : keep uploads no record claimed for this many hours}';

    protected $description = 'Delete images pasted into an editor that no record holds';

    public function handle(InlineImages $images): int
    {
        $result = $images->prune(now()->subHours(max(0, (int) $this->option('older-than'))));

        $this->info("Unsaved uploads: {$result['pending']}, links to deleted records: {$result['orphaned']}, files deleted: {$result['files']}");

        return self::SUCCESS;
    }
}
