<?php

namespace Epesi\Modules\MigrationWizard\Verification;

use App\Services\LegacyImport\CountedTables;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\Importers\UsersImporter;
use App\Services\LegacyImport\LegacyMultipleAddresses;
use Epesi\Modules\MigrationWizard\Database\UnownedLegacyData;
use Epesi\Modules\MigrationWizard\Orchestration\OperatorChoices;
use Illuminate\Database\ConnectionInterface;

/**
 * Gathers the measured facts MigrationVerifier needs after an in-place import.
 * Read-only. Source facts come from the legacy tables through the prefixed
 * `legacy` connection; destination facts from the imported models' `legacy_id`
 * column, soft-deleted rows included (legacy's inactive records are imported as
 * deleted, so every legacy row is expected to have a destination row).
 *
 * Counts and legacy-id sets are produced only for importers that completed:
 * `users` (user_login) and the RecordBrowser tabs (`<tab>_data_1`). Importers
 * that only seed reference data (commondata, currencies, ...) have no count
 * rule of their own; their completion is recorded and nothing more is claimed.
 */
final class VerificationInputBuilder
{
    public function __construct(
        private readonly ConnectionInterface $legacy,
        /** @var array<string, class-string> tab => importer class for the record importers */
        private readonly array $recordImporters,
        /** @var callable(string): object resolves an importer class */
        private $make,
    ) {}

    /**
     * @param  array<string, mixed>  $coverage  ModuleCoverage::report() output
     * @param  list<string>  $completed  importer tabs that finished
     * @param  array<string, mixed>  $choices  OperatorChoices shape
     * @param  array<string, mixed>|null  $ledger  FileLedger::verify() output
     * @param  list<string>  $migratedFileAreas  protected data areas whose importer completed with a clean file ledger
     * @param  array<string, array{count: int, bytes: int, sample: list<string>}>  $fileLeftovers  files left in those areas that no imported record references
     * @param  array<string, true>  $moduleTables  tables the Premium and Custom modules that stay create
     * @return array<string, mixed> MigrationVerifier::verify() input
     */
    public function build(array $coverage, array $completed, array $choices, ?array $ledger, array $migratedFileAreas = [], array $fileLeftovers = [], array $moduleTables = []): array
    {
        $discardUnknown = (bool) ($choices['discard_unknown'] ?? false);
        $choices = OperatorChoices::normalise($choices);
        $done = array_fill_keys($completed, true);
        $counts = [];
        $ids = [];
        $certification = [];
        $missing = [];

        if (isset($done['users'])) {
            $declared = (new UsersImporter)->countedTables();
            foreach ($declared as $table) {
                if (! $this->tableExists($table['table'], $missing)) {
                    continue;
                }
                [$count, $idSet, $entry] = $this->measure('Users', 'users', $table);
                $counts[] = $count;
                $ids[] = $idSet;
                $certification[] = $entry;
            }
        }
        foreach ($this->recordImporters as $tab => $class) {
            if (! isset($done[$tab])) {
                continue;
            }
            $importer = ($this->make)($class);
            if (! $importer instanceof CountedTables) {
                continue;
            }
            foreach ($importer->countedTables() as $table) {
                if (($table['optional'] ?? false) === true && ! $this->legacy->getSchemaBuilder()->hasTable($table['table'])) {
                    continue;
                }
                if (! $this->tableExists($table['table'], $missing)) {
                    continue;
                }
                if (isset($table['count'])) {
                    $certification[] = $this->measuredByImporter($tab, $table, $ledger) + $this->extras($table);

                    continue;
                }
                [$count, $idSet, $entry] = $this->measure(ucfirst($tab), $tab, $table);
                $counts[] = $count;
                $ids[] = $idSet;
                $certification[] = $entry + $this->extras($table);
            }
        }

        // Premium_MultipleAddresses: its rows are carried over by the contacts importer, as Shipping addresses of
        // their owner (and of the company they name). The table is certified by looking for every such address
        // in the destination now, so it (and its side tables) is released only when none is missing.
        if (isset($done['contacts'])) {
            $addresses = new LegacyMultipleAddresses($this->legacy);
            if ($addresses->exists()) {
                $count = $addresses->verification();
                $certification[] = [
                    'label' => 'Multiple addresses',
                    'importer' => 'contacts',
                    'table' => LegacyMultipleAddresses::TABLE,
                    'source' => $count['source'],
                    'imported' => $count['imported'],
                    'skipped' => $count['skipped'],
                    'skip_label' => 'inactive, without an address or without an owner',
                    'missing_ids' => $count['missing'],
                    'unmigrated_columns' => [],
                ];
            }
        }

        $preserve = $choices['preserve'];
        if (in_array(OperatorChoices::UNKNOWN, $preserve, true)) {
            $preserve = array_values(array_diff($preserve, [OperatorChoices::UNKNOWN]));
            // Protected business-file areas are never kept by a blanket choice: they must migrate.
            $areas = array_diff(array_map('strval', (array) ($coverage['unclassified_file_areas'] ?? [])), MigrationVerifier::PROTECTED_AREAS);
            array_push($preserve, ...array_map('strval', (array) ($coverage['unclassified_tables'] ?? [])), ...$areas);
        }

        // After verification only Premium and Custom modules' tables stay (user decision; UnownedLegacyData), unless
        // kept by name. A table an importer counts (certified) is decided by its count, not here. What no importer
        // claims and is not removed (those tables, the data/ folders) is kept and never blocks. The junk files in
        // data/ go separately (LegacyJunk), never a whole folder.
        $unowned = ['tables' => [], 'files' => []];
        if ($discardUnknown) {
            $kept = array_fill_keys(array_map('strval', $preserve), true);
            // A counted table and the tables that follow it are decided by its count: taking one of them here would
            // remove it uncounted, even when its table has to stay.
            $counted = [];
            foreach ($certification as $entry) {
                $counted[$entry['table']] = true;
                foreach ($entry['sides'] ?? [] as $side) {
                    $counted[$side] = true;
                }
                if (str_ends_with($entry['table'], 'data_1')) {
                    foreach (MigrationVerifier::SIDE_TABLES as $suffix) {
                        $counted[substr($entry['table'], 0, -strlen('data_1')).$suffix] = true;
                    }
                }
            }
            foreach ((array) ($coverage['tables'] ?? []) as $row) {
                $table = (string) ($row['table'] ?? '');
                if (in_array($row['outcome'] ?? 'unknown', ['unknown', 'known_but_not_migrated'], true)
                    && ! isset($kept[$table]) && ! isset($counted[$table]) && UnownedLegacyData::removable($table, $moduleTables)) {
                    $unowned['tables'][] = $table;
                }
            }
        }

        return [
            'counts' => $counts,
            'legacy_ids' => $ids,
            'table_certification' => $certification,
            'ledger' => $ledger,
            'migrated_file_areas' => array_values($migratedFileAreas),
            'file_leftovers' => $fileLeftovers,
            'coverage' => $coverage,
            'completed_importers' => array_values($completed),
            'preserved' => array_values(array_unique($preserve)),
            'approved_discards' => $choices['discard'],
            'core_only' => $choices['core_only'],
            'unowned' => $unowned,
            'missing_tables' => $missing,
            // What no importer claims and is not removed is kept, not a reason to stop.
            'keep_unclassified' => $discardUnknown,
        ];
    }

