<?php

namespace Epesi\Modules\RecordBrowser\Extensions;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * How one module reaches into another's record pages — the port of Epesi's
 * `Utils_RecordBrowserCommon::new_addon()` and the ActionBar buttons a module
 * such as Watchdog puts on every record it knows about.
 *
 * A module calls this from its service provider's boot():
 *
 *     RecordExtensions::addon(NotesRelationManager::class);              // every record
 *     RecordExtensions::addon(NotesRelationManager::class, ['task']);    // tasks only
 *     RecordExtensions::headerActions('watchdog', fn (Model $record): array => [...]);
 *     RecordExtensions::emailLink('mail', fn (Model $record, string $email): ?string => ...);
 *
 * Record types are given as morph aliases ('task', 'contact') — the same
 * stable names the rest of the app stores — so the registering module never
 * imports another module's model classes. Every View page extending
 * RecordBrowser's ViewRecord picks the registrations up, core resources and
 * recordsets alike.
 *
 * State is static and filled during boot, the same lifetime Filament's own
 * panel registrations have. Registrations are keyed (by relation manager
 * class, or by the name given for header actions), so registering again —
 * a second application boot in the same process, as tests do — replaces
 * rather than duplicates.
 */
class RecordExtensions
{
    /** @var array<class-string<RelationManager>, array<int, string>|null> */
    protected static array $addons = [];

    /** @var array<string, array{callback: Closure, types: array<int, string>|null}> */
    protected static array $headerActions = [];

    /** @var array<string, array{callback: Closure, types: array<int, string>|null}> */
    protected static array $emailLinks = [];

    /**
     * @param  class-string<RelationManager>  $manager
     * @param  array<int, string>|null  $types  morph aliases; null means every record type
     */
    public static function addon(string $manager, ?array $types = null): void
    {
        static::$addons[$manager] = $types;
    }

    /**
     * @param  Closure(Model): array<Action|ActionGroup>  $callback
     * @param  array<int, string>|null  $types  morph aliases; null means every record type
     */
    public static function headerActions(string $key, Closure $callback, ?array $types = null): void
    {
        static::$headerActions[$key] = ['callback' => $callback, 'types' => $types];
    }

    /**
     * Where clicking an e-mail address on a record goes — Epesi's
     * CRM_RoundcubeCommon::get_mailto_link(), which opened the CRM's own
     * compose window for a user with a mail account. The callback returns a
     * URL, or null to leave the address to plain mailto:.
     *
     * @param  Closure(Model, string): ?string  $callback
     * @param  array<int, string>|null  $types  morph aliases; null means every record type
     */
    public static function emailLink(string $key, Closure $callback, ?array $types = null): void
    {
        static::$emailLinks[$key] = ['callback' => $callback, 'types' => $types];
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function addonsFor(Model $record): array
    {
        return collect(static::$addons)
            ->filter(fn (?array $types): bool => static::applies($types, $record))
            ->keys()
            ->all();
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function headerActionsFor(Model $record): array
    {
        return collect(static::$headerActions)
            ->filter(fn (array $entry): bool => static::applies($entry['types'], $record))
            ->flatMap(fn (array $entry): array => ($entry['callback'])($record))
            ->values()
            ->all();
    }

    public static function emailUrlFor(Model $record, string $email): string
    {
        foreach (static::$emailLinks as $entry) {
            if (static::applies($entry['types'], $record) && filled($url = ($entry['callback'])($record, $email))) {
                return $url;
            }
        }

        return 'mailto:'.$email;
    }

    /** For tests: forget every registration. */
    public static function flush(): void
    {
        static::$addons = [];
        static::$headerActions = [];
        static::$emailLinks = [];
    }

    /**
     * @param  array<int, string>|null  $types
     */
    protected static function applies(?array $types, Model $record): bool
    {
        return $types === null || in_array(static::aliasOf($record), $types, true);
    }

    public static function aliasOf(Model $record): string
    {
        return Relation::getMorphAlias($record::class);
    }
}
