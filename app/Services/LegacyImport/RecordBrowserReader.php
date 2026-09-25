<?php

namespace App\Services\LegacyImport;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generic read access to a legacy Epesi RecordBrowser recordset ("tab"),
 * confirmed live against the `epesicrm.com-demo` database rather than
 * guessed from RecordBrowserCommon_0.php alone: `<tab>_data_1` holds real
 * typed `f_<field>` columns (not EAV rows), `<tab>_field` holds the field
 * catalog, and `<tab>_edit_history`/`_edit_history_data` hold per-edit
 * envelopes where only the *old* value is recorded per changed field.
 *
 * Works for any tab name — the RecordBrowser storage shape is the same
 * everywhere — but only `contact`/`company`/`phonecall`/`task`/
 * `crm_meeting` currently have a Laravel-side Importer to feed.
 */
class RecordBrowserReader
{
    public function __construct(private readonly string $connection = 'legacy') {}

    /**
     * The `<tab>_field` catalog, keyed by field slug (the `field` column,
     * e.g. "company_name"), which is also the `f_<field>` column suffix on
     * `<tab>_data_1`.
     *
     * @return Collection<string, object>
     */
    public function fields(string $tab): Collection
    {
        return DB::connection($this->connection)
            ->table("{$tab}_field")
            ->orderBy('processing_order')
            ->get()
            ->keyBy('field');
    }

    /**
     * Every row of `<tab>_data_1`, including Epesi's own `id`, `created_on`,
     * `created_by`, and `active` (0 = lazy-deleted, matching Laravel's
     * SoftDeletes convention this app already uses everywhere else).
     *
     * @return \Generator<int, object>
     */
    public function rows(string $tab): \Generator
    {
        yield from DB::connection($this->connection)
            ->table("{$tab}_data_1")
            ->orderBy('id')
            ->cursor();
    }

    public function count(string $tab): int
    {
        return DB::connection($this->connection)->table("{$tab}_data_1")->count();
    }

    /**
     * Edit-history envelopes for every record of a tab, grouped by legacy
     * record id and sorted oldest-first — the shape
     * LegacyHistoryReplayer::reconstruct() expects. Each envelope is one row
     * of `<tab>_edit_history` (one `edited_on`/`edited_by`) plus all of its
     * `<tab>_edit_history_data` rows (the fields that changed in that save,
     * each carrying only the value *before* the change).
     *
     * @return Collection<int, Collection<int, object{edited_on: string, edited_by: ?int, changes: Collection<string, string>}>>
     */
    public function history(string $tab): Collection
    {
        $envelopes = DB::connection($this->connection)
            ->table("{$tab}_edit_history")
            ->orderBy('edited_on')
            ->orderBy('id')
            ->get();

        if ($envelopes->isEmpty()) {
            return collect();
        }

        $data = DB::connection($this->connection)
            ->table("{$tab}_edit_history_data")
            ->whereIn('edit_id', $envelopes->pluck('id'))
            ->get()
            ->groupBy('edit_id');

        $recordIdColumn = "{$tab}_id";
        // Some legacy tabs use a shorter FK name (e.g. crm_meeting's history
        // table still points a "crm_meeting_id" column per the same
        // convention) — read it directly rather than assuming.
        if (! property_exists($envelopes->first(), $recordIdColumn)) {
            $recordIdColumn = collect((array) $envelopes->first())
                ->keys()
                ->first(fn (string $c) => str_ends_with($c, '_id'));
        }

        return $envelopes
            ->map(function ($envelope) use ($data, $recordIdColumn) {
                $envelope->record_id = $envelope->{$recordIdColumn};
                $envelope->changes = ($data->get($envelope->id) ?? collect())
                    ->pluck('old_value', 'field');

                return $envelope;
            })
            ->groupBy('record_id');
    }
}
