<?php

namespace App\Support\Calendar;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Implemented by one class per model that should appear on the Calendar page
 * (App\Filament\Pages\Calendar), registered once via CalendarRegistry::register().
 *
 * This is the Laravel-idiomatic equivalent of Epesi's
 * CRM_CalendarCommon::new_event_handler()/crm_calendar_handler() dispatch —
 * a code-level registry replaces Epesi's DB-backed dynamic handler table.
 */
interface CalendarEventProvider
{
    /**
     * Short, unique prefix for this source's event ids (e.g. 'meeting') —
     * avoids collisions across providers, mirroring Epesi's
     * "<handler_id>#<record_id>" composite ids.
     */
    public static function calendarKey(): string;

    /**
     * Every event this source has in [$start, $end), visible to $user.
     * Implementations query their model's own ownership-scoped visibility
     * (see Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility) — no separate ACL
     * filtering is needed here.
     *
     * @return Collection<int, CalendarEvent>
     */
    public static function calendarEvents(Carbon $start, Carbon $end, User $user): Collection;

    /**
     * Singular, human label for this source, used by the "new event" type
     * picker (App\Filament\Pages\Calendar::createEventAction()) — e.g.
     * 'Meeting', 'Phone Call'.
     */
    public static function calendarLabel(): string;

    /**
     * The Filament create-page URL for a new record of this type, seeded
     * from a calendar click at $date ($allDay when the click landed on a
     * day cell / all-day row rather than a specific time slot). Each
     * resource's Form class reads the same query parameters back as its
     * date field's default.
     */
    public static function calendarCreateUrl(Carbon $date, bool $allDay): string;

    /**
     * Persists a drag-move or resize from the calendar. Returns false on
     * "not found" or "not authorized" (checked against the model's own
     * Policy, e.g. Epesi\Modules\CRM\Meetings\Policies\MeetingPolicy::update()) so the calendar can
     * snap the event back to where it was — mirroring how Epesi's own drag
     * handler silently no-ops on a denied update.
     */
    public static function calendarReschedule(string $recordId, Carbon $start, ?Carbon $end, bool $allDay, User $user): bool;
}