    /**
     * A counted table that is not there (for example dropped by an earlier cleanup) has nothing to count: it is
     * reported as a warning and the run goes on (user decision).
     *
     * @param  list<string>  $missing
     */
    private function tableExists(string $table, array &$missing): bool
    {
        if ($this->legacy->getSchemaBuilder()->hasTable($table)) {
            return true;
        }
        $missing[] = $table;

        return false;
    }

    /**
     * A table whose rows have no `legacy_id` to count by: the importer compares it row by row (CountedTables `count`).
     *
     * @param  array<string, mixed>  $table
     * @param  array<string, mixed>|null  $ledger
     * @return array<string, mixed>
     */
    private function measuredByImporter(string $tab, array $table, ?array $ledger): array
    {
        $absent = array_fill_keys(array_map('strval', (array) ($ledger['absent'] ?? [])), true);
        $numbers = ($table['count'])($this->legacy, $absent);

        return [
            'label' => (string) ($table['label'] ?? ucfirst($tab)),
            'importer' => $tab,
            'table' => $table['table'],
            'source' => (int) $numbers['source'],
            'imported' => (int) $numbers['imported'],
            'skipped' => (int) $numbers['skipped'],
            'skip_label' => $table['skip_label'] ?? null,
            'missing_ids' => (int) $numbers['missing_ids'],
            'unmigrated_columns' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array{sides?: list<string>, pending?: list<string>} only what the table declares
     */
    private function extras(array $table): array
    {
        $pending = $table['pending'] ?? null;

        return array_filter([
            'sides' => array_values(array_map('strval', (array) ($table['sides'] ?? []))),
            'pending' => is_callable($pending) ? array_values(array_map('strval', $pending($this->legacy))) : [],
        ]);
    }

    /**
     * Count one declared table in the database now: the legacy rows, the rows the importer deliberately
     * skips (its declared rule), and the records that carry a legacy id of this table.
     *
     * @param  array{table: string, model: class-string, skip: array{column: string, value: scalar, label: string}|null, unmigrated_columns?: list<string>}  $table
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function measure(string $label, string $tab, array $table): array
    {
        $source = $this->legacy->table($table['table'])->pluck('id')->all();
        $skipped = [];
        if ($table['skip'] !== null) {
            $skipped = $this->legacy->table($table['table'])->where($table['skip']['column'], $table['skip']['value'])->pluck('id')->all();
        }
        $expected = array_values(array_diff(array_map('strval', $source), array_map('strval', $skipped)));
        $model = $table['model'];
        $destination = $model::query()->withoutGlobalScopes()->whereNotNull('legacy_id')->pluck('legacy_id')->all(); // soft-deleted records count
        $missing = array_diff($expected, array_map('strval', $destination));

        return [
            ['label' => $label, 'source' => count($source), 'destination' => count($destination)],
            ['label' => $label, 'source_ids' => $source, 'destination_ids' => $destination],
            [
                'label' => $label,
                'importer' => $tab,
                'table' => $table['table'],
                'source' => count($source),
                'imported' => count($destination),
                'skipped' => count($skipped),
                'skip_label' => $table['skip']['label'] ?? null,
                'missing_ids' => count($missing),
                // Legacy columns the importer did not carry (a custom field it could not fill yet): the table is kept while any is left.
                'unmigrated_columns' => array_values(array_map('strval', $table['unmigrated_columns'] ?? [])),
            ],
        ];
    }
}
