<?php

namespace Epesi\Modules\RecordBrowser\Browsing;

use App\Support\RequestMemo;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The records each user starred — Epesi's `<tab>_favorite` tables and the
 * star next to every record (`Utils_RecordBrowserCommon::set_favs()`).
 * A recordset turns them on with `$recordsetFavorites`.
 */
class Favorites
{
    public static function has(User $user, Model $record): bool
    {
        // A View page asks once per widget that shows the star.
        return app(RequestMemo::class)->remember(
            static::memoKey($user, $record),
            fn (): bool => static::rows($user, $record->getMorphClass())
                ->where('recordable_id', $record->getKey())
                ->exists(),
        );
    }

    protected static function memoKey(User $user, Model $record): string
    {
        return "favorite:{$user->id}:{$record->getMorphClass()}:{$record->getKey()}";
    }

    public static function add(User $user, Model $record): void
    {
        app(RequestMemo::class)->forget(static::memoKey($user, $record));
        DB::table('epesi_recordbrowser_favorites')->insertOrIgnore([
            'user_id' => $user->id,
            'recordable_type' => $record->getMorphClass(),
            'recordable_id' => $record->getKey(),
            'created_at' => now(),
        ]);
    }

    public static function remove(User $user, Model $record): void
    {
        app(RequestMemo::class)->forget(static::memoKey($user, $record));
        static::rows($user, $record->getMorphClass())
            ->where('recordable_id', $record->getKey())
            ->delete();
    }

    /**
     * @return bool whether the record is a favorite now
     */
    public static function toggle(User $user, Model $record): bool
    {
        if (static::has($user, $record)) {
            static::remove($user, $record);

            return false;
        }

        static::add($user, $record);

        return true;
    }

    /**
     * @return array<int, int|string>
     */
    public static function keysFor(User $user, string $recordType): array
    {
        return static::rows($user, $recordType)->pluck('recordable_id')->all();
    }

    public static function onlyFavorites(Builder $query, User $user): Builder
    {
        $model = $query->getModel();

        return $query->whereIn(
            $model->getQualifiedKeyName(),
            static::rows($user, $model->getMorphClass())->select('recordable_id'),
        );
    }

    /**
     * The star, on a record's View page and on each row of its list. A list
     * passes $isFavorite so its rows are answered from one lookup rather than
     * a query per row.
     *
     * @param  (Closure(Model): bool)|null  $isFavorite
     */
    public static function toggleAction(?Closure $isFavorite = null): Action
    {
        $isFavorite ??= fn (Model $record): bool => static::has(Auth::user(), $record);

        return Action::make('favorite')
            ->label(__('Favorite'))
            ->icon(fn (Model $record): Heroicon => $isFavorite($record) ? Heroicon::Star : Heroicon::OutlinedStar)
            ->color(fn (Model $record): string => $isFavorite($record) ? 'warning' : 'gray')
            ->tooltip(fn (Model $record): string => $isFavorite($record)
                ? __('This record is on your favorites list. Click to remove it.')
                : __('Click to add this record to your favorites.'))
            ->action(fn (Model $record): bool => static::toggle(Auth::user(), $record));
    }

    protected static function rows(User $user, string $recordType): QueryBuilder
    {
        return DB::table('epesi_recordbrowser_favorites')
            ->where('user_id', $user->id)
            ->where('recordable_type', $recordType);
    }
}
