<?php

namespace Epesi\Modules\MigrationWizard\Files;

use App\Models\StoredFile;
use App\Services\FileStorage;
use App\Services\LegacyImport\CopyingLegacyFileTransfer;
use App\Services\LegacyImport\LegacyFileTransfer;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * The in-place migration's file transfer: legacy files are MOVED into the Epesi 3
 * file storage one at a time, so the extra disk space needed is at most one file.
 *
 *  1. hash the source (SHA-256 for the ledger, SHA-512 as the storage address) and
 *     write the ledger entry `moving` BEFORE touching it;
 *  2. same filesystem: rename(); otherwise copy that one file, verify, delete the
 *     source before the next file;
 *  3. content already stored (de-duplication): verify the stored copy's hash, then
 *     delete the source instead of storing it twice;
 *  4. the entry becomes `moved` / `deduplicated` only after the destination hash
 *     verified.
 *
 * Resumable: a source that is already gone is resolved from its ledger entry (which
 * side holds the file), never re-hashed in full. A missing or unreadable source is a
 * `missing` / `unreadable` ledger entry, which fails verification, never a skip.
 * Re-importing a file another record already moved (a shared Utils_FileStorage file)
 * adds only a new use of the stored content.
 */
final class MovingLegacyFileTransfer implements LegacyFileTransfer
{
    public function __construct(
        private readonly FileLedger $ledger,
        private readonly FileStorage $storage = new FileStorage,
        private readonly ?Filesystem $disk = null,
        private readonly string $diskName = FileStorage::DISK,
    ) {}

    public function moves(): bool
    {
        return true;
    }

    public function store(string $dataDir, array $candidates, string $name, iterable $reuse, string $ownerType, string|int $ownerId, ?string $createdOn = null): ?StoredFile
    {
        $root = realpath($dataDir);
        if ($root === false || $candidates === []) {
            throw new RuntimeException('The legacy data directory was not found; nothing was moved.');
        }
        $relatives = array_map(fn (string $path): string => $this->ledger->normalise($path), $candidates);

        foreach ($relatives as $relative) {
            $full = $root.'/'.$relative;
            $entry = $this->ledger->find($relative);
            if (file_exists($full) || is_link($full)) {
                $file = $this->move($root, $relative, $name, $ownerType, $ownerId, $entry);
                if ($file === null) {
                    return null; // recorded as unreadable
                }

                return CopyingLegacyFileTransfer::keepCreatedOn($file, $createdOn);
            }
            if ($entry !== null && ($entry['status'] === FileLedger::MOVING || FileLedger::isDone($entry))) {
                $file = $this->resolve($entry, $name, $reuse);
                if ($file !== null) {
                    return CopyingLegacyFileTransfer::keepCreatedOn($file, $createdOn);
                }
            }
        }

        $this->ledger->record([
            'source' => $relatives[0], 'size' => null, 'sha256' => null, 'disk' => $this->diskName, 'destination' => null,
            'owner_type' => $ownerType, 'owner_id' => (string) $ownerId,
            'status' => FileLedger::MISSING, 'error' => 'The source file does not exist.',
        ]);

        return null;
    }

    /** A file still at its source. */
    private function move(string $root, string $relative, string $name, string $ownerType, string|int $ownerId, ?array $previous): ?StoredFile
    {
        $full = $root.'/'.$relative;
        $real = is_link($full) ? false : realpath($full);
        if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($real) || ! is_readable($real)) {
            $this->ledger->record([
                'source' => $relative, 'size' => null, 'sha256' => null, 'disk' => $this->diskName, 'destination' => null,
                'owner_type' => $ownerType, 'owner_id' => (string) $ownerId,
                'status' => FileLedger::UNREADABLE, 'error' => 'The source is a link, escapes the data directory, or cannot be read.',
            ]);

            return null;
        }
        $hashes = self::hashes($real);
        if ($hashes === null) {
            $this->ledger->record([
                'source' => $relative, 'size' => null, 'sha256' => null, 'disk' => $this->diskName, 'destination' => null,
                'owner_type' => $ownerType, 'owner_id' => (string) $ownerId,
                'status' => FileLedger::UNREADABLE, 'error' => 'The source file could not be hashed.',
            ]);

            return null;
        }
        [$sha256, $sha512, $size] = $hashes;
        $destination = FileStorage::pathFor($sha512);
        $disk = $this->disk();
        $existed = $disk->exists($destination);
        // A stored file an earlier, interrupted attempt of this very ledger placed stays ours.
        $ours = $previous !== null && in_array($previous['status'], [FileLedger::MOVING, FileLedger::FAILED], true)
            && ($previous['destination'] ?? null) === $destination && (bool) ($previous['created_destination'] ?? false);
        if ($existed && $ours && self::destinationHashes($disk, $destination) !== [$sha256, $sha512]) {
            $disk->delete($destination); // our own half-written copy from the interrupted attempt; the source is intact
            $existed = false;
        }
        $created = ! $existed || $ours;
        $base = [
            'source' => $relative, 'size' => $size, 'sha256' => $sha256, 'sha512' => $sha512, 'disk' => $this->diskName,
            'destination' => $destination, 'owner_type' => $ownerType, 'owner_id' => (string) $ownerId,
            'created_destination' => $created, 'error' => null,
        ];
        $this->ledger->record([...$base, 'status' => FileLedger::MOVING]);

