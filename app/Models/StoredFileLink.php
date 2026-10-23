<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An image pasted into a rich-text editor, and the record whose text holds it
 * (App\Support\Files\InlineImages). The image route serves a StoredFile only
 * to someone who may view one of its owners.
 *
 * A row with no owner is an upload not saved with a record yet: only its
 * uploader can see it, and it is pruned after a day if it stays unclaimed.
 */
class StoredFileLink extends Model
{
    protected $fillable = [
        'stored_file_id',
        'owner_type',
        'owner_id',
    ];

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->owner_type === null;
    }
}
