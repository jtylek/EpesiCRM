<?php

namespace Epesi\Modules\MigrationWizard\Files;

use Illuminate\Contracts\Filesystem\Filesystem;
use JsonException;
use RuntimeException;

/**
 * Business-file ledger: one entry per legacy file the migration copies, shared
 * by the importers (which copy) and the verifier (which checks). An entry holds source path (relative to the legacy data/ directory),
 * size and SHA-256, destination disk and path, and the owning record
 * (a morph alias plus id, as strings; the ledger has no model of its own).
 *
 * Missing, unreadable or unverifiable sources are recorded as failed entries,
 * never skipped silently, so verification can block on them. Importing the same
 * file again is idempotent. Stored as append-only JSON lines (last entry per
 * source wins), which keeps recording O(1) for installations with many files.
 *
 * Moves (MovingLegacyFileTransfer) use the same ledger so that nothing needs twice
 * the disk space: an entry is written as `moving` (source, size, hashes, intended
 * destination) BEFORE the file is touched and becomes `moved` (or `deduplicated`,
 * when the content was already stored) only after the destination hash verified.
 * A `moving` entry is resolved on resume by checking which side holds the file.
 */
final class FileLedger
{
    public const COPIED = 'copied';

    public const MISSING = 'missing';

    public const UNREADABLE = 'unreadable';

    public const FAILED = 'failed';

    /** Written before a move starts; never final. */
    public const MOVING = 'moving';

    /** The file was moved into the destination and the destination hash verified. */
    public const MOVED = 'moved';

    /** The content was already stored: its hash was verified and the source deleted. */
    public const DEDUPLICATED = 'deduplicated';

    /** @var array<string, array<string, mixed>>|null latest entry per source, loaded once (a ledger of 75,000 files must not be re-read per file) */
    private ?array $index = null;

    /**
     * @param  string|null  $dataRoot  the legacy data/ directory, needed to put moved files back
     */
    public function __construct(private readonly string $directory, private readonly ?string $dataRoot = null) {}

    /** The latest entry for a source path (relative to data/), from memory. */
    public function find(string $source): ?array
    {
        $this->index ??= $this->entries();

        return $this->index[$this->normalise($source)] ?? null;
    }

    /**
     * Record an entry as it is (moves write their own entries).
     *
     * @param  array<string, mixed>  $entry  needs source and status
     * @return array<string, mixed>
     */
    public function record(array $entry): array
    {
        $entry['source'] = $this->normalise((string) ($entry['source'] ?? ''));
        if (! is_string($entry['status'] ?? null)) {
            throw new RuntimeException('A ledger entry needs a status.');
        }

        return $this->append($entry);
    }

    public static function isDone(array $entry): bool
    {
        return in_array($entry['status'] ?? null, [self::COPIED, self::MOVED, self::DEDUPLICATED], true);
    }

    /**
     * Copy one legacy file to a destination disk and record it.
     *
     * @return array<string, mixed> the ledger entry (status 'copied', or a failure status)
     */
    public function import(
        string $dataRoot,
        string $relativePath,
        Filesystem $disk,
        string $diskName,
        string $destination,
        string $ownerType,
        string|int $ownerId,
    ): array {
        $relative = $this->normalise($relativePath);
        $destination = $this->normaliseDestination($destination);
        $entry = [
            'source' => $relative,
            'size' => null,
            'sha256' => null,
            'disk' => $diskName,
            'destination' => $destination,
            'owner_type' => $ownerType,
            'owner_id' => (string) $ownerId,
            'status' => self::FAILED,
            'error' => null,
        ];

        $entries = $this->entries();
        foreach ($entries as $other) {
            if ($other['status'] === self::COPIED && $other['disk'] === $diskName
                && $other['destination'] === $destination && $other['source'] !== $relative) {
                throw new RuntimeException('Another legacy file is already recorded at this destination.');
            }
        }

        $path = $this->resolveSource($dataRoot, $relative, $entry);
        if ($path === null) {
            return $this->append($entry);
        }
        $hash = hash_file('sha256', $path);
        $size = filesize($path);
        if ($hash === false || $size === false) {
            return $this->append([...$entry, 'status' => self::UNREADABLE, 'error' => 'The source file could not be hashed.']);
        }
        $entry['size'] = $size;
        $entry['sha256'] = $hash;

        $existing = $entries[$relative] ?? null;
        if ($existing !== null && $existing['status'] === self::COPIED && $existing['sha256'] === $hash
            && $existing['disk'] === $diskName && $existing['destination'] === $destination
            && $this->destinationHash($disk, $destination) === $hash) {
            return $existing;
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return $this->append([...$entry, 'status' => self::UNREADABLE, 'error' => 'The source file could not be opened.']);
        }
        try {
            $written = $disk->writeStream($destination, $stream);
        } finally {
            fclose($stream);
        }
        if ($written === false || $this->destinationHash($disk, $destination) !== $hash) {
            if ($written !== false) {
                $disk->delete($destination);
            }

            return $this->append([...$entry, 'status' => self::FAILED, 'error' => 'The copied file does not match its source.']);
        }

        return $this->append([...$entry, 'status' => self::COPIED]);
    }

