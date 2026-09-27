<?php

namespace App\Models;

use App\Services\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One use of a stored content under a name — a `utils_filestorage` row. A
 * note's file or an e-mail attachment holds one of these; two of them with the
 * same bytes share a StoredFileContent, so the file is on disk once. Deleting
 * the last StoredFile of a content removes the content too.
 *
 * Create them through App\Services\FileStorage, never directly.
 */
class StoredFile extends Model
{
    protected $fillable = [
        'content_id',
        'name',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (StoredFile $file) => app(FileStorage::class)->release($file->content_id));
    }

    /**
     * @return BelongsTo<StoredFileContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(StoredFileContent::class, 'content_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOnDisk(): bool
    {
        return $this->content !== null && FileStorage::disk()->exists($this->content->path());
    }

    public function read(): ?string
    {
        return $this->content ? FileStorage::disk()->get($this->content->path()) : null;
    }

    public function size(): int
    {
        return $this->content->size ?? 0;
    }

    public function mimeType(): ?string
    {
        return $this->content?->mime_type;
    }

    /**
     * Safe to serve with `Content-Disposition: inline` for a "View" action —
     * a browser renders it directly rather than downloading it. Deliberately
     * a small allow-list rather than "anything but a blocklist": SVG carries
     * `<script>` same as HTML/XML does, and inline-rendering that from this
     * app's own origin (with the viewer's session cookie) would be a stored
     * XSS vector, so it's excluded even though it is, visually, an image.
     */
    public function isPreviewable(): bool
    {
        $mime = $this->mimeType();

        return match (true) {
            $mime === null => false,
            $mime === 'image/svg+xml' => false,
            str_starts_with($mime, 'image/'),
            str_starts_with($mime, 'video/'),
            str_starts_with($mime, 'audio/') => true,
            default => in_array($mime, ['application/pdf', 'text/plain', 'text/csv', 'application/json'], true),
        };
    }

    /** Another use of the same content under its own name — Epesi's add_files() clone. */
    public function copy(?string $name = null): self
    {
        return app(FileStorage::class)->reuse($this->content, $name ?? $this->name);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function response(?string $name = null, array $headers = [], string $disposition = 'attachment'): StreamedResponse
    {
        return FileStorage::disk()->response($this->content->path(), $name ?? $this->name, $headers, $disposition);
    }

    public function download(?string $name = null): StreamedResponse
    {
        return FileStorage::disk()->download($this->content->path(), $name ?? $this->name);
    }
}
