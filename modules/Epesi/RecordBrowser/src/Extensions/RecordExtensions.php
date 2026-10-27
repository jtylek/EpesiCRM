<?php

namespace Epesi\Modules\RecordBrowser\Extensions;

use Closure;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * How one module reaches into another's record pages — the port of Epesi's
 * `Utils_RecordBrowserCommon::new_addon()` and the ActionBar buttons a module
 * such as Watchdog puts on every record it knows about.
 *
 * A module calls this from its service provider's boot():
 *
 *     RecordExtensions::addon(NotesRelationManager::class);              // every record
 *     RecordExtensions::addon(NotesRelationManager::class, ['task']);    // tasks only
 *     RecordExtensions::addon(NotesRelationManager::class, first: true); // ahead of the resource's own tabs
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
    /** @var array<class-string<RelationManager>, array{types: array<int, string>|null, first: bool}> */
    protected static array $addons = [];

    /** @var array<string, array{callback: Closure, types: array<int, string>|null}> */
    protected static array $headerActions = [];

    protected static ?Closure $statusActionFactory = null;

    /** @var array<string, array{callback: Closure, types: array<int, string>|null}> */
    protected static array $emailLinks = [];

    /** @var array<string, array{prepare: Closure, badge: Closure}> */
    protected static array $watcherBadges = [];

    /** @var array<string, Closure> */
    protected static array $recordInfoRows = [];

    /**
     * @param  class-string<RelationManager>  $manager
     * @param  array<int, string>|null  $types  morph aliases; null means every record type
     * @param  bool  $first  ahead of the resource's own tabs (ViewRecord::getAllRelationManagers()) rather than after them
     */
    public static function addon(string $manager, ?array $types = null, bool $first = false): void
    {
        static::$addons[$manager] = ['types' => $types, 'first' => $first];
    }

    /**
     * The callback is also given the View page, for a module that reacts to
     * the record being opened (Watchdog tells the bell to reload).
     *
     * @param  Closure(Model, ViewRecord): array<Action|ActionGroup>  $callback
     * @param  array<int, string>|null  $types  morph aliases; null means every record type
     */
    public static function headerActions(string $key, Closure $callback, ?array $types = null): void
    {
        static::$headerActions[$key] = ['callback' => $callback, 'types' => $types];
    }

    /** @param Closure(): Action $callback */
    public static function registerStatusAction(Closure $callback): void
    {
        static::$statusActionFactory = $callback;
    }

    public static function statusAction(): ?Action
    {
        return static::$statusActionFactory ? (static::$statusActionFactory)() : null;
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
     * Something drawn in front of each badge of a relation field marked with
     * watchers (Field::employees(), ->watchers()): Watchdog's eye, saying
     * whether that person has seen the record's latest change. $prepare gets
     * every record the field is drawn for at once (a list page's rows), so
     * $badge can answer from what it loaded rather than query per badge.
     *
     * @param  Closure(Field, Collection<int, Model>): void  $prepare
     * @param  Closure(Field, Model, Model): ?Htmlable  $badge  the field, the record, one related record
     */
    public static function watcherBadge(string $key, Closure $prepare, Closure $badge): void
    {
        static::$watcherBadges[$key] = ['prepare' => $prepare, 'badge' => $badge];
    }

    public static function hasWatcherBadges(): bool
    {
        return static::$watcherBadges !== [];
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    public static function prepareWatcherBadges(Field $field, Collection $records): void
    {
        foreach (static::$watcherBadges as $entry) {
            ($entry['prepare'])($field, $records);
        }
    }

    public static function watcherBadgeFor(Field $field, Model $record, Model $related): ?Htmlable
    {
        $parts = collect(static::$watcherBadges)
            ->map(fn (array $entry): ?Htmlable => ($entry['badge'])($field, $record, $related))
            ->filter();

        return $parts->isEmpty() ? null : new HtmlString($parts->map->toHtml()->implode(''));
    }

    /**
     * More rows for the Record Info popup, below Updated and Created: label,
     * who, and when (or any short third column). Watchdog lists the record's
     * watchers here.
     *
     * @param  Closure(Model): array<int, array{0: string, 1: string|Htmlable|null, 2: string|null}>  $callback
     */
    public static function recordInfoRows(string $key, Closure $callback): void
    {
        static::$recordInfoRows[$key] = $callback;
    }

    /**
     * @return array<int, array{0: string, 1: string|Htmlable|null, 2: string|null}>
     */
    public static function recordInfoRowsFor(Model $record): array
    {
        return collect(static::$recordInfoRows)
            ->flatMap(fn (Closure $callback): array => $callback($record))
            ->values()
            ->all();
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function addonsFor(Model $record): array
    {
        return [...static::applicableAddons($record)
            ->filter(fn (array $entry): bool => ! $entry['first'])
            ->keys()
            ->all(), ...IncomingLinks::addons($record)];
    }

    /**
     * Addons registered with `first: true` — pinned ahead of the resource's
     * own tabs instead of after them; see ViewRecord::getAllRelationManagers().
     *
     * @return array<int, class-string<RelationManager>>
     */
    public static function firstAddonsFor(Model $record): array
    {
        return static::applicableAddons($record)
            ->filter(fn (array $entry): bool => $entry['first'])
            ->keys()
            ->all();
    }

    /**
     * @return Collection<class-string<RelationManager>, array{types: array<int, string>|null, first: bool}>
     */
    protected static function applicableAddons(Model $record): Collection
    {
        return collect(static::$addons)->filter(fn (array $entry): bool => static::applies($entry['types'], $record));
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function headerActionsFor(Model $record, ViewRecord $page): array
    {
        return collect(static::$headerActions)
            ->filter(fn (array $entry): bool => static::applies($entry['types'], $record))
            ->flatMap(fn (array $entry): array => ($entry['callback'])($record, $page))
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
        static::$statusActionFactory = null;
        static::$emailLinks = [];
        static::$watcherBadges = [];
        static::$recordInfoRows = [];
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
