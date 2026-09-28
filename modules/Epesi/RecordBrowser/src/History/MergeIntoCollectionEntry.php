<?php

namespace Epesi\Modules\RecordBrowser\History;

use Closure;
use Spatie\Activitylog\Contracts\LoggablePipe;
use Spatie\Activitylog\EventLogBag;

/**
 * A record's own changes join the History entry a collection change of the
 * same save already made, rather than making a second one: Filament's Edit
 * page saves the form's collections before it updates the record (see
 * SaveActivity). Registered on every model using HasCollections.
 *
 * Emptying the changes, and not submitting an empty log, is how LogsActivity
 * is told to log nothing itself.
 */
class MergeIntoCollectionEntry implements LoggablePipe
{
    public function handle(EventLogBag $event, Closure $next): EventLogBag
    {
        if ($event->event !== 'updated' || $event->model->isLogEmpty($event->changes)) {
            return $next($event);
        }

        $entry = app(SaveActivity::class)->entryFor($event->model, collectionOnly: true);

        if ($entry === null) {
            return $next($event);
        }

        $properties = $entry->properties->toArray();

        // The record's own fields first, as they come first on its form.
        $entry->properties = collect([
            ...$properties,
            'old' => [...($event->changes['old'] ?? []), ...($properties['old'] ?? [])],
            'attributes' => [...($event->changes['attributes'] ?? []), ...($properties['attributes'] ?? [])],
        ]);

        // What ActivityLogger::log() does for an entry of its own: a file
        // field's names (HasFileFields).
        if (method_exists($event->model, 'tapActivity')) {
            $event->model->tapActivity($entry, 'updated');
        }

        $entry->save();

        // The model's own options object (EventLogBag is handed it), so
        // LogsActivity skips the now empty log even where empty ones are
        // submitted — spatie's default.
        $event->changes = ['attributes' => [], 'old' => []];
        $event->options?->dontSubmitEmptyLogs();

        return $next($event);
    }
}
