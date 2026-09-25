<?php

namespace App\Models;

use App\Services\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One distinct file content, kept once on the `filestorage` disk under its
 * hash — a `utils_filestorage_files` row. Nothing outside App\Services\FileStorage
 * creates or deletes these; everything else holds a StoredFile.
 */
class StoredFileContent extends Model
{
    protected $table = 'stored_file_contents';

    protected $fillable = [
        'hash',
        'size',
        'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return HasMany<StoredFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class, 'content_id');
    }

    /** Where the content lives on the `filestorage` disk. */
    public function path(): string
    {
        return FileStorage::pathFor($this->hash);
    }
}
