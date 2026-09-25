<?php

namespace Epesi\Modules\Mail\Models;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * One attachment of an archived message. Its bytes are a StoredFile in the
 * file storage (App\Services\FileStorage), so the same file on many messages
 * is on disk once.
 */
class MailAttachment extends Model
{
    protected $table = 'epesi_mail_attachments';

    protected $fillable = [
        'mail_id',
        'name',
        'mime_type',
        'size',
        'content_id',
        'inline',
        'stored_file_id',
    ];

    protected function casts(): array
    {
        return [
            'inline' => 'boolean',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (MailAttachment $attachment) => $attachment->storedFile?->delete());
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    /**
     * @return BelongsTo<Mail, $this>
     */
    public function mail(): BelongsTo
    {
        return $this->belongsTo(Mail::class);
    }

    public function url(bool $inline = false): string
    {
        if ($inline) {
            // Signed and short-lived: the body viewer is a sandboxed iframe
            // with an opaque origin, so the browser sends no session cookie
            // with its image requests — the signature is the authorisation.
            return URL::temporarySignedRoute('epesi.mail.attachment', now()->addHour(), [
                'mail' => $this->mail_id,
                'attachment' => $this->getKey(),
                'inline' => 1,
            ]);
        }

        return route('epesi.mail.attachment', ['mail' => $this->mail_id, 'attachment' => $this->getKey()]);
    }

    public function humanSize(): string
    {
        $size = $this->size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $size : number_format($size, 1)).' '.$unit;
            }
            $size /= 1024;
        }

        return $size.' B';
    }
}
