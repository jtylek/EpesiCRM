<?php

namespace App\Models\Concerns;

use App\Models\StoredFileLink;
use App\Support\Files\InlineImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A model whose rich text can show pasted images (App\Support\Files\InlineImages).
 * A rich editor on a form of this model takes images; one on any other model
 * doesn't, since nothing would record which record holds them.
 *
 * Saving the record links the images its text names to it. Taking an image
 * out of the text keeps the link, because an earlier version in History still
 * shows it and can be restored; the links and their files go when the record
 * is deleted for good. The model needs a morph alias, like every polymorphic
 * owner here.
 */
trait HasInlineImages
{
    /**
     * The columns holding rich text (HTML or Markdown) that may show images.
     *
     * @return list<string>
     */
    abstract public function richTextColumns(): array;

    public static function bootHasInlineImages(): void
    {
        static::saved(function (Model $record): void {
            /** @var Model&self $record */
            if ($record->isDirty($record->richTextColumns())) {
                app(InlineImages::class)->sync($record);
            }
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted(fn (Model $record) => app(InlineImages::class)->release($record));
        } else {
            static::deleted(fn (Model $record) => app(InlineImages::class)->release($record));
        }
    }

    /**
     * @return MorphMany<StoredFileLink, $this>
     */
    public function inlineImageLinks(): MorphMany
    {
        return $this->morphMany(StoredFileLink::class, 'owner');
    }
}
