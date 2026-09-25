<?php

namespace Epesi\Modules\Attachments\Models;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One note — the port of a `utils_attachment` record. Visibility is the same
 * rule every CRM record follows (HasOwnershipVisibility): anyone may see it
 * unless it is Private, in which case only its author (and managers) can,
 * i.e. Epesi's `'(!permission' => 2, '|:Created_by' => 'USER_ID'` view crit.
 */
class Attachment extends Model
{
    use HasOwnershipVisibility, LogsActivity, SoftDeletes;

    protected $table = 'epesi_attachments';

    protected $fillable = [
        'title',
        'note',
        'files',
        'permission',
        'sticky',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'permission' => RecordPermission::class,
            'sticky' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // `files` holds StoredFile ids (see App\Services\FileStorage), as
        // strings: that is the FileUpload field's state, and its tamper check
        // only recognises a note's own files when they compare equal.
        static::saving(function (Attachment $note): void {
            if ($note->files !== null) {
                $note->files = array_values(array_map('strval', $note->files));
            }
        });

        // A file taken off a note leaves the file storage too, unless another
        // note or e-mail holds the same content.
        static::updated(function (Attachment $note): void {
            if ($note->wasChanged('files')) {
                $before = json_decode((string) $note->getRawOriginal('files'), true) ?: [];
                StoredFile::query()->whereKey(array_diff($before, $note->files ?? []))->get()->each->delete();
            }
        });

        static::forceDeleted(fn (Attachment $note) => $note->storedFiles()->each->delete());
    }

    /**
     * Where this note is attached — usually one record, more than one when a
     * note was attached to several (Follow-up's tracing notes).
     *
     * @return HasMany<AttachmentLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(AttachmentLink::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Attach this note to one more record. Idempotent.
     */
    public function attachTo(Model $record): void
    {
        $this->links()->firstOrCreate([
            'attachable_type' => $record->getMorphClass(),
            'attachable_id' => $record->getKey(),
        ]);
    }

    /**
     * Create a plain note on $record — the port of
     * Utils_AttachmentCommon::add(), used by other modules (Follow-up) to
     * leave a trail on a record.
     */
    public static function addTo(Model $record, string $note, ?string $title = null, RecordPermission $permission = RecordPermission::Public): static
    {
        $attachment = static::create([
            'title' => $title,
            'note' => $note,
            'permission' => $permission,
        ]);

        $attachment->attachTo($record);

        return $attachment;
    }

    /**
     * The label a note goes by where there is no room for its body — its
     * title, or the start of its text.
     */
    public function label(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        $text = trim(html_entity_decode(strip_tags((string) $this->note)));

        return $text === '' ? 'Note #'.$this->getKey() : Str::limit($text, 60);
    }

    /**
     * The note's files, in order.
     *
     * @return Collection<int, StoredFile>
     */
    public function storedFiles(): Collection
    {
        $ids = array_map('strval', array_values($this->files ?? []));
        $files = StoredFile::query()->whereKey($ids)->get()->keyBy(fn (StoredFile $file): string => (string) $file->getKey());

        return collect($ids)->map(fn (string $id): ?StoredFile => $files->get($id))->filter()->values();
    }

    /**
     * The names of the note's files, in order.
     *
     * @return array<int, string>
     */
    public function fileList(): array
    {
        return $this->storedFiles()->map(fn (StoredFile $file): string => $file->name)->all();
    }

    protected static function applyExtraVisibility(Builder $query, User $user): void
    {
        //
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['title', 'note', 'permission', 'sticky', 'files'])
            ->useLogName('attachment');
    }
}
