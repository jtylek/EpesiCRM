<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use App\Models\User;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

/**
 * Picking one record of any linkable recordset in two steps — the
 * recordset, then the record: what the Linked to field's popup
 * (LinkedToField) offers. Every record comes through its own model's query,
 * so the ownership scope leaves out one the user can't see.
 */
class RecordPicker
{
    /**
     * The recordsets of $aliases that are linkable, by label, each after its
     * icon (HTML, for a select that allows it).
     *
     * @param  list<string>  $aliases
     * @return array<string, string> alias => icon and label
     */
    public static function recordsetOptions(array $aliases): array
    {
        $options = [];

        foreach (LinkableRecordsets::options() as $alias => $label) {
            if (in_array($alias, $aliases, true)) {
                $options[$alias] = LinkableRecordsets::iconLabel($alias, $label);
            }
        }

        return $options;
    }

    /** Whether the recordset keeps a recent list per user, which the picker shows before anything is typed. */
    public static function hasRecent(string $alias): bool
    {
        $resource = LinkableRecordsets::resource($alias);

        return $resource !== null && is_subclass_of($resource, RecordsetResource::class) && $resource::getRecentLimit() > 0;
    }

    /**
     * The records of $alias this user opened last, the latest first — empty
     * for a recordset without a recent list.
     *
     * @param  list<int|string>  $except  keys left out (the record being edited, those already linked)
     * @return array<int|string, string> key => title
     */
    public static function recent(string $alias, array $except = [], int $limit = 10): array
    {
        $user = Auth::user();
        $class = static::model($alias);

        if ($class === null || ! static::hasRecent($alias) || ! $user instanceof User) {
            return [];
        }

        $visits = RecentRecords::visitsOf($user, $alias);
        arsort($visits);
        $ids = array_slice(array_values(array_diff(array_keys($visits), $except)), 0, $limit);

        if ($ids === []) {
            return [];
        }

        return $class::query()->whereKey($ids)->get()
            ->sortBy(fn (Model $record): int|false => array_search($record->getKey(), $ids))
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => LinkableRecordsets::title($record)])
            ->all();
    }

    /**
     * Records of $alias matching $search, by the columns its pickers search.
     *
     * @param  list<int|string>  $except
     * @return array<int|string, string> key => title
     */
    public static function search(string $alias, string $search, array $except = [], int $limit = 25): array
    {
        $class = static::model($alias);
        $resource = LinkableRecordsets::resource($alias);

        if ($class === null || $resource === null || ! is_subclass_of($resource, RecordsetResource::class) || blank($search)) {
            return [];
        }

        return $resource::applyPickerSearch($class::query(), $search)
            ->when($except !== [], fn (Builder $query): Builder => $query->whereKeyNot($except))
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => LinkableRecordsets::title($record)])
            ->all();
    }

    /** The record's title, or null for one that doesn't exist or this user can't see. */
    public static function title(string $alias, mixed $key): ?string
    {
        $record = static::find($alias, $key);

        return $record ? LinkableRecordsets::title($record) : null;
    }

    public static function find(string $alias, mixed $key): ?Model
    {
        $class = static::model($alias);

        return $class !== null && filled($key) ? $class::query()->find($key) : null;
    }

    /** @return class-string<Model>|null */
    protected static function model(string $alias): ?string
    {
        $class = Relation::getMorphedModel($alias);

        return is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
