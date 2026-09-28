<?php

namespace Epesi\Modules\RecordBrowser\Files;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A file field's column: the StoredFile ids it holds (App\Services\FileStorage),
 * as a JSON list — the port of legacy's `file` value, `__12__34__`.
 *
 * Ids come back as strings, one or several alike: that is FileUpload's state,
 * and its tamper check (preventFilePathTampering()) only recognises a record's
 * own files when they compare equal. No files is stored as null, not `[]`, so a
 * "has files" filter is a plain null check.
 *
 * Also how the rest of the engine tells a file column from any other JSON
 * column: HasFileFields releases the files of every column cast to this, and
 * the download route serves only from those.
 *
 * @implements CastsAttributes<list<string>, list<int|string>|int|string|null>
 */
class StoredFileIds implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return static::decode($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $ids = static::normalize($value);

        return $ids === [] ? null : json_encode($ids);
    }

    /**
     * A raw column value as the list of ids it holds.
     *
     * @return list<string>
     */
    public static function decode(mixed $raw): array
    {
        return static::normalize(is_string($raw) ? json_decode($raw, true) : $raw);
    }

    /**
     * @return list<string>
     */
    protected static function normalize(mixed $value): array
    {
        $ids = array_filter(
            is_array($value) ? $value : [$value],
            fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        );

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
