<?php

namespace Epesi\Modules\ActivityReport;

use App\Models\User;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Models\AttachmentLink;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * Who may read whose activity, and the query behind the report — the port of
 * Apps_ActivityReport, with one rule changed: legacy let anyone holding the
 * permission pick any user; here employees and accountants see only their own
 * activity, and only managers and super admins may pick another user or
 * everyone.
 *
 * The scope is applied to the base query on the server, whatever the filters
 * say: a forged user filter can only narrow what the viewer may see already.
 */
class ActivityReport
{
    /** Report kinds (Epesi's checkboxes) and the activity_log events they stand for. */
    public const KINDS = [
        'new' => ['created', 'cloned'],
        'edit' => ['updated'],
        'delete_restore' => ['deleted', 'restored'],
    ];

    public const FILES = 'files';

    /** @var list<string> */
    public const DEFAULT_KINDS = ['new', 'edit', 'delete_restore'];

    /** Who may open the report at all (consultants may not). */
    public const ROLES = ['super_admin', 'manager', 'employee', 'accountant'];

    /** Who may pick another user, or everyone. */
    public const PICKER_ROLES = ['super_admin', 'manager'];

    public static function canAccess(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->hasAnyRole(self::ROLES) ?? false;
    }

    public static function canPickUser(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->hasAnyRole(self::PICKER_ROLES) ?? false;
    }

    /**
     * The users whose activity the viewer may see: themselves, or for a
     * manager every active user.
     *
     * @return list<int>
     */
    public static function allowedUserIds(User $viewer): array
    {
        if (! self::canPickUser($viewer)) {
            return [$viewer->getKey()];
        }

        return User::query()->where('active', true)->pluck('id')->push($viewer->getKey())->unique()->values()->all();
    }

    /**
     * @return array<int|string, string> user id => name, active users by name
     */
    public static function userOptions(): array
    {
        return User::query()->where('active', true)->with('contact')->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->displayName()])
            ->sortBy(fn (string $name): string => mb_strtolower($name))
            ->all();
    }

    /**
     * The viewer's reachable activity, newest first: only rows of recordsets
     * (and of notes/attachments), so that e.g. field-definition changes do
     * not show up. Narrowed further by apply().
     *
     * @param  list<int>  $userIds
     */
    public static function query(array $userIds): Builder
    {
        $activity = ActivitylogServiceProvider::determineActivityModel();

        return $activity::query()
            ->where('causer_type', (new User)->getMorphClass())
            ->whereIn('causer_id', $userIds)
            ->whereIn('subject_type', [...array_keys(LinkableRecordsets::options()), self::attachmentAlias()]);
    }

    /**
     * The report's own filter: record types, kinds of action, dates. The
     * dates are whole days in the viewer's timezone.
     *
     * @param  array<string, mixed>  $data
     */
    public static function apply(Builder $query, array $data): Builder
    {
        $kinds = array_values(array_intersect((array) ($data['kinds'] ?? self::DEFAULT_KINDS), [...array_keys(self::KINDS), self::FILES]));
        $recordsets = array_values(array_intersect(
            filled($data['recordsets'] ?? null) ? (array) $data['recordsets'] : array_keys(LinkableRecordsets::options()),
            array_keys(LinkableRecordsets::options()),
        ));

        $events = collect($kinds)->flatMap(fn (string $kind): array => self::KINDS[$kind] ?? [])->all();

        $query->where(function (Builder $query) use ($kinds, $recordsets, $events): void {
            $none = true;

            if ($events !== []) {
                $none = false;
                $query->orWhere(fn (Builder $q) => $q->whereIn('subject_type', $recordsets)->whereIn('event', $events));
            }

            if (in_array(self::FILES, $kinds, true)) {
                $none = false;
                $query->orWhere(fn (Builder $q) => $q
                    ->where('subject_type', self::attachmentAlias())
                    ->whereIn('event', ['created', 'updated'])
                    ->whereIn('subject_id', AttachmentLink::query()->select('attachment_id')->whereIn('attachable_type', $recordsets)));
            }

            if ($none) {
                $query->whereRaw('1 = 0');
            }
        });

        if (filled($data['from'] ?? null)) {
            $query->where('created_at', '>=', self::dayBoundary($data['from'], end: false));
        }

        if (filled($data['until'] ?? null)) {
            $query->where('created_at', '<=', self::dayBoundary($data['until'], end: true));
        }

        return $query;
    }

    /** The first and last day shown until the viewer chooses: this month so far. */
    public static function defaultFilter(): array
    {
        $today = Carbon::now(FilamentTimezone::get());

        return [
            'recordsets' => [],
            'kinds' => self::DEFAULT_KINDS,
            'from' => $today->copy()->startOfMonth()->toDateString(),
            'until' => $today->toDateString(),
        ];
    }

    public static function attachmentAlias(): string
    {
        return (new Attachment)->getMorphClass();
    }

    private static function dayBoundary(mixed $date, bool $end): Carbon
    {
        $day = Carbon::parse($date, FilamentTimezone::get());

        return ($end ? $day->endOfDay() : $day->startOfDay())->utc();
    }
}
