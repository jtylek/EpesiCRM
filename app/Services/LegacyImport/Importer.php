<?php

namespace App\Services\LegacyImport;

use App\Models\User;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Template for a single-tab legacy import: upserts `<tab>_data_1` rows by
 * `legacy_id` into an Eloquent model, then reconstructs `<tab>_edit_history`
 * into `activity_log` rows, using the history-reconstruction algorithm below
 * (read from RecordBrowserCommon_0.php, not guessed):
 * `<tab>_edit_history_data` stores only the value *before* each change, so a
 * field's value right after edit N is edit N+1's recorded old value, or the
 * field's current live value if N was the last edit that touched it.
 *
 * Only plain 1:1 legacy-field -> column mappings go through
 * trackedFields()/decodeTrackedValue() and are history-replayed. Composite
 * fields that combine several legacy columns into one (PhoneCall's
 * Customer/Other-Customer/Phone chain) and relation/pivot fields
 * (Employees, Customers, Related Companies, Related, Access) are import-only, via
 * extraAttributes()/syncPivots() — matching Spatie's own LogsActivity, which
 * likewise never tracks pivot-table changes on the live app either, so this
 * isn't a new gap. A collection's items (a contact's addresses) are current
 * state only as well, via collections(); the legacy fields they come from
 * still replay into History under their old column names
 * (historyOnlyColumns()).
 */
abstract class Importer
{
    protected RecordBrowserReader $reader;

    /** @var array<int, object> raw legacy rows by legacy id, for history replay's "current value" fallback. */
    private array $liveRows = [];

    /** @var array<string, list<string>|null> legacy commondata list => its keys, null when it has none */
    private array $legacyLists = [];

    /** The running import's summary, for what syncPivots() has to report. */
    protected ImportSummary $summary;

    private ?LegacyRecordRefs $recordRefs = null;

    public function __construct()
    {
        $this->reader = new RecordBrowserReader;
    }

    abstract public function legacyTab(): string;

    abstract public function modelClass(): string;

    abstract public function logName(): string;

    /** @return array<string, string> legacy field slug => model column */
    abstract protected function trackedFields(): array;

    /** Decode one raw f_<field> value (current or historical) into [column => value]. */
    abstract protected function decodeTrackedValue(string $legacyField, ?string $raw): array;

    /** Composite/derived attributes not covered by trackedFields() — current state only. */
    protected function extraAttributes(object $row): array
    {
        return [];
    }

    /**
     * Columns the target table holds a unique index on. Legacy Epesi never
     * enforced that, so a value another record already carries is left blank
     * on the later record (lowest legacy id wins) and reported, rather than
     * aborting the whole run on the first collision.
     *
     * @return list<string>
     */
    protected function uniqueColumns(): array
    {
        return [];
    }

    /** Attach pivot relations after save — current state only. */
    protected function syncPivots(object $row, Model $model): void {}

    /**
     * Tracked columns the model no longer has: replayed into History under
     * their own names, as history recorded against them before reads, but
     * not stored on the record — a contact's address columns, now its
     * address items (collections()).
     *
     * @return list<string>
     */
    protected function historyOnlyColumns(): array
    {
        return [];
    }

    /**
     * The row's items for the model's collection fields
     * (HasCollections::syncCollection()), current state only: field => items.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function collections(object $row): array
    {
        return [];
    }

    /** Extra per-row side effects after save (ContactsImporter uses this for role assignment). */
    protected function afterSave(object $row, Model $model): void {}

    /**
     * This row's extra e-mail addresses from legacy's rc_multiple_emails
     * (`f_record_type`/`f_record_id` naming this tab and row) — absorbed into
     * the owner's own E-mail addresses collection (ContactsImporter's and
     * CompaniesImporter's collections()) instead of Mail's separate table.
     *
     * @return list<string>
     */
    protected function legacyExtraEmails(object $row, string $recordType): array
    {
        $legacy = DB::connection('legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('rc_multiple_emails_data_1')) {
            return [];
        }

        return $legacy->table('rc_multiple_emails_data_1')
            ->where('active', 1)
            ->where('f_record_type', $recordType)
            ->where('f_record_id', $row->id)
            ->pluck('f_email')
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter()
            ->all();
    }

    /**
     * $values with any address already claimed by a *different* record
     * removed and reported (an address belongs to one record) — a value
     * already on this row's own record (a re-run) isn't a conflict.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    protected function withoutTakenEmails(object $row, array $values): array
    {
        $modelClass = $this->modelClass();
        $kept = [];

        foreach (array_values(array_unique($values)) as $value) {
            $conflict = EmailAddress::query()->where('value', $value)->first();

            if ($conflict !== null) {
                // Without global scopes, so a soft-deleted owner is still
                // named here — an administrator-facing report, unlike the
                // live form's gentler message for a record they can't open.
                $ownerClass = Relation::getMorphedModel($conflict->owner_type);
                $owner = $ownerClass ? $ownerClass::query()->withoutGlobalScopes()->find($conflict->owner_id) : null;

                if (! ($owner instanceof $modelClass && $owner->legacy_id === $row->id)) {
                    $label = $owner ? "{$conflict->owner_type} #{$owner->legacy_id}" : "{$conflict->owner_type} #{$conflict->owner_id}";
                    $this->summary->warn("{$this->legacyTab()}#{$row->id}: e-mail address \"{$value}\" is already used by {$label}, skipped");

                    continue;
                }
            }

            $kept[] = $value;
        }

        return $kept;
    }

    /**
     * A multiselect commondata field's keys (a record's Group), filtered to
     * those legacy's own list offers: a key whose entry was deleted in Epesi
     * is dropped, as the enum's tryFrom() did before these lists moved into
     * CommonData.
     *
     * Checked against the legacy database, not this one: a fresh install
     * already holds the module's default list, which lacks every group an
     * administrator added in Epesi, and the commondata tab may not have run
     * yet. A list the legacy database doesn't have lets the keys through.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function commonDataKeys(array $keys, string $list): array
    {
        if (! array_key_exists($list, $this->legacyLists)) {
            $this->legacyLists[$list] = $this->legacyListKeys($list);
        }

        $known = $this->legacyLists[$list];

        if ($known === null) {
            return array_values($keys);
        }

        return array_values(array_filter($keys, fn (string $key): bool => in_array($key, $known, true)));
    }

    /**
     * @return list<string>|null
     */
    private function legacyListKeys(string $list): ?array
    {
        $legacy = DB::connection('legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('utils_commondata_tree')) {
            return null;
        }

        $id = $legacy->table('utils_commondata_tree')->where('parent_id', -1)->where('akey', $list)->value('id');

        if ($id === null) {
            return null;
        }

        // Stored through htmlspecialchars(), decoded as CommonDataImporter does.
        return $legacy->table('utils_commondata_tree')->where('parent_id', $id)->pluck('akey')
            ->map(fn (string $key): string => html_entity_decode($key, ENT_QUOTES | ENT_HTML5))
            ->all();
    }

    /**
     * A `__RECORDSETS__` field ("__task/7__company/3__") as the links of the
     * model's "link to any record" field (HasRecordLinks), current state only.
     * A reference to a record that isn't here — its recordset isn't ported, or
     * it wasn't imported — is skipped and reported, and comes in on a later
     * run once it is.
     */
    protected function importRelated(object $row, Model $model, string $legacyField = 'related', string $field = 'related'): void
    {
        $this->recordRefs ??= LegacyRecordRefs::fromImporters();
        $tokens = [];

        foreach (LegacyValue::typedRefMulti($row->{"f_{$legacyField}"} ?? null) as $ref) {
            $token = $this->recordRefs->token($ref['type'], $ref['id']);

            if ($token === null) {
                $this->summary->warn("{$this->legacyTab()}#{$row->id}: {$legacyField} \"{$ref['type']}/{$ref['id']}\" isn't imported here, link skipped");

                continue;
            }

            $tokens[] = $token;
        }

        $model->syncRecordLinks($field, $tokens);
    }

    public function run(bool $withHistory = true): ImportSummary
    {
        $summary = $this->summary = new ImportSummary;
        $modelClass = $this->modelClass();
        $userMap = LegacyIdMap::for(User::class);
        $tracked = $this->trackedFields();

        foreach ($this->reader->rows($this->legacyTab()) as $row) {
            $this->liveRows[$row->id] = $row;

            $attributes = [];
            foreach ($tracked as $legacyField => $column) {
                $attributes = array_merge($attributes, $this->decodeTrackedValue($legacyField, $row->{"f_{$legacyField}"} ?? null));
            }
            $attributes = array_merge($attributes, $this->extraAttributes($row));
            $attributes = ActivityTimes::rounded((new $modelClass)->getTable(), $attributes);
            $attributes = $this->withoutTakenValues($row, $attributes, $summary);

            /** @var Model $model */
            $model = $modelClass::withTrashed()->firstOrNew(['legacy_id' => $row->id]);
            $isNew = ! $model->exists;
            $model->forceFill(array_diff_key($attributes, array_flip($this->historyOnlyColumns())));
            $model->legacy_id = $row->id;
            $model->created_by = $userMap->get((int) ($row->created_by ?? 0));
            $model->timestamps = false;
            $model->created_at = $row->created_on;
            $model->updated_at = $row->created_on;
            // Best-effort until replayHistory() below can locate the actual
            // DELETED envelope timestamp for this record, if any.
            $model->deleted_at = (int) ($row->active ?? 1) === 0 ? $row->created_on : null;

            $model->save();
            $isNew ? $summary->created++ : $summary->updated++;

            $this->syncPivots($row, $model);
            $this->importCollections($row, $model);
            $this->afterSave($row, $model);
        }

        if ($withHistory) {
            $this->replayHistory($summary);
        }

        return $summary;
    }

    /**
     * collections() into the model. Each item takes the id of the item in
     * its place from an earlier run, so running the import again updates the
     * items rather than replacing them.
     */
    private function importCollections(object $row, Model $model): void
    {
        foreach ($this->collections($row) as $field => $items) {
            $existing = $model->collection($field)->pluck('id')->all();

            $model->syncCollection($field, array_map(
                fn (array $item, int $i): array => ['id' => $existing[$i] ?? null, ...$item],
                array_values($items),
                array_keys(array_values($items)),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutTakenValues(object $row, array $attributes, ImportSummary $summary): array
    {
        foreach ($this->uniqueColumns() as $column) {
            $value = $attributes[$column] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $attributes[$column] = $value === '' ? null : $value;

            if ($value === '') {
                continue;
            }

            // Every row, soft-deleted ones included: a deleted record still
            // holds its value in the unique index. Whether "Ann@x.com" and
            // "ann@x.com" collide is left to the database's collation, which
            // is also what would reject the insert.
            $holder = $this->modelClass()::withoutGlobalScopes()
                ->where($column, $value)
                ->where(fn ($query) => $query->whereNull('legacy_id')->orWhere('legacy_id', '!=', $row->id))
                ->first(['id', 'legacy_id']);

            if ($holder) {
                $attributes[$column] = null;
                $summary->warn("{$this->legacyTab()}#{$row->id}: {$column} \"{$value}\" is already used by ".($holder->legacy_id ? "{$this->legacyTab()}#{$holder->legacy_id}" : 'a record created here').', left blank');
            }
        }

        return $attributes;
    }

    private function replayHistory(ImportSummary $summary): void
    {
        $modelClass = $this->modelClass();
        $recordMap = LegacyIdMap::for($modelClass);
        $userMap = LegacyIdMap::for(User::class);
        $tracked = $this->trackedFields();
        $historyByRecord = $this->reader->history($this->legacyTab());

        // Replayed from scratch on every run rather than appended to, so
        // re-running the (idempotent, upsert-by-legacy_id) import doesn't
        // duplicate activity_log rows for records already imported.
        // Both spellings: rows from an import run before the morph map was
        // enforced carry the class name, later ones the alias.
        DB::table('activity_log')
            ->whereIn('subject_type', [$modelClass, $this->morphAlias($modelClass)])
            ->where('log_name', $this->logName())
            ->delete();

        // Every imported record is replayed, not just legacy ids that show up
        // in <tab>_edit_history — a record never edited since creation has no
        // envelopes there, but still has a known created_at/created_by and
        // deserves its "created" row (the loop below already falls back to
        // the record's live field values when there's no edit chain for a
        // field, via the empty($chain[$field]) branch), otherwise its History
        // tab would show nothing at all.
        $legacyIds = collect(array_keys($this->liveRows))
            ->merge($historyByRecord->keys())
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($legacyIds as $legacyId) {
            $newId = $recordMap->get($legacyId);
            if ($newId === null) {
                $summary->warn("history for {$this->legacyTab()}#{$legacyId}: record not imported, skipped");

                continue;
            }

            $liveRow = $this->liveRows[$legacyId] ?? null;
            $envelopes = $historyByRecord->get($legacyId, collect());
            $normalEnvelopes = $envelopes->reject(fn ($e) => $e->changes->has('id'))->values();
            $sentinelEnvelopes = $envelopes->filter(fn ($e) => $e->changes->has('id'))->values();

            // Chain of old-values per legacy field, oldest first.
            $chain = [];
            foreach ($normalEnvelopes as $env) {
                foreach ($env->changes as $field => $oldRaw) {
                    if (! isset($tracked[$field])) {
                        continue;
                    }
                    $chain[$field][] = ['env' => $env, 'old_raw' => $oldRaw];
                }
            }

            $envOld = [];
            $envNew = [];
            $createdAttrs = [];

            foreach ($tracked as $field => $column) {
                if (empty($chain[$field])) {
                    $createdAttrs = array_merge($createdAttrs, $this->decodeTrackedValue($field, $liveRow->{"f_{$field}"} ?? null));

                    continue;
                }

                $entries = $chain[$field];
                $createdAttrs = array_merge($createdAttrs, $this->decodeTrackedValue($field, $entries[0]['old_raw']));

                foreach ($entries as $k => $entry) {
                    $newRaw = $entries[$k + 1]['old_raw'] ?? $liveRow?->{"f_{$field}"};
                    $key = spl_object_id($entry['env']);
                    $envOld[$key] = array_merge($envOld[$key] ?? [], $this->decodeTrackedValue($field, $entry['old_raw']));
                    $envNew[$key] = array_merge($envNew[$key] ?? [], $this->decodeTrackedValue($field, $newRaw));
                }
            }

            $rows = [];

            $rows[] = $this->historyRow(
                $newId,
                'created',
                'created',
                ['attributes' => $createdAttrs],
                $userMap->get((int) ($liveRow->created_by ?? 0)),
                $liveRow->created_on ?? now(),
            );

            foreach ($normalEnvelopes as $env) {
                $key = spl_object_id($env);
                $old = $envOld[$key] ?? [];
                $new = $envNew[$key] ?? [];

                if ($old === [] && $new === []) {
                    $summary->warn("history for {$this->legacyTab()}#{$legacyId}: edit at {$env->edited_on} touched only unported fields, skipped");

                    continue;
                }

                $rows[] = $this->historyRow(
                    $newId,
                    'updated',
                    'updated',
                    ['old' => $old, 'attributes' => $new],
                    $userMap->get((int) $env->edited_by),
                    $env->edited_on,
                );
            }

            $deletedAt = null;
            foreach ($sentinelEnvelopes as $env) {
                $value = $env->changes->get('id');
                $event = match (true) {
                    $value === 'DELETED' => 'deleted',
                    $value === 'RESTORED' => 'restored',
                    default => 'imported-note',
                };

                if ($event === 'deleted') {
                    $deletedAt = $env->edited_on;
                } elseif ($event === 'restored') {
                    $deletedAt = null;
                }

                $rows[] = $this->historyRow(
                    $newId,
                    $event,
                    $event === 'imported-note' ? "Legacy history: {$value}" : $event,
                    [],
                    $userMap->get((int) $env->edited_by),
                    $env->edited_on,
                );
            }

            usort($rows, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
            DB::table('activity_log')->insert($rows);
            $summary->historyRows += count($rows);

            // Reconcile the soft-delete timestamp against what history
            // actually shows, now that it's known; active=0 with no DELETED
            // envelope found (e.g. deleted before history tracking existed)
            // keeps run()'s created_on fallback rather than losing the flag.
            $isActive = (int) ($liveRow->active ?? 1) === 1;
            $modelClass::withTrashed()->where('id', $newId)->update([
                'deleted_at' => $isActive ? null : ($deletedAt ?? $liveRow?->created_on),
                'updated_at' => collect($rows)->max('created_at'),
            ]);
        }
    }

    private function historyRow(int $subjectId, string $event, string $description, array $properties, ?int $causerId, $at): array
    {
        return [
            'log_name' => $this->logName(),
            'description' => $description,
            // The morph alias ("contact"), which is what the models'
            // activities() relation looks up since the morph map became
            // enforced — a class name here would hide the whole history.
            'subject_type' => $this->morphAlias($this->modelClass()),
            'subject_id' => $subjectId,
            'event' => $event,
            'causer_type' => $causerId ? $this->morphAlias(User::class) : null,
            'causer_id' => $causerId,
            'properties' => json_encode($properties),
            'batch_uuid' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    private function morphAlias(string $class): string
    {
        return Relation::getMorphAlias($class);
    }
}
