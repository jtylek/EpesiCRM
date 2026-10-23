<?php

namespace App\Support\Files;

use App\Models\Concerns\HasInlineImages;
use App\Models\StoredFile;
use App\Services\FileStorage;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * One-off: images pasted into a rich editor before InlineImages, which
 * Filament kept on the public disk and linked as `<img src="/storage/x.png"
 * data-id="x.png">`. Each is moved into the file storage, the text names it by
 * its StoredFile id instead, as a new image would be, and it is linked to the
 * record. Records of every model with HasInlineImages are converted, and their
 * History entries too, which Show and Restore read. An address that isn't an
 * `<img>` (a Markdown image, a link) becomes the image route's address.
 *
 * The public copies are deleted only at the end, once every text naming them
 * is rewritten. Running it again finds nothing left to do. A text naming a
 * file that is not on the public disk is left as it is and reported.
 */
class PublicEditorImages
{
    public const DISK = 'public';

    /** @var array<string, StoredFile|null> public path => its StoredFile (null in a dry run) */
    protected array $moved = [];

    /** @var array<string, true> */
    protected array $missing = [];

    protected bool $dryRun = false;

    /**
     * @param  (Closure(string): void)|null  $report  told about each record converted
     * @return array{records: int, history: int, files: int, missing: list<string>}
     */
    public function run(bool $dryRun = false, ?Closure $report = null): array
    {
        $this->dryRun = $dryRun;
        $this->moved = [];
        $this->missing = [];
        $report ??= fn (string $line) => null;
        $records = 0;
        $history = 0;

        foreach ($this->owners() as $alias => $class) {
            /** @var Model&HasInlineImages $model */
            $model = new $class;
            $columns = array_values(array_filter($model->richTextColumns(), fn (string $column): bool => Schema::hasColumn($model->getTable(), $column)));

            if ($columns === []) {
                continue;
            }

            $model->newQueryWithoutScopes()
                ->where(function ($query) use ($columns): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%/storage/%');
                    }
                })
                ->chunkById(100, function (Collection $chunk) use ($columns, $alias, $report, &$records): void {
                    foreach ($chunk as $record) {
                        if ($this->convertRecord($record, $columns)) {
                            $records++;
                            $report("{$alias} #{$record->getKey()}");
                        }
                    }
                });

            if (Schema::hasTable((new Activity)->getTable())) {
                Activity::query()
                    ->where('subject_type', $alias)
                    ->where('properties', 'like', '%storage%')
                    ->chunkById(100, function (Collection $chunk) use ($model, $columns, $alias, $report, &$history): void {
                        foreach ($chunk as $entry) {
                            if ($this->convertEntry($entry, $model, $columns)) {
                                $history++;
                                $report("{$alias} #{$entry->subject_id}: history entry #{$entry->getKey()}");
                            }
                        }
                    });
            }
        }

        if (! $dryRun) {
            foreach (array_keys($this->moved) as $path) {
                $this->disk()->delete($path);
            }
        }

        return ['records' => $records, 'history' => $history, 'files' => count($this->moved), 'missing' => array_keys($this->missing)];
    }

    /**
     * The models whose records hold rich text with images, by morph alias.
     *
     * @return array<string, class-string<Model>>
     */
    protected function owners(): array
    {
        return array_filter(
            Relation::morphMap(),
            fn (string $class): bool => InlineImages::supports($class) && Schema::hasTable((new $class)->getTable()),
        );
    }

    /**
     * @param  list<string>  $columns
     */
    protected function convertRecord(Model $record, array $columns): bool
    {
        $changes = [];
        $files = [];

        foreach ($columns as $column) {
            $old = $record->getAttributes()[$column] ?? null;

            if (is_string($old) && ($new = $this->rewrite($old, $files)) !== $old) {
                $changes[$column] = $new;
            }
        }

        if ($changes === []) {
            return false;
        }

        if (! $this->dryRun) {
            // Past the model: this is no edit of the record, so no History
            // entry, no Watchdog notice and no "edited on".
            DB::transaction(function () use ($record, $changes, $files): void {
                $record->newQueryWithoutScopes()->whereKey($record->getKey())->update($changes);
                $this->linkAll($files, $record);
            });
        }

        return true;
    }

    /**
     * @param  Model&HasInlineImages  $model
     * @param  list<string>  $columns
     */
    protected function convertEntry(Activity $entry, Model $model, array $columns): bool
    {
        // An entry of a record deleted for good: nobody can open its History.
        $subject = $model->newQueryWithoutScopes()->find($entry->subject_id);

        if ($subject === null) {
            return false;
        }

        $properties = $entry->properties->toArray();
        $files = [];
        $changed = false;

        foreach (['old', 'attributes'] as $part) {
            foreach ($columns as $column) {
                $old = $properties[$part][$column] ?? null;

                if (is_string($old) && ($new = $this->rewrite($old, $files)) !== $old) {
                    $properties[$part][$column] = $new;
                    $changed = true;
                }
            }
        }

        if (! $changed) {
            return false;
        }

        if (! $this->dryRun) {
            DB::transaction(function () use ($entry, $properties, $files, $subject): void {
                $entry->properties = collect($properties);
                $entry->save();
                $this->linkAll($files, $subject);
            });
        }

        return true;
    }

    /**
     * The text with every image of the public disk replaced by its StoredFile.
     *
     * @param  list<StoredFile|null>  $files  collects the files the text names
     */
    public function rewrite(string $text, array &$files): string
    {
        if (! str_contains($text, '/storage/')) {
            return $text;
        }

        $text = preg_replace_callback('~<img\b[^>]*>~i', function (array $match) use (&$files): string {
            $tag = $match[0];

            if (! preg_match('~\ssrc\s*=\s*(["\'])(.*?)\1~is', $tag, $src) || ($path = $this->publicPath(html_entity_decode($src[2]))) === null) {
                return $tag;
            }

            $file = $this->move($path);
            $files[] = $file;
            $tag = (string) preg_replace('~\s(?:src|data-id)\s*=\s*(["\']).*?\1~is', '', $tag);

            return (string) preg_replace('~^<img\b~i', '<img data-id="'.($file?->getKey() ?? 0).'"', $tag, 1);
        }, $text) ?? $text;

        return preg_replace_callback('~(?:https?://[^\s"\'()<>]+)?/storage/[^\s"\'()<>?#]+~i', function (array $match) use (&$files): string {
            if (($path = $this->publicPath($match[0])) === null) {
                return $match[0];
            }

            $file = $this->move($path);
            $files[] = $file;

            return route(InlineImages::ROUTE, ['storedFile' => $file?->getKey() ?? 0]);
        }, $text) ?? $text;
    }

    /** The path on the public disk an address names, when the file is there. */
    protected function publicPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! preg_match('~/storage/(.+)$~', $path, $match)) {
            return null;
        }

        $relative = rawurldecode($match[1]);

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')) {
            return null;
        }

        if (! isset($this->moved[$relative]) && ! $this->disk()->exists($relative)) {
            $this->missing[$relative] = true;

            return null;
        }

        return $relative;
    }

    protected function move(string $path): ?StoredFile
    {
        if (array_key_exists($path, $this->moved)) {
            return $this->moved[$path];
        }

        return $this->moved[$path] = $this->dryRun
            ? null
            : app(FileStorage::class)->putFile($this->disk()->path($path), basename($path));
    }

    /**
     * @param  list<StoredFile|null>  $files
     */
    protected function linkAll(array $files, Model $owner): void
    {
        foreach (array_filter($files) as $file) {
            app(InlineImages::class)->link($file, $owner);
        }
    }

    protected function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(self::DISK);
    }
}
