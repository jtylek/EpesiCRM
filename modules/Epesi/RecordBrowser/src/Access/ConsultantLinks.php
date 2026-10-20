<?php

namespace Epesi\Modules\RecordBrowser\Access;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Which contacts and companies a consultant sees: those linked directly to a
 * record he sees himself — a customer or employee of his task, the owner of
 * his ticket, the manager of his project. Not one step further: a company
 * isn't his because a contact of it is.
 *
 * Each module names its own links from its service provider's boot(), the
 * way it registers a calendar provider (see the Tasks module), so this class
 * never needs to know which recordsets exist. The relations are resolved
 * only when a consultant's query runs.
 */
class ConsultantLinks
{
    /** @var array<int, array{0: class-string<Model>, 1: string}> */
    protected static array $links = [];

    /**
     * The records of $sourceClass a consultant sees make what these
     * relations point at visible to him too. BelongsToMany (MorphToMany
     * included), BelongsTo and MorphTo relations are understood.
     *
     * @param  class-string<Model>  $sourceClass
     */
    public static function register(string $sourceClass, string ...$relations): void
    {
        foreach ($relations as $relation) {
            if (! in_array([$sourceClass, $relation], static::$links, true)) {
                static::$links[] = [$sourceClass, $relation];
            }
        }
    }

    /**
     * Adds "or linked to one of the consultant's records" to a query of
     * $query's model. It runs inside that model's ownership scope, so the
     * source records' own queries (scoped to the consultant) are subqueries.
     */
    public static function orWhereLinked(Builder $query): void
    {
        $target = $query->getModel();
        $key = $query->qualifyColumn($target->getKeyName());

        foreach (static::$links as [$sourceClass, $name]) {
            $ids = static::linkedIds($sourceClass, $name, $target);

            if ($ids !== null) {
                $query->orWhereIn($key, $ids);
            }
        }
    }

    /**
     * Every record type a consultant may see some records of: the sources
     * and the models their relations point at (not MorphTo's, which may be
     * anything — the other relations already name them).
     *
     * @return array<int, class-string<Model>>
     */
    public static function recordTypes(): array
    {
        $types = [];

        foreach (static::$links as [$sourceClass, $name]) {
            $types[] = $sourceClass;
            $relation = static::relation($sourceClass, $name);

            if (! $relation instanceof MorphTo) {
                $types[] = $relation->getRelated()::class;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Narrows a query of polymorphic links (a note's, a mail's) to those
     * pointing at a record the consultant sees — or to none, when no
     * recordset has registered any links.
     */
    public static function whereVisibleRecord(Builder $links, string $typeColumn, string $idColumn): void
    {
        $types = static::recordTypes();

        if ($types === []) {
            $links->whereRaw('1 = 0');

            return;
        }

        $links->where(function (Builder $links) use ($types, $typeColumn, $idColumn) {
            foreach ($types as $class) {
                $record = new $class;

                $links->orWhere(fn (Builder $link) => $link
                    ->where($link->qualifyColumn($typeColumn), $record->getMorphClass())
                    ->whereIn($link->qualifyColumn($idColumn), $class::query()->select($record->getQualifiedKeyName())));
            }
        });
    }

    /** For tests: forget every registered link. */
    public static function flush(): void
    {
        static::$links = [];
    }

    /**
     * The ids of $target's records that $sourceClass's relation links to,
     * from the source records the consultant sees; null when the relation
     * doesn't point at $target's model.
     *
     * @param  class-string<Model>  $sourceClass
     */
    protected static function linkedIds(string $sourceClass, string $name, Model $target): Builder|QueryBuilder|null
    {
        $relation = static::relation($sourceClass, $name);
        $source = $relation->getParent();
        $visible = $sourceClass::query()->select($source->getQualifiedKeyName());

        if ($relation instanceof MorphTo) {
            return $sourceClass::query()
                ->where($source->qualifyColumn($relation->getMorphType()), $target->getMorphClass())
                ->select($source->qualifyColumn($relation->getForeignKeyName()));
        }

        if (! $relation->getRelated() instanceof $target) {
            return null;
        }

        if ($relation instanceof BelongsTo) {
            return $sourceClass::query()->select($relation->getQualifiedForeignKeyName());
        }

        if ($relation instanceof BelongsToMany) {
            // The relation's own query: the pivot join and its pivot values
            // (the shared link table's `field` and `target_type`). Without the
            // related model's scopes — its ownership scope is the one being
            // built, and would never end.
            $query = $relation->getQuery()
                ->withoutGlobalScopes()
                ->select($relation->getQualifiedRelatedPivotKeyName())
                ->whereIn($relation->getQualifiedForeignPivotKeyName(), $visible);

            if ($relation instanceof MorphToMany) {
                $query->where($relation->qualifyPivotColumn($relation->getMorphType()), $relation->getMorphClass());
            }

            return $query->toBase();
        }

        return null;
    }

    /**
     * The relation as built on a blank model, without the parent's key
     * constraint (a MorphToMany's morph type goes with it; linkedIds() puts
     * it back).
     *
     * @param  class-string<Model>  $sourceClass
     */
    protected static function relation(string $sourceClass, string $name): Relation
    {
        return Relation::noConstraints(fn () => (new $sourceClass)->{$name}());
    }
}
