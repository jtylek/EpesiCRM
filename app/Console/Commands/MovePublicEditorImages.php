<?php

namespace App\Console\Commands;

use App\Support\Files\PublicEditorImages;
use Illuminate\Console\Command;

/**
 * Moves the images pasted into rich editors before they were kept in the file
 * storage (PublicEditorImages). The data migration
 * 2026_10_23_000100_move_public_editor_images_to_file_storage runs it once on
 * every installation; this is for running it again, or looking first.
 */
class MovePublicEditorImages extends Command
{
    protected $signature = 'filestorage:move-public-images
        {--dry-run : only list what would be moved}';

    protected $description = 'Move images pasted into rich editors from the public disk into the file storage';

    public function handle(PublicEditorImages $images): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $images->run($dryRun, fn (string $line) => $this->line(($dryRun ? 'Would convert ' : 'Converted ').$line));

        foreach ($result['missing'] as $path) {
            $this->warn("Not on the public disk, left as it is: /storage/{$path}");
        }

        $this->info(sprintf(
            '%s %d record(s), %d history entr%s, %d file(s).',
            $dryRun ? 'Dry run, nothing changed. Would convert' : 'Converted',
            $result['records'],
            $result['history'],
            $result['history'] === 1 ? 'y' : 'ies',
            $result['files'],
        ));

        return self::SUCCESS;
    }
}