    /**
     * Latest entry per source path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return [];
        }
        $entries = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            try {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('The business-file ledger is corrupt.');
            }
            if (! is_array($entry) || ! is_string($entry['source'] ?? null) || ! is_string($entry['status'] ?? null)) {
                throw new RuntimeException('The business-file ledger is corrupt.');
            }
            $entries[$entry['source']] = $entry;
        }

        return $entries;
    }

    /**
     * Re-check every copied file: the destination exists and still matches, and
     * (when $dataRoot is given) the source still has the recorded hash. Any
     * non-copied entry is a problem. Feeds MigrationVerifier.
     *
     * @param  callable(string): Filesystem  $disks  disk name => disk
     * @param  (callable(int, int): void)|null  $progress  (entries checked so far, entries in all), for the page
     * @return array{checked: int, problems: list<array{source: string, problem: string}>, absent: list<string>}
     *                                                                                                           `absent`: sources a record names that are not in the legacy data at all (nothing was moved, nothing is lost)
     */
    public function verify(callable $disks, ?string $dataRoot = null, ?callable $progress = null): array
    {
        $problems = [];
        $absent = [];
        $checked = 0;
        $entries = $this->entries();
        $total = count($entries);
        foreach ($entries as $source => $entry) {
            $checked++;
            if ($progress !== null) {
                $progress($checked, $total);
            }
            // The legacy installation itself has no such file (a record names it, but it was purged or never
            // kept): there is nothing to move and nothing the administrator could fix, so it is reported, not a problem.
            if ($entry['status'] === self::MISSING) {
                $absent[] = $source;

                continue;
            }
            if ($entry['status'] === self::MOVING) {
                $problems[] = ['source' => $source, 'problem' => 'incomplete_move'];

                continue;
            }
            if (! self::isDone($entry)) {
                $problems[] = ['source' => $source, 'problem' => $entry['status']];

                continue;
            }
            // A moved file is gone from the source by design; only a copy has a source to compare.
            if ($dataRoot !== null && $entry['status'] === self::COPIED) {
                $path = $this->resolveSource($dataRoot, $source, $scratch);
                if ($path === null) {
                    $problems[] = ['source' => $source, 'problem' => 'source_'.$scratch['status']];
                } elseif (hash_file('sha256', $path) !== $entry['sha256']) {
                    $problems[] = ['source' => $source, 'problem' => 'source_changed'];
                }
            }
            $disk = $disks((string) $entry['disk']);
            if (! $disk->exists($entry['destination'])) {
                $problems[] = ['source' => $source, 'problem' => 'destination_missing'];
            } elseif ($this->destinationHash($disk, $entry['destination']) !== $entry['sha256']) {
                $problems[] = ['source' => $source, 'problem' => 'destination_hash_mismatch'];
            }
        }

        return ['checked' => $checked, 'problems' => $problems, 'absent' => $absent];
    }

    /** Resolve a source below $dataRoot without following links out of it; on failure fills $entry status/error and returns null. */
    private function resolveSource(string $dataRoot, string $relative, ?array &$entry): ?string
    {
        $entry ??= [];
        $root = realpath($dataRoot);
        $full = $dataRoot.'/'.$relative;
        if ($root === false || (! file_exists($full) && ! is_link($full))) {
            $entry['status'] = self::MISSING;
            $entry['error'] = 'The source file does not exist.';

            return null;
        }
        $real = realpath($full);
        if (is_link($full) || $real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($real) || ! is_readable($real)) {
            $entry['status'] = self::UNREADABLE;
            $entry['error'] = 'The source is a link, escapes the data directory, or cannot be read.';

            return null;
        }

        return $real;
    }

    private function destinationHash(Filesystem $disk, string $destination): ?string
    {
        if (! $disk->exists($destination)) {
            return null;
        }
        $stream = $disk->readStream($destination);
        if (! is_resource($stream)) {
            return null;
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    public function normalise(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1 || preg_match('#^[A-Za-z]:#', $path) === 1) {
            throw new RuntimeException('Invalid legacy file path.');
        }

        return $path;
    }

    private function normaliseDestination(string $path): string
    {
        $path = $this->normalise($path);
        if (str_ends_with($path, '/')) {
            throw new RuntimeException('Invalid legacy file destination.');
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function append(array $entry): array
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cannot create the ledger directory.');
        }
        $entry['recorded_at'] = gmdate('c');
        if ($this->index !== null) {
            $this->index[$entry['source']] = $entry;
        }
        if (file_put_contents($this->path(), json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Cannot write the business-file ledger.');
        }

        return $entry;
    }

    private function path(): string
    {
        return $this->directory.'/file-ledger.jsonl';
    }
}
