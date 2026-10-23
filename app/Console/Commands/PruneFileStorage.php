<?php

namespace App\Console\Commands;

use App\Models\StoredFile;
use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $referenced = $this->referencedIds();
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

    /**
     * Every StoredFile id a note, an e-mail, a record's file field or a
     * record's rich text (stored_file_links) holds.
     * Read straight from the tables, past the models' scopes: a trashed record
     * still holds its files.
     *
     * @return array<int, int>
     */
    protected function referencedIds(): array
    {
        $ids = [];

        if (Schema::hasTable('epesi_attachments')) {
            foreach (DB::table('epesi_attachments')->whereNotNull('files')->pluck('files') as $raw) {
                array_push($ids, ...StoredFileIds::decode($raw));
            }
        }

        // Images pasted into an editor (InlineImages), claimed or not: their
        // own clean-up is filestorage:prune-inline-images.
        if (Schema::hasTable('stored_file_links')) {
            array_push($ids, ...DB::table('stored_file_links')->pluck('stored_file_id')->all());
        }

        if (Schema::hasTable('epesi_mail_attachments')) {
            array_push($ids, ...DB::table('epesi_mail_attachments')->whereNotNull('stored_file_id')->pluck('stored_file_id')->all());
        }

        foreach (Relation::morphMap() as $class) {
            if (! is_subclass_of($class, Model::class) || ! method_exists($class, 'fileColumns')) {
                continue;
            }

            $model = new $class;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            foreach ($model->fileColumns() as $column) {
                if (! Schema::hasColumn($model->getTable(), $column)) {
                    continue;
                }

                foreach (DB::table($model->getTable())->whereNotNull($column)->pluck($column) as $raw) {
                    array_push($ids, ...StoredFileIds::decode($raw));
                }
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
