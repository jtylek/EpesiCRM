<?php

namespace Epesi\Modules\RecordBrowser\Browsing;

use App\Models\User;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The records each user opened last — Epesi's `<tab>_recent` tables. A
 * recordset turns them on by giving `$recordsetRecent` a size; the list
 * then keeps that many per user, the newest first.
 */
class RecentRecords
{
    /**
     * What the View and Edit pages call on opening a record. Does nothing for
     * a resource that keeps no recent list.
     *
     * @param  class-string  $resource
     */
    public static function opened(string $resource, Model $record): void
    {
        $limit = is_subclass_of($resource, RecordsetResource::class) ? $resource::getRecentLimit() : 0;
        $user = Auth::user();

        if ($limit > 0 && $user instanceof User) {
            static::visit($user, $record, $limit);
        }
    }

    /**
     * Put $record at the top of $user's list for its type and drop whatever
     * falls past $limit — Utils_RecordBrowserCommon::add_recent_entry().
     */
    public static function visit(User $user, Model $record, int $limit): void
    {
        $type = $record->getMorphClass();

        DB::table('epesi_recordbrowser_recent')->upsert(
            [[
                'user_id' => $user->id,
                'recordable_type' => $type,
                'recordable_id' => $record->getKey(),
                'visited_at' => now(),
            ]],
            ['user_id', 'recordable_type', 'recordable_id'],
            ['visited_at'],
        );

        $kept = static::rows($user, $type)
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        static::rows($user, $type)->whereNotIn('id', $kept)->delete();
    }

    /**
     * @return array<int|string, string> record key => when it was last opened
     */
    public static function visitsOf(User $user, string $recordType): array
    {
        return static::rows($user, $recordType)->pluck('visited_at', 'recordable_id')->all();
    }

    /**
     * Narrow $query to the records on $user's list, the latest visit first
     * unless the caller is sorting by something else.
     */
    public static function onlyRecent(Builder $query, User $user, bool $latestFirst = true): Builder
    {
        $model = $query->getModel();
        $type = $model->getMorphClass();

        $query->whereIn($model->getQualifiedKeyName(), static::rows($user, $type)->select('recordable_id'));

        if ($latestFirst) {
            $query->orderByDesc(static::rows($user, $type)
                ->select('visited_at')
                ->whereColumn('epesi_recordbrowser_recent.recordable_id', $model->getQualifiedKeyName()));
        }

        return $query;
    }

    protected static function rows(User $user, string $recordType): QueryBuilder
    {
        return DB::table('epesi_recordbrowser_recent')
            ->where('epesi_recordbrowser_recent.user_id', $user->id)
            ->where('epesi_recordbrowser_recent.recordable_type', $recordType);
    }
}
