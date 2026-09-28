<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use Epesi\Modules\RecordBrowser\History\MergeIntoCollectionEntry;
use Epesi\Modules\RecordBrowser\History\SaveActivity;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Recordset\CollectionFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Spatie\Activitylog\ActivityLogStatus;

/**
 * The items of a model's collection fields (Field::collection()): its
 * addresses, each a row of the collection type's own table, owned by this
 * record alone. HasCustomFields uses this trait, so every recordset that takes
 * custom fields takes collections, an administrator's included.
 *
 * - One relation per collection field, named after it: `$contact->addresses`
 *   is a morphMany over the Address table, held to `field = 'addresses'` and
 *   ordered by position, so `->first()` is the primary one. Filament's dot
 *   notation (`addresses.city`) works on it in global search.
 * - One way to write, syncCollection(), which also writes the History entry:
 *   LogsActivity sees only the record's own columns.
 * - Deleting the record for good deletes its items; a soft delete keeps them,
 *   so Restore brings them back, as HasRecordLinks does with links.
 *
 * Which fields a model has comes from its recordset (CollectionFields).
 */
trait HasCollections
{
    public static function bootHasCollections(): void
    {
        static::deleted(function (Model $record): void {
            // An item is no owner, and asking every type's table on each
            // item deleted would cost a query per type.
            if ($record instanceof CollectionItem) {
                return;
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($record), true) && ! $record->isForceDeleting()) {
                return;
            }

            foreach (CollectionItem::types() as $type) {
                $type::query()
                    ->where('owner_type', $record->getMorphClass())
                    ->where('owner_id', $record->getKey())
                    ->delete();
            }
        });

        // A save that changes a collection and the record's own fields makes
        // one History entry (SaveActivity). Once per class: models boot again
        // in every test, and the list of pipes is static.
        if (method_exists(static::class, 'addLogChange')) {
            foreach (static::$changesPipes as $pipe) {
                if ($pipe instanceof MergeIntoCollectionEntry) {
                    return;
                }
            }

            static::addLogChange(new MergeIntoCollectionEntry);
        }
    }

    /**
     * Each collection field's relation, registered on every instance as
     * HasCustomFields registers a link field's: an administrator can add a
     * collection within the process, and the resolver is only an array entry.
     */
    public function initializeHasCollections(): void
    {
        foreach (array_keys(CollectionFields::for(static::class)) as $name) {
            static::resolveRelationUsing($name, fn (Model $record): MorphMany => $record->collection($name));
        }
    }

    /**
     * The items of the collection field $field, primary first.
     *
     * @return MorphMany<CollectionItem, $this>
     */
    public function collection(string $field): MorphMany
    {
        $type = CollectionFields::field(static::class, $field)?->collectionType()
            ?? throw new InvalidArgumentException(static::class." has no collection field \"{$field}\".");

        return $this->morphMany($type, 'owner')
            ->where('field', $field)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Makes the collection field $field hold exactly $items, in their order:
     * an item carrying the id of one of this field's items updates it, any
     * other is added, and those left out are deleted. An item with no value
     * but its kind (an empty card on the form) is left out. Only the item's
     * own values are taken (CollectionItem's fillable), never its owner or
     * position, so an id from somewhere else can't reach another record's
     * item.
     *
     * A change writes one History entry on this record, the items as
     * one-line summaries before and after (CollectionItem::historyLine()),
     * joining the entry of the same save when there is one (SaveActivity).
     * A save that changes nothing logs nothing.
     *
     * @param  iterable<array<string, mixed>>  $items
     */
    public function syncCollection(string $field, iterable $items): void
    {
        if (! $this->exists) {
            throw new LogicException('A record is saved before its collections are.');
        }

        $relation = $this->collection($field);
        $type = $relation->getRelated()::class;

        DB::transaction(function () use ($field, $items, $relation, $type): void {
            $existing = $relation->get()->keyBy(fn (CollectionItem $item): int => (int) $item->getKey());
            $before = $existing->map(fn (CollectionItem $item): string => $item->historyLine())->values()->all();
            $changed = false;
            $kept = [];
            $position = 0;

            foreach ($items as $data) {
                $data = (array) $data;
                $item = new $type;
                $values = array_intersect_key($data, array_flip($item->getFillable()));

                if (! $this->collectionItemHasValues($values)) {
                    continue;
                }

                $id = is_numeric($data['id'] ?? null) ? (int) $data['id'] : null;

                if ($id !== null && $existing->has($id) && ! isset($kept[$id])) {
                    $item = $existing->get($id);
                } else {
                    $item->forceFill([
                        'owner_type' => $this->getMorphClass(),
                        'owner_id' => $this->getKey(),
                        'field' => $field,
                    ]);
                }

                $item->fill($values);
                $item->position = ++$position;

                if (! $item->exists || $item->isDirty()) {
                    $item->save();
                    $changed = true;
                }

                $kept[(int) $item->getKey()] = $item;
            }

            foreach ($existing as $id => $item) {
                if (! isset($kept[$id])) {
                    $item->delete();
                    $changed = true;
                }
            }

            $this->unsetRelation($field);

            if (! $changed) {
                return;
            }

            if ($this->usesTimestamps()) {
                $this->touchQuietly();
            }

            $after = array_values(array_map(fn (CollectionItem $item): string => $item->historyLine(), $kept));

            if ($before !== $after) {
                $this->logCollectionChange($field, $before, $after);
            }
        });
    }

    /**
     * Anything besides the kind: a blank string, an empty list or an unticked
     * checkbox doesn't count.
     *
     * @param  array<string, mixed>  $values
     */
    protected function collectionItemHasValues(array $values): bool
    {
        foreach ($values as $column => $value) {
            if ($column === 'kind' || $value === null || $value === false || $value === [] || (is_string($value) && trim($value) === '')) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    protected function logCollectionChange(string $field, array $before, array $after): void
    {
        if (! method_exists($this, 'getActivitylogOptions') || ! $this->enableLoggingModelsEvents || app(ActivityLogStatus::class)->disabled()) {
            return;
        }

        $history = app(SaveActivity::class);

        if ($entry = $history->entryFor($this)) {
            $properties = $entry->properties->toArray();
            $properties['attributes'] = [...($properties['attributes'] ?? []), $field => $after];

            // A record just created had nothing before.
            if ($entry->event === 'updated') {
                $properties['old'] = [...($properties['old'] ?? []), $field => $before];
            }

            $entry->properties = collect($properties);
            $entry->save();

            return;
        }

        $logged = activity($this->getActivitylogOptions()->logName ?: config('activitylog.default_log_name'))
            ->performedOn($this)
            ->event('updated')
            ->withProperties(['old' => [$field => $before], 'attributes' => [$field => $after]])
            ->log('updated');

        if ($logged !== null) {
            $history->markCollectionEntry($this);
        }
    }
}
