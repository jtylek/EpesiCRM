<?php

namespace Epesi\Modules\PriorityList;

use App\Enums\RecordStatus;
use App\Filament\Dashboard\AppletTooltip;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Epesi\Modules\PriorityList\Models\Entry;
use Epesi\Modules\PriorityList\Models\PriorityListSetting;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Each user's short, ordered list of what they work on next — GTD's next
 * actions. It is capped, so putting something on it means choosing: when the
 * list is full, something has to be finished or taken off first.
 *
 *     PriorityList::add($user, $task);     // false when the list is full
 *     PriorityList::entries($user);        // first place first
 *     PriorityList::complete($task);       // closed, and off every list
 *
 * A list only holds records its user can still see. Visibility comes from
 * each record type's ownership scope, which reads the signed-in user, so
 * these are asked about the signed-in user's own list — the only one anyone
 * changes.
 */
class PriorityList
{
    public const LIMIT = 10;

    public const ROLES = ['super_admin', 'manager', 'employee'];

    /**
     * Morph alias => how its due date is read.
     *
     * @var array<string, array{due: (Closure(Model): ?CarbonInterface)|null, allDay: (Closure(Model): bool)|null}>
     */
    protected static array $types = [];

    /**
     * Register a type's due date, shown beside the record and red once
     * passed ($allDay says the date has no time of day, so it is due until
     * the day ends). Optional: every RecordsetResource is already a
     * candidate type without this (see candidateTypes()) — this only adds
     * the due date shown beside it once an administrator turns it on.
     *
     * @param  (Closure(Model): ?CarbonInterface)|null  $due
     * @param  (Closure(Model): bool)|null  $allDay
     */
    public static function enableFor(string $alias, ?Closure $due = null, ?Closure $allDay = null): void
    {
        static::$types[$alias] = ['due' => $due, 'allDay' => $allDay];
    }

    /**
     * The types an administrator can turn on, from Administration → Priority
     * List: registered with enableFor() (first, so recordTypes() keeps its
     * order once filtered) plus every other RecordsetResource discovered in
     * the main panel — a new module needs no code at all to appear here.
     *
     * @return array<int, string>
     */
    public static function candidateTypes(): array
    {
        return array_values(array_unique([...array_keys(static::$types), ...static::discoveredTypes()]));
    }

