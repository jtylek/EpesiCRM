<?php

namespace App\Services\LegacyImport;

use App\Models\StoredFile;
use App\Services\FileStorage;

/** `import:legacy`'s transfer: copies into the file storage and leaves the legacy file where it is. */
class CopyingLegacyFileTransfer implements LegacyFileTransfer
{
    public function moves(): bool
    {
        return false;
    }

    public function store(string $dataDir, array $candidates, string $name, iterable $reuse, string $ownerType, string|int $ownerId, ?string $createdOn = null): ?StoredFile
    {
        foreach ($candidates as $relative) {
            $path = rtrim($dataDir, '/\\').'/'.$relative;

            if (is_file($path)) {
                return static::keepCreatedOn(app(FileStorage::class)->putFile($path, $name, $reuse), $createdOn);
            }
        }

        return null;
    }

    /** A file keeps the date of the record it came with, not the time of the import. */
    public static function keepCreatedOn(StoredFile $file, ?string $createdOn): StoredFile
    {
        if (filled($createdOn)) {
            $file->timestamps = false;
            $file->created_at = $createdOn;
            $file->updated_at = $createdOn;
            $file->saveQuietly();
        }

        return $file;
    }
}
