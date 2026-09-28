<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use App\Models\StoredFile;
use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

/**
 * Looks after the files of a model's file fields (Field::file()): every column
 * cast to StoredFileIds, whether a module declared it or an administrator
 * added it (HasCustomFields uses this trait, so a custom File field works on
 * every recordset that takes custom fields).
 *
 * A file taken off a field leaves the file storage, unless something else holds
 * the same content (App\Services\FileStorage), and so does every file of a
 * record deleted for good. A soft-deleted record keeps its files, so Restore
 * brings them back — the same rules as a note's files.
 */
trait HasFileFields
{
    public static function bootHasFileFields(): void
    {
        // On saved, not updated: LogsActivity logs on updated, and its entry
        // has to name the files (tapActivity()) before they go. Which of two
        // traits' updated listeners runs first depends on the order the model
        // lists them in; saved always comes after updated.
        // wasChanged() is false straight after an insert, so a new record's
        // files are left alone. The original is synced only after saved, so it
        // still holds the files as they were.
        static::saved(function (self $record): void {
            foreach ($record->fileColumns() as $column) {
                if (! $record->wasChanged($column)) {
                    continue;
                }

                $removed = array_diff(StoredFileIds::decode($record->getRawOriginal($column)), $record->getAttribute($column));

                StoredFile::query()->whereKey($removed)->get()->each->delete();
            }
        });

        static::deleted(function (self $record): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($record), true) && ! $record->isForceDeleting()) {
                return;
            }

            $record->storedFiles()->each->delete();
        });
    }

    /**
     * @return list<string> the columns holding StoredFile ids
     */
    public function fileColumns(): array
    {
        return array_keys(array_filter($this->getCasts(), fn (string $cast): bool => $cast === StoredFileIds::class));
    }

    /**
     * The files one column holds, in its order, or every file column's when
     * $column is null.
     *
     * @return Collection<int, StoredFile>
     */
    public function storedFiles(?string $column = null): Collection
    {
        $ids = [];

        foreach ($column === null ? $this->fileColumns() : [$column] as $one) {
            array_push($ids, ...StoredFileIds::decode($this->getAttribute($one)));
        }

        if ($ids === []) {
            return new Collection;
        }

        $files = StoredFile::query()->with('content')->whereKey($ids)->get()->keyBy(fn (StoredFile $file): string => (string) $file->getKey());

        return new Collection(array_values(array_filter(array_map(fn (string $id): ?StoredFile => $files[$id] ?? null, $ids))));
    }

    /**
     * The History addon names a file by the name it had when the change was
     * logged, `file_names` (id => name) on the entry: a file taken off is
     * deleted, name and all, straight after (on saved, see above).
     *
     * A model that defines its own tapActivity() calls tapFileNames() from it.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $this->tapFileNames($activity);
    }

    protected function tapFileNames(Activity $activity): void
    {
        $ids = [];

        foreach ($this->fileColumns() as $column) {
            array_push(
                $ids,
                ...StoredFileIds::decode(data_get($activity->properties, "old.{$column}")),
                ...StoredFileIds::decode(data_get($activity->properties, "attributes.{$column}")),
            );
        }

        if ($ids !== []) {
            $activity->properties = $activity->properties->put(
                'file_names',
                StoredFile::query()->whereKey(array_unique($ids))->pluck('name', 'id')->all(),
            );
        }
    }
}