        $renamed = false;
        try {
            $file = $this->storage->storeWith($sha512, $name, function (string $path) use ($disk, $real, &$renamed): bool {
                return $this->place($disk, $path, $real, $renamed);
            });
        } catch (\Throwable $e) {
            // A renamed file lives only in the file storage now: its `moving` entry stays so a resume can resolve it.
            if (! $renamed) {
                $this->ledger->record([...$base, 'status' => FileLedger::FAILED, 'error' => $e->getMessage()]);
            }

            throw $e;
        }

        // Only a verified destination counts; the source goes only after that (unless rename() already took it).
        // A rename on one filesystem moves the very bytes that were just hashed, so its size is checked instead of
        // reading and hashing the whole file a second time; a copy (another disk) is always hashed again.
        $verified = $renamed
            ? self::destinationSize($disk, $destination) === $size
            : self::destinationHashes($disk, $destination) === [$sha256, $sha512];
        if (! $verified) {
            if (! $renamed) {
                $this->ledger->record([...$base, 'status' => FileLedger::FAILED, 'error' => 'The stored file does not match its source.']);
                if ($created) {
                    $disk->delete($destination); // a bad copy we wrote; the source is intact
                }
            }

            throw new RuntimeException("The stored copy of {$relative} does not match its source hash; the source was ".($renamed ? 'already moved' : 'left in place').'.');
        }
        if (! $renamed && ! @unlink($real)) {
            $this->ledger->record([...$base, 'status' => FileLedger::FAILED, 'error' => 'The source could not be deleted after it was stored.']);

            throw new RuntimeException("Could not delete {$relative} after storing it.");
        }
        $this->ledger->record([...$base, 'status' => $existed && ! $created ? FileLedger::DEDUPLICATED : FileLedger::MOVED]);

        return $file;
    }

    /** The source is gone: its ledger entry says where the file is. */
    private function resolve(array $entry, string $name, iterable $reuse): ?StoredFile
    {
        $disk = $this->disk();
        $destination = (string) ($entry['destination'] ?? '');
        $sha512 = (string) ($entry['sha512'] ?? '');
        if ($destination === '' || $sha512 === '' || ! $disk->exists($destination)) {
            return null; // neither side holds it: reported as missing
        }
        if ($entry['status'] === FileLedger::MOVING) {
            // Interrupted between moving and recording: finish only if the stored bytes verify.
            if (self::destinationHashes($disk, $destination) !== [$entry['sha256'], $sha512]) {
                return null;
            }
            $this->ledger->record([...$entry, 'status' => ($entry['created_destination'] ?? false) ? FileLedger::MOVED : FileLedger::DEDUPLICATED]);
        }
        foreach ($reuse as $existing) {
            if ($existing->name === $name && $existing->content?->hash === $sha512 && $existing->isOnDisk()) {
                return $existing;
            }
        }

        return $this->storage->storeWith($sha512, $name, static fn (): bool => false);
    }

    /** Put the source at the storage path: rename on one filesystem, else copy (the caller deletes the source once verified). */
    private function place(Filesystem $disk, string $path, string $source, bool &$renamed): bool
    {
        if (method_exists($disk, 'path')) {
            $target = $disk->path($path);
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return false;
            }
            if (@rename($source, $target)) {
                $renamed = true;

                return true;
            }
        }
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            return false;
        }
        try {
            return (bool) $disk->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function disk(): Filesystem
    {
        return $this->disk ?? FileStorage::disk();
    }

    /** @return array{0: string, 1: string, 2: int}|null sha256, sha512, size in one pass */
    private static function hashes(string $path): ?array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return null;
        }
        $a = hash_init('sha256');
        $b = hash_init('sha512');
        $size = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                fclose($stream);

                return null;
            }
            hash_update($a, $chunk);
            hash_update($b, $chunk);
            $size += strlen($chunk);
        }
        fclose($stream);

        return [hash_final($a), hash_final($b), $size];
    }

    private static function destinationSize(Filesystem $disk, string $path): ?int
    {
        try {
            return $disk->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: string, 1: string}|null */
    private static function destinationHashes(Filesystem $disk, string $path): ?array
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }
        $a = hash_init('sha256');
        $b = hash_init('sha512');
        while (! feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                fclose($stream);

                return null;
            }
            hash_update($a, $chunk);
            hash_update($b, $chunk);
        }
        fclose($stream);

        return [hash_final($a), hash_final($b)];
    }
}
