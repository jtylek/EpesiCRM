<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A user's screen state outliving the session: the lists' filters, search,
 * sort, columns and page size, the small choices a screen remembers (the
 * dashboard's tab, the mailbox's account and folder — see remember()), and the
 * last page they were on.
 *
 * Filament keeps the lists' state in the session under `tables.*`, and
 * remember() keeps the rest under `screens`. This copies both into
 * `user_ui_states` after every request that changed them
 * (App\Http\Middleware\PersistUiState) and back into the fresh session at
 * login (App\Listeners\RestoreUiState), so nothing is lost to a logout or an
 * expired session. The last URL is where App\Http\Responses\LoginResponse
 * sends the user next.
 */
class UiState
{
    private const TABLE = 'user_ui_states';

    private const HASH_KEY = 'ui_state_hash';

    /** The session keys that are copied, and their columns. */
    private const COLUMNS = ['tables', 'screens'];

    /** Fills the (new) session with what this user left. */
    public static function restore(User $user): void
    {
        $saved = self::savedOf($user);

        foreach (self::COLUMNS as $column) {
            if ($saved[$column] !== []) {
                session()->put($column, $saved[$column]);
            }
        }

        session()->put(self::HASH_KEY, self::hash($saved));

        // ListRecords::applyDefaultMyRecords() opens a fresh session's lists on
        // "My records" unless it was told this one has been through already:
        // a list the user left with no filters at all must stay that way.
        foreach (array_keys($saved['tables']) as $key) {
            if (str_ends_with($key, '_filters')) {
                session()->put('my_records_default:tables.'.$key, true);
            }
        }
    }

    /** What a screen remembered under $key, or $default. */
    public static function recall(string $key, mixed $default = null): mixed
    {
        return session()->get('screens', [])[$key] ?? $default;
    }

    /**
     * Remembers a small JSON-able value for a screen, by a key of its own
     * ("dashboard.tab"). It reaches the database at the end of the request.
     */
    public static function remember(string $key, mixed $value): void
    {
        $screens = (array) session()->get('screens', []);

        if ($value === null) {
            unset($screens[$key]);
        } else {
            $screens[$key] = $value;
        }

        session()->put('screens', $screens);
    }

    /** Writes the session's lists and screen state down, when it differs from what is stored. */
    public static function save(User $user): void
    {
        $current = self::currentOf();
        $hash = self::hash($current);

        if (session()->get(self::HASH_KEY, self::hash(['tables' => [], 'screens' => []])) === $hash) {
            return;
        }

        self::write($user, array_map(fn (array $value): ?array => $value === [] ? null : $value, $current));

        session()->put(self::HASH_KEY, $hash);
    }

    public static function saveLastUrl(User $user, string $url): void
    {
        if (session()->get('ui_state_last_url') === $url) {
            return;
        }

        self::write($user, ['last_url' => $url]);

        session()->put('ui_state_last_url', $url);
    }

    public static function lastUrl(User $user): ?string
    {
        try {
            return DB::table(self::TABLE)->where('user_id', $user->getKey())->value('last_url');
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{tables: array<string, mixed>, screens: array<string, mixed>} */
    private static function currentOf(): array
    {
        return [
            'tables' => (array) session()->get('tables', []),
            'screens' => (array) session()->get('screens', []),
        ];
    }

    /** @return array{tables: array<string, mixed>, screens: array<string, mixed>} */
    private static function savedOf(User $user): array
    {
        try {
            $row = DB::table(self::TABLE)->where('user_id', $user->getKey())->first(self::COLUMNS);
        } catch (Throwable) {
            $row = null;
        }

        return [
            'tables' => $row?->tables ? (array) json_decode($row->tables, true) : [],
            'screens' => $row?->screens ? (array) json_decode($row->screens, true) : [],
        ];
    }

    /** @param array<string, mixed> $values */
    private static function write(User $user, array $values): void
    {
        foreach (self::COLUMNS as $column) {
            if (isset($values[$column])) {
                $values[$column] = json_encode($values[$column]);
            }
        }

        try {
            DB::table(self::TABLE)->upsert(
                [['user_id' => $user->getKey(), 'tables' => null, 'screens' => null, 'last_url' => null, ...$values, 'created_at' => now(), 'updated_at' => now()]],
                ['user_id'],
                [...array_keys($values), 'updated_at'],
            );
        } catch (Throwable $e) {
            // The table (or a column) is missing until the database update has
            // run: the app must still work meanwhile.
            Log::debug('UI state not saved: '.$e->getMessage());
        }
    }

    /** @param array<string, mixed> $state */
    private static function hash(array $state): string
    {
        return md5(json_encode($state));
    }
}
