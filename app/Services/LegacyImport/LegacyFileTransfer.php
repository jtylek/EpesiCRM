<?php

namespace App\Services\LegacyImport;

use App\Models\StoredFile;

/**
 * How an importer gets one legacy file (a mail attachment, a note's file) into
 * the Epesi 3 file storage. `import:legacy` copies (the legacy tree stays as it
 * is); the Migration Wizard binds a transfer that MOVES the file, so an in-place
 * migration never needs twice the disk space (see the Migration Wizard plan note).
 */
interface LegacyFileTransfer
{
    /** Whether the legacy file is consumed (moved) rather than copied. */
    public function moves(): bool;

    /**
     * Store one legacy file. The first candidate that is available wins.
     *
     * @param  string  $dataDir  absolute path of the legacy data/ directory
     * @param  list<string>  $candidates  paths relative to $dataDir, in order of preference
     * @param  iterable<StoredFile>  $reuse  StoredFiles an earlier run made for the same owner
     * @param  string  $ownerType  morph-style alias of the owning record ('mail', 'attachment')
     * @param  string|int  $ownerId  the legacy id of the owning record
     * @param  string|null  $createdOn  the date the file keeps as its creation date (the mail's own date)
     * @return StoredFile|null null when no candidate is available (the caller reports it)
     */
    public function store(string $dataDir, array $candidates, string $name, iterable $reuse, string $ownerType, string|int $ownerId, ?string $createdOn = null): ?StoredFile;
}
