<?php

namespace App\Console\Commands;

use App\Models\StoredFile;
use App\Support\Files\StoredFileReferences;
use Illuminate\Console\Command;

/**
 * Deletes the StoredFile rows nothing refers to — what repeated legacy imports
 * and wiped tables leave behind. Deleting the last StoredFile of a content
 * removes the content and its file from the disk too (FileStorage::release()).
 *
 * A dry run unless --force. A StoredFile is created before the record holding
 * it is saved (an upload), so rows younger than --older-than hours are kept.
 */
class PruneFileStorage extends Command
{
    protected $signature = 'filestorage:prune
        {--force : actually delete; without it only the counts are shown}
        {--older-than=24 : keep rows created within this many hours}';

    protected $description = 'Delete stored files no note, e-mail or record refers to (dry run unless --force)';

    public function handle(): int
    {
        $referenced = StoredFileReferences::ids();
        $cutoff = now()->subHours(max(0, (int) $this->option('older-than')));

        $orphans = StoredFile::query()
            ->where('created_at', '<=', $cutoff)
            ->whereNotIn('id', $referenced)
            ->pluck('id');

        $total = StoredFile::query()->count();

        $this->line("Stored files: {$total}, referenced: ".count($referenced).", unreferenced and older than {$this->option('older-than')} h: {$orphans->count()}");

        if (! $this->option('force')) {
            $this->info('Dry run: nothing was deleted. Rerun with --force.');

            return self::SUCCESS;
        }

        $orphans->chunk(500)->each(
            fn ($ids) => StoredFile::query()->whereKey($ids->all())->get()->each->delete(),
        );

        $this->info("{$orphans->count()} stored file(s) deleted.");

        return self::SUCCESS;
    }
}
