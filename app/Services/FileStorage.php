<?php

namespace App\Services;

use App\Models\StoredFile;
use App\Models\StoredFileContent;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Port of Utils/FileStorage: every file a note or an e-mail carries is kept
 * once, however many times it is attached. The content goes to the
 * `filestorage` disk under its sha512, with Epesi's layout — the first five
 * hex digits one directory each, the rest as the file name — so a legacy
 * `data/Utils_FileStorage` tree and this one name the same bytes the same way.
 *
 * A StoredFileContent is the content; a StoredFile is one use of it under a
 * name, and is what a module keeps. Storing bytes that are already there adds
 * only a StoredFile. Deleting the last StoredFile of a content deletes the
 * content and its file (release()), which Epesi left to an administrator.
 */
class FileStorage
{
    public const DISK = 'filestorage';

    public const ALGORITHM = 'sha512';

    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(self::DISK);
    }

    public static function pathFor(string $hash): string
    {
        return implode('/', str_split(substr($hash, 0, 5))).'/'.substr($hash, 5);
    }

    public function put(string $contents, string $name): StoredFile
    {
        return $this->store(hash(self::ALGORITHM, $contents), $name, fn (string $path): bool => static::disk()->put($path, $contents));
    }

    /** A file on the local filesystem, streamed rather than read into memory. */
    public function putFile(string $file, ?string $name = null): StoredFile
    {
        $hash = hash_file(self::ALGORITHM, $file) ?: throw new RuntimeException("Could not read {$file}.");

        return $this->store($hash, $name ?? basename($file), function (string $path) use ($file): bool {
            $stream = fopen($file, 'rb');

            try {
                return static::disk()->writeStream($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        });
    }

    /** An upload — a form's, or a Livewire temporary one — under the name it was sent with. */
    public function putUpload(UploadedFile $file): StoredFile
    {
        $name = $file->getClientOriginalName();
        $path = $file->getRealPath();

        return is_string($path) && is_file($path) ? $this->putFile($path, $name) : $this->put((string) $file->get(), $name);
    }

    /** One more use of a content already stored. */
    public function reuse(StoredFileContent $content, string $name): StoredFile
    {
        return StoredFile::create(['content_id' => $content->getKey(), 'name' => $name, 'created_by' => Auth::id()]);
    }

    /**
     * Deletes a content once no StoredFile uses it — called whenever one is
     * deleted. Epesi's delete_orphaned_file(), also tidying the directories the
     * file leaves empty.
     */
    public function release(int $contentId): void
    {
        $content = StoredFileContent::query()->find($contentId);

        if ($content === null) {
            return;
        }

        $this->locked($content->hash, function () use ($content): void {
            if ($content->files()->exists()) {
                return;
            }

            try {
                $content->delete();
            } catch (QueryException) {
                // A StoredFile created in a transaction this connection can't
                // see yet: the foreign key refused, so the content stays.
                return;
            }

            $path = $content->path();
            static::disk()->delete($path);

            for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
                if (static::disk()->files($dir) !== [] || static::disk()->directories($dir) !== []) {
                    break;
                }

                static::disk()->deleteDirectory($dir);
            }
        });
    }

    /**
     * @param  Closure(string): bool  $write  puts the content at the path given
     */
    protected function store(string $hash, string $name, Closure $write): StoredFile
    {
        return $this->locked($hash, function () use ($hash, $name, $write): StoredFile {
            $path = static::pathFor($hash);

            // Already on disk, row or not: a content whose row was rolled back
            // with the transaction that stored it is simply reused.
            if (! static::disk()->exists($path) && ! $write($path)) {
                throw new RuntimeException("Could not write \"{$name}\" to the file storage.");
            }

            // From the bytes; for what they don't tell apart (plain text
            // among them), from the extension of the first name it came
            // under, as Epesi's get_mime_type() did.
            $content = StoredFileContent::query()->firstOrCreate(['hash' => $hash], [
                'size' => static::disk()->size($path),
                'mime_type' => static::disk()->mimeType($path)
                    ?: (MimeTypes::getDefault()->getMimeTypes(strtolower(pathinfo($name, PATHINFO_EXTENSION)))[0] ?? null),
            ]);

            return $this->reuse($content, $name);
        });
    }

    /**
     * Storing and releasing the same content never overlap, so a content is
     * never deleted from under a file that is just being stored with it.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function locked(string $hash, Closure $callback): mixed
    {
        return Cache::lock('filestorage:'.substr($hash, 0, 64), 30)->block(15, $callback);
    }
}