    /**
     * Every RecordsetResource registered in the main panel, reverse-mapped to
     * its morph alias.
     *
     * @return array<int, string>
     */
    protected static function discoveredTypes(): array
    {
        $panel = Filament::getPanel('main', isStrict: false);

        if ($panel === null) {
            return [];
        }

        $aliases = [];

        foreach ($panel->getResources() as $resource) {
            if (! is_subclass_of($resource, RecordsetResource::class)) {
                continue;
            }

            $alias = array_search($resource::getModel(), Relation::morphMap(), true);

            if (is_string($alias)) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    /**
     * Whether $alias is turned on: an administrator's explicit choice from
     * Administration → Priority List when there is one, else on for a type
     * enableFor() registered (today's task/meeting/phone_call, unchanged) and
     * off for one only discovered — an administrator has to add those.
     */
    public static function isEnabled(string $alias): bool
    {
        $overrides = PriorityListSetting::current()->overrides ?? [];

        if (array_key_exists($alias, $overrides)) {
            return (bool) $overrides[$alias];
        }

        return isset(static::$types[$alias]);
    }

    public static function setEnabled(string $alias, bool $enabled): void
    {
        $setting = PriorityListSetting::current();

        $setting->update([
            'overrides' => [...($setting->overrides ?? []), $alias => $enabled],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function recordTypes(): array
    {
        return array_values(array_filter(
            static::candidateTypes(),
            fn (string $alias): bool => static::isEnabled($alias),
        ));
    }

    public static function covers(Model $record): bool
    {
        return static::isEnabled($record->getMorphClass());
    }

    /**
     * $user's list, first place first. whereHasMorph() runs each type's
     * global scopes, so a record they can no longer see (made private, or
     * trashed) drops out.
     *
     * @return Builder<Entry>
     */
    public static function entries(User $user): Builder
    {
        $types = array_values(array_filter(array_map(
            fn (string $alias): ?string => Relation::getMorphedModel($alias),
            static::recordTypes(),
        )));

        return Entry::query()
            ->where('user_id', $user->id)
            ->whereHasMorph('record', $types)
            ->orderBy('position')
            ->orderBy('id');
    }

    public static function count(User $user): int
    {
        return static::entries($user)->count();
    }

    public static function isFull(User $user): bool
    {
        return static::count($user) >= static::LIMIT;
    }

    public static function has(User $user, Model $record): bool
    {
        return Entry::query()->where(static::key($user, $record))->exists();
    }

    /** Its place on $user's list, 1 first; null when it isn't on it. */
    public static function positionOf(User $user, Model $record): ?int
    {
        $index = static::entries($user)
            ->get(['record_type', 'record_id'])
            ->search(fn (Entry $entry): bool => $entry->record_type === $record->getMorphClass()
                && (string) $entry->record_id === (string) $record->getKey());

        return $index === false ? null : $index + 1;
    }

    /**
     * Put $record last on $user's list. False, and nothing added, when the
     * list is full. A record already on the list keeps its place.
     */
    public static function add(User $user, Model $record): bool
    {
        if (static::has($user, $record)) {
            return true;
        }

        // What the user can no longer see takes up no place: dropped here,
        // so the list never holds more than LIMIT should it come back.
        Entry::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', static::entries($user)->pluck('id')->all())
            ->delete();

        if (static::isFull($user)) {
            return false;
        }

        Entry::query()->create([
            ...static::key($user, $record),
            'position' => (int) Entry::query()->where('user_id', $user->id)->max('position') + 1,
        ]);

        return true;
    }

    public static function remove(User $user, Model $record): void
    {
        Entry::query()->where(static::key($user, $record))->delete();
    }

    /** Off everyone's list: the record is done with, or gone. */
    public static function forget(Model $record): void
    {
        Entry::query()
            ->where('record_type', $record->getMorphClass())
            ->where('record_id', $record->getKey())
            ->delete();
    }

    /**
     * Move one of $user's entries to $position (0 first): a drag on the list.
     * An id that isn't on their list changes nothing.
     */
    public static function move(User $user, int $entryId, int $position): void
    {
        $ids = static::entries($user)->pluck('id')->map(fn ($id): int => (int) $id);

        if (! $ids->contains($entryId)) {
            return;
        }

        $ids = $ids->reject(fn (int $id): bool => $id === $entryId)->values();
        $ids->splice(max(0, min($position, $ids->count())), 0, [$entryId]);

        DB::transaction(fn () => $ids->each(fn (int $id, int $index) => Entry::query()
            ->whereKey($id)
            ->update(['position' => $index + 1])));
    }

    /** Whether "Done" can close $record for $user: it has a status that is still open, and they may edit it. */
    public static function canComplete(User $user, Model $record): bool
    {
        return $record->status instanceof RecordStatus
            && ! in_array($record->status, RecordStatus::finished(), true)
            && Gate::forUser($user)->allows('update', $record);
    }

    /** Done: closed, and so off every list it was on. */
    public static function complete(Model $record): void
    {
        $record->update(['status' => RecordStatus::Closed]);

        static::forget($record);
    }

    /**
     * Keep lists to what is still to be done: a record closed or canceled —
     * by anyone, from anywhere — or deleted leaves every list, freeing its
     * place. Called for every candidate type once the application has
     * booted, on or off, so switching one on later never reveals a record
     * that finished while it was off.
     *
     * What "finished" means reads the status's own enum: any backed enum
     * with a static finished(): array works, not RecordStatus specifically —
     * ProjectStatus and TicketStatus both qualify. A status shaped some other
     * way just never triggers this; the record still comes off manually.
     */
    public static function wire(string $alias): void
    {
        $class = Relation::getMorphedModel($alias);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return;
        }

        $class::saved(function (Model $record): void {
            if (! $record->wasChanged('status')) {
                return;
            }

            $status = $record->status;
            $statusClass = is_object($status) ? $status::class : null;

            if ($statusClass !== null && method_exists($statusClass, 'finished')
                && in_array($status, $statusClass::finished(), true)) {
                static::forget($record);
            }
        });

        $class::deleted(fn (Model $record) => static::forget($record));
    }

    public static function notifyFull(): void
    {
        Notification::make()
            ->title(__('Your priority list is full!'))
            ->body(__('Do some work first: finish something on it, or take something off.'))
            ->danger()
            ->send();
    }

    public static function due(Model $record): ?CarbonInterface
    {
        $resolver = static::$types[$record->getMorphClass()]['due'] ?? null;

        return $resolver ? $resolver($record) : null;
    }

    public static function isAllDay(Model $record): bool
    {
        $resolver = static::$types[$record->getMorphClass()]['allDay'] ?? null;

        return $resolver ? (bool) $resolver($record) : false;
    }

    public static function formatDue(Model $record): ?string
    {
        return static::due($record)?->format(static::isAllDay($record) ? 'Y-m-d' : 'Y-m-d H:i');
    }

    public static function isOverdue(Model $record): bool
    {
        $due = static::due($record);

        if ($due === null || in_array($record->status, RecordStatus::finished(), true)) {
            return false;
        }

        return (static::isAllDay($record) ? $due->copy()->endOfDay() : $due)->isPast();
    }

    /** What its row shows on hover: the description; when it is due is in the row already. */
    public static function details(Model $record): ?HtmlString
    {
        return AppletTooltip::text($record->getAttribute('description'));
    }

    /** "Task: Call the bank". */
    public static function label(Model $record): string
    {
        return static::typeLabel($record).': '.static::title($record);
    }

    /** "Task · In progress" — what the record is, and how far along. */
    public static function describe(Model $record): string
    {
        return collect([
            static::typeLabel($record),
            $record->status instanceof RecordStatus ? $record->status->getLabel() : null,
        ])->filter()->implode(' · ');
    }

    public static function typeLabel(Model $record): string
    {
        return static::typeLabelFor($record->getMorphClass());
    }

    /** typeLabel() for an alias with no record in hand — the admin page's rows. */
    public static function typeLabelFor(string $alias): string
    {
        $class = Relation::getMorphedModel($alias);
        $resource = $class !== null ? static::resourceForClass($class) : null;

        return $resource ? Str::ucfirst($resource::getModelLabel()) : Str::headline($alias);
    }

    public static function title(Model $record): string
    {
        $resource = static::resourceFor($record);
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : '#'.$record->getKey();
    }

    public static function url(Model $record): ?string
    {
        $resource = static::resourceFor($record);

        if (! $resource || ! $resource::hasPage('view')) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record], panel: 'main');
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    protected static function resourceFor(Model $record): ?string
    {
        return static::resourceForClass($record::class);
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    protected static function resourceForClass(string $class): ?string
    {
        return Filament::getPanel('main', isStrict: false)?->getModelResource($class);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function key(User $user, Model $record): array
    {
        return [
            'user_id' => $user->id,
            'record_type' => $record->getMorphClass(),
            'record_id' => $record->getKey(),
        ];
    }
}
