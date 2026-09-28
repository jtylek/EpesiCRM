<?php

namespace Epesi\Modules\RecordBrowser\History;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\CauserResolver;
use WeakMap;

/**
 * The History entry a record was last logged under while this instance of it
 * is being saved, so a save that changes a collection as well as the record's
 * own fields makes one entry, not two (HasCollections).
 *
 * Filament saves a form's collections around the record: after creating it
 * (Create), but before updating it (Edit). So whichever comes second joins
 * the entry the first one made: a collection change joins the record's
 * "created" or "updated" entry, and the record's own changes join an entry a
 * collection change made (MergeIntoCollectionEntry).
 *
 * Kept per model instance, not per record id: Filament saves one instance, and
 * the next save — the next request, or the next Livewire call in a test —
 * loads a new one, which starts an entry of its own. A WeakMap, so an entry is
 * forgotten with its instance and a long import holds none of them.
 */
class SaveActivity
{
    /** @var WeakMap<Model, array{id: int|string, event: ?string, collection: bool}> */
    protected WeakMap $entries;

    public function __construct()
    {
        $this->entries = new WeakMap;
    }

    /** Every new activity row: the instance it was logged on (performedOn()) is its subject. */
    public function remember(Model $activity): void
    {
        $subject = $activity->relationLoaded('subject') ? $activity->getRelation('subject') : null;

        if ($subject instanceof Model) {
            $this->entries[$subject] = ['id' => $activity->getKey(), 'event' => $activity->event, 'collection' => false];
        }
    }

    /** The entry $record got was a collection change's own (HasCollections::syncCollection()). */
    public function markCollectionEntry(Model $record): void
    {
        if (isset($this->entries[$record])) {
            $this->entries[$record] = ['collection' => true] + $this->entries[$record];
        }
    }

    /**
     * The "created" or "updated" entry this instance was logged under, when
     * the same user made it; with $collectionOnly, only one a collection
     * change made.
     */
    public function entryFor(Model $record, bool $collectionOnly = false): ?Model
    {
        $entry = $this->entries[$record] ?? null;

        if ($entry === null || ! in_array($entry['event'], ['created', 'updated'], true) || ($collectionOnly && ! $entry['collection'])) {
            return null;
        }

        $activity = ActivitylogServiceProvider::determineActivityModel()::query()->find($entry['id']);

        if ($activity === null) {
            return null;
        }

        $causer = app(CauserResolver::class)->resolve();

        return (string) $activity->causer_type === (string) $causer?->getMorphClass()
            && (string) $activity->causer_id === (string) $causer?->getKey()
            ? $activity
            : null;
    }
}
