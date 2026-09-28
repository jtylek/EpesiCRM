<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The links of a model's "link to any record" fields (Field::related()), kept
 * in one shared table (RecordLink) rather than a pivot per field, so a field an
 * administrator adds needs no DDL. HasCustomFields uses this trait, so such a
 * field works on every recordset that takes custom fields.
 *
 * A linked record is always loaded through its own model's query, so its
 * ownership scope applies: a record the user can't see is left out of what
 * they're shown, and left linked when they save — a link nobody can see can't
 * be removed by accident.
 */
trait HasRecordLinks
{
    public static function bootHasRecordLinks(): void
    {
        // A soft-deleted record keeps its links, so Restore brings them back.
        static::deleted(function (self $record): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($record), true) && ! $record->isForceDeleting()) {
                return;
            }

            $record->recordLinks()->delete();
        });
    }

    /**
     * Every link of every field; filter by `field`.
     *
     * @return MorphMany<RecordLink, $this>
     */
    public function recordLinks(): MorphMany
    {
        return $this->morphMany(RecordLink::class, 'source');
    }

    /**
     * The records $field links to that this user may see, in the order they
     * were linked. Uses the loaded `recordLinks` when there are some — a list
     * page loads a whole page's at once (Field::preloadRecordLinks()).
     *
     * @return Collection<int, Model>
     */
    public function linkedRecords(string $field): Collection
    {
        $links = $this->relationLoaded('recordLinks')
            ? $this->recordLinks
            : $this->recordLinks()->where('field', $field)->orderBy('id')->get();

        $links = $links->where('field', $field)->sortBy('id')->values();
        $links->loadMissing('target');

        return new Collection($links->map(fn (RecordLink $link): ?Model => $link->target)->filter()->values()->all());
    }

    /**
     * Makes $field link to exactly the records $tokens name ("alias:id"),
     * as far as this user can see: a link to a record they can't see stays,
     * since they couldn't have meant to remove it, and a token naming such a
     * record — or a recordset outside $recordsets — is ignored.
     *
     * @param  list<string>  $tokens
     * @param  list<string>|null  $recordsets  morph aliases the field may link to, null for any
     */
    public function syncRecordLinks(string $field, array $tokens, ?array $recordsets = null): void
    {
        $wanted = array_values(array_unique(array_filter($tokens, fn (mixed $token): bool => RecordLink::parseToken($token) !== null)));

        $existing = $this->recordLinks()->where('field', $field)->with('target')->get();
        $existingTokens = $existing->map(fn (RecordLink $link): string => $link->token())->all();

        $existing
            ->filter(fn (RecordLink $link): bool => $link->target !== null && ! in_array($link->token(), $wanted, true))
            ->each->delete();

        foreach (array_diff($wanted, $existingTokens) as $token) {
            [$alias, $id] = RecordLink::parseToken($token);

            if ($recordsets !== null && ! in_array($alias, $recordsets, true)) {
                continue;
            }

            $class = Relation::getMorphedModel($alias);

            // Through the target's own query: its ownership scope decides
            // whether this user may link to it at all.
            if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! $class::query()->whereKey($id)->exists()) {
                continue;
            }

            $this->recordLinks()->create(['field' => $field, 'target_type' => $alias, 'target_id' => $id]);
        }

        $this->unsetRelation('recordLinks');
    }
}
