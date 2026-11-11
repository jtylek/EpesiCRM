<?php

namespace Epesi\Modules\MigrationWizard\Verification;

use InvalidArgumentException;

/**
 * Pure post-import verification: turns measured facts into a pass/warn/block
 * report and decides what a later cleanup may touch. It reads no database or
 * file itself, so it is deterministic and testable; the wizard gathers the
 * facts (counts, id sets, the FileLedger result, the ModuleCoverage report)
 * with the importers' own verification rules.
 *
 * Fail-closed: a missing coverage report, a count mismatch, an id without a
 * destination row, any file-ledger problem, a supported table whose importer
 * did not complete, and any legacy data nobody explicitly preserved, approved
 * for discard or covered by the Core-only choice, all block cleanup.
 *
 * input:
 *  counts:              list of {label, source, destination}
 *  legacy_ids:          list of {label, source_ids, destination_ids}
 *  ledger:              result of FileLedger::verify() (null blocks: not verified)
 *  coverage:            ModuleCoverage::report() output (null blocks)
 *  completed_importers: list of importer ids that finished and were rerun-checked
 *  preserved:           exact tables/file areas the operator chose to keep
 *  approved_discards:   exact tables/file areas approved for discard (typed confirmation, upstream)
 *  migrated_file_areas: protected areas (PROTECTED_AREAS) whose importer completed with a clean file ledger
 *  table_certification: per declared table (VerificationInputBuilder, counted in the database now): label, importer, table,
 *                       source rows, imported records, skipped rows with their declared rule, ids without a record, what it
 *                       still holds for a future importer (`pending`), tables that follow it (`sides`). A table is certified
 *                       when source = imported + skipped (no tolerance), no id is missing and nothing is pending; otherwise it
 *                       is KEPT and reported with both counts, and nothing else is blocked by it
 *  file_leftovers:      per migrated area, the files no imported record references (count, bytes, sample): they stay
 *                       where they are, do not block, and are reported (`leftover_files`)
 *  core_only:           the operator chose "Continue with Epesi Core only"
 */
final class MigrationVerifier
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const BLOCK = 'block';

    /**
     * Migrated business data: never unknown, preserved-only or discardable. Its files must be in
     * the file ledger and verified (input `migrated_file_areas`) before they count as migrated, and
     * only then may cleanup remove the legacy copies.
     */
    public const PROTECTED_AREAS = ['data/CRM_Mail', 'data/Utils_FileStorage'];

    /**
     * Legacy tables Epesi 3 does not use (user decision): exact names, never a pattern, and no other
     * table without the user's approval. They need no --discard and no typed confirmation, never block
     * verification and are neither preserved nor unknown. The wizard drops them once verification has
     * passed (MigrationRunner); the pre-upgrade backup still holds them. An operator's explicit --preserve still wins.
     *
     * `recordbrowser_search_index`: Epesi 3 has no such index (RecordsetResource searches the records
     * directly). The `rc_*` tables are the legacy Roundcube engine's own (cache, sessions, searches,
     * address book, identities, users): the mail accounts and the archived mail are Epesi records
     * (`rc_accounts_*`, `rc_mails_*`, `rc_mail_threads_*`, `rc_multiple_emails_*`, `rc_related_*`) and are
     * never in this list; a test fails if one of those prefixes ever enters it.
     */
    public const OBSOLETE_TABLES = [
        'recordbrowser_search_index',
        'rc_cache',
        'rc_cache_index',
        'rc_cache_messages',
        'rc_cache_shared',
        'rc_cache_thread',
        'rc_session',
        'rc_searches',
        'rc_system',
        'rc_dictionary',
        'rc_users',
        'rc_identities',
        'rc_contacts',
        'rc_contactgroups',
        'rc_contactgroupmembers',
    ];

    /**
     * Side tables of a recordset that go together with its certified `<tab>_data_1` (user decision): its metadata
     * (`_access*`, `_callback`, `_field`), its replayed edit history, and the per-user favorites and recents.
     * They are never counted on their own; a parent that does not match keeps all of them.
     */
    public const SIDE_TABLES = ['access', 'access_clearance', 'access_fields', 'callback', 'field', 'edit_history', 'edit_history_data', 'favorite', 'recent'];

    /**
     * The legacy watchdog ("watched records") tables, released as one exact group (user decision). No importer or
     * verifier reads any of them (checked: nothing under app/ or modules/ names them), and Epesi 3 keeps its own
     * subscriptions in `epesi_watchdog_*`. An explicit --preserve still wins; an absent table is ignored.
     */
    public const WATCHDOG_TABLES = ['utils_watchdog_category', 'utils_watchdog_category_subscription', 'utils_watchdog_event', 'utils_watchdog_notification_queue', 'utils_watchdog_subscription'];

    /** @var array{tables: list<string>, files: list<string>} what nobody owns, removed right after verification */
    private array $unowned = ['tables' => [], 'files' => []];

    /** @param  array<string, mixed>  $input */
    public function verify(array $input): array
    {
        $this->unowned = ['tables' => [], 'files' => []];
        $passed = [];
        $warnings = [];
        $blocking = [];

        $certification = $this->certify($this->list($input, 'table_certification'));
        // A declared table is judged by its certification (a mismatch keeps the table and blocks nothing), not by the generic count check.
        $certified = array_fill_keys(array_column($certification, 'label'), true);

        foreach ((array) ($input['missing_tables'] ?? []) as $table) {
            $warnings[] = ['check' => 'table_missing', 'label' => 'Table '.$table, 'detail' => 'The legacy table does not exist, so its records could not be counted.'];
        }
        foreach ($this->list($input, 'counts') as $count) {
            if (isset($certified[$this->label($count)])) {
                continue;
            }
            $label = $this->label($count);
            $source = $this->int($count, 'source');
            $destination = $this->int($count, 'destination');
            if ($source === $destination) {
                $passed[] = ['check' => 'count', 'label' => $label, 'detail' => "{$source} source and destination records"];
            } else {
                $blocking[] = ['check' => 'count', 'label' => $label, 'detail' => "{$source} source records but {$destination} destination records"];
            }
        }

        foreach ($this->list($input, 'legacy_ids') as $ids) {
            if (isset($certified[$this->label($ids)])) {
                continue;
            }
            $label = $this->label($ids);
            $source = array_unique(array_map('strval', (array) ($ids['source_ids'] ?? [])));
            $destination = array_unique(array_map('strval', (array) ($ids['destination_ids'] ?? [])));
            $missing = array_values(array_diff($source, $destination));
            $extra = array_values(array_diff($destination, $source));
            if ($missing !== []) {
                $blocking[] = ['check' => 'legacy_id_coverage', 'label' => $label, 'detail' => count($missing).' legacy id(s) have no destination record', 'examples' => array_slice($missing, 0, 10)];
            } else {
                $passed[] = ['check' => 'legacy_id_coverage', 'label' => $label, 'detail' => count($source).' legacy ids all present'];
            }
            if ($extra !== []) {
                $warnings[] = ['check' => 'legacy_id_extra', 'label' => $label, 'detail' => count($extra).' destination record(s) have no legacy id in the source', 'examples' => array_slice($extra, 0, 10)];
            }
        }

        $this->ledger($input['ledger'] ?? null, $passed, $blocking, $warnings);

        $coverage = $input['coverage'] ?? null;
        $obsolete = [];
        $sideTables = ['released_with_parent' => [], 'watchdog' => [], 'kept_with_uncertified_parent' => []];
        $groups = [];
        $leftovers = [];
        $eligible = [];
        $preserved = [];
        $discarded = [];
        if (! is_array($coverage) || ! is_array($coverage['tables'] ?? null) || ! is_array($coverage['files'] ?? null)) {
            $blocking[] = ['check' => 'coverage', 'label' => 'Coverage report', 'detail' => 'No coverage report: legacy tables and files are unclassified.'];
        } else {
            $this->coverage($coverage, $input, $passed, $warnings, $blocking, $eligible, $preserved, $discarded, $obsolete, $leftovers, $certification, $sideTables, $groups);
        }

        foreach ($sideTables as $kind => $list) {
            sort($list, SORT_STRING);
            $sideTables[$kind] = $list;
        }
        ksort($groups, SORT_STRING);
        foreach ($groups as $parent => $list) {
            sort($list, SORT_STRING);
            $groups[$parent] = $list;
        }
        foreach (['tables', 'files'] as $kind) {
            if (isset($eligible[$kind])) {
                sort($eligible[$kind], SORT_STRING);
            }
            if (isset($preserved[$kind])) {
                sort($preserved[$kind], SORT_STRING);
            }
            if ($kind === 'tables') {
                sort($obsolete, SORT_STRING);
            }
            if (isset($discarded[$kind])) {
                sort($discarded[$kind], SORT_STRING);
            }
        }
        $hasPreserved = ($preserved['tables'] ?? []) !== [] || ($preserved['files'] ?? []) !== [];
        $status = $blocking !== [] ? self::BLOCK : ($warnings !== [] || $hasPreserved ? self::WARN : self::PASS);

        return [
            'status' => $status,
            'passed' => $passed,
            'warnings' => $warnings,
            'blocking' => $blocking,
            'cleanup_allowed' => $blocking === [],
            'cleanup_eligible_tables' => $eligible['tables'] ?? [],
            'cleanup_eligible_file_areas' => $eligible['files'] ?? [],
            'preserved_tables' => $preserved['tables'] ?? [],
            'preserved_file_areas' => $preserved['files'] ?? [],
            'discarded_tables' => $discarded['tables'] ?? [],
            'obsolete_tables' => $obsolete,
            'table_certification' => $certification,
            'table_groups' => $groups,
            'side_tables' => $sideTables,
            'leftover_files' => $leftovers,
            'leftover_total' => ['count' => array_sum(array_column($leftovers, 'count')), 'bytes' => array_sum(array_column($leftovers, 'bytes'))],
            'discarded_file_areas' => $discarded['files'] ?? [],
            'unowned_tables' => $this->unowned['tables'],
            'unowned_file_areas' => $this->unowned['files'],
            'preserved_for_assisted_migration' => $hasPreserved,
            'outcome' => $blocking !== [] ? 'not_verified' : ($hasPreserved ? 'core_complete_with_preserved_data' : 'verified'),
        ];
    }

    /** @param  array<string, mixed>|null  $ledger */
    private function ledger(?array $ledger, array &$passed, array &$blocking, array &$warnings): void
    {
        if ($ledger === null || ! is_array($ledger['problems'] ?? null) || ! isset($ledger['checked'])) {
            $blocking[] = ['check' => 'file_ledger', 'label' => 'Business files', 'detail' => 'The business-file ledger was not verified.'];

            return;
        }
        foreach ($ledger['problems'] as $problem) {
            $blocking[] = [
                'check' => 'file_ledger',
                'label' => (string) ($problem['source'] ?? 'unknown file'),
                'detail' => 'File problem: '.(string) ($problem['problem'] ?? 'unknown'),
            ];
        }
        if ($ledger['problems'] === []) {
            $passed[] = ['check' => 'file_ledger', 'label' => 'Business files', 'detail' => (int) $ledger['checked'].' file(s) match their recorded hashes'];
        }
        $absent = is_array($ledger['absent'] ?? null) ? count($ledger['absent']) : 0;
        if ($absent > 0) {
            $warnings[] = [
                'check' => 'source_files_absent',
                'label' => 'Business files',
                'detail' => $absent.' file(s) that records name are not in the legacy data folder (purged or never kept), so those records were imported without them; nothing was lost by the migration.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  array<string, mixed>  $input
     * @param  array<string, list<string>>  $eligible
     * @param  array<string, list<string>>  $preserved
     * @param  array<string, list<string>>  $discarded
     * @param  list<string>  $obsolete
     * @param  array<string, array{count: int, bytes: int, sample: list<string>}>  $leftovers
     * @param  list<array<string, mixed>>  $certification
     * @param  array<string, list<string>>  $sideTables
     * @param  array<string, list<string>>  $groups
     */
    private function coverage(array $coverage, array $input, array &$passed, array &$warnings, array &$blocking, array &$eligible, array &$preserved, array &$discarded, array &$obsolete, array &$leftovers, array $certification, array &$sideTables, array &$groups): void
    {
        $completed = array_fill_keys(array_map('strval', (array) ($input['completed_importers'] ?? [])), true);
        $keep = array_fill_keys(array_map('strval', (array) ($input['preserved'] ?? [])), true);
        $discard = array_fill_keys(array_map('strval', (array) ($input['approved_discards'] ?? [])), true);
        $coreOnly = (bool) ($input['core_only'] ?? false);
        $keepUnclassified = (bool) ($input['keep_unclassified'] ?? false);
        $unownedInput = [
            'tables' => array_fill_keys(array_map('strval', (array) ($input['unowned']['tables'] ?? [])), true),
            'files' => array_fill_keys(array_map('strval', (array) ($input['unowned']['files'] ?? [])), true),
        ];
        $migratedAreas = array_fill_keys(array_map('strval', (array) ($input['migrated_file_areas'] ?? [])), true);
        $leftoverInput = (array) ($input['file_leftovers'] ?? []);
        $eligible = ['tables' => [], 'files' => []];
        $preserved = ['tables' => [], 'files' => []];
        $discarded = ['tables' => [], 'files' => []];

        $assets = [];
        foreach ($coverage['tables'] as $row) {
            $assets[] = ['kind' => 'tables', 'name' => (string) $row['table'], 'row' => $row, 'rows' => (int) ($row['estimated_rows'] ?? 0)];
        }
        foreach ($coverage['files'] as $row) {
            $assets[] = ['kind' => 'files', 'name' => (string) $row['area'], 'row' => $row, 'rows' => (int) ($row['count'] ?? 0)];
        }

        // An operator choice naming something that is not there is a typo, not an approval.
        $names = array_fill_keys(array_column($assets, 'name'), true);
        foreach ([['preserve', $keep], ['discard', $discard]] as [$kind, $chosen]) {
            foreach (array_keys($chosen) as $name) {
                if (! isset($names[$name])) {
                    $blocking[] = ['check' => 'unknown_choice', 'label' => (string) $name, 'detail' => "The {$kind} choice names no legacy table or file area."];
                }
            }
        }

        // Which tables are certified or kept by their count, and which side tables follow which parent.
        $byTable = [];
        foreach ($certification as $entry) {
            $byTable[$entry['table']] = $entry;
        }
        $present = array_fill_keys(array_column(array_filter($assets, static fn (array $asset): bool => $asset['kind'] === 'tables'), 'name'), true);
        $sideOf = [];
        foreach ($byTable as $parent => $entry) {
            foreach ($entry['sides'] as $side) {
                if (isset($present[$side])) {
                    $sideOf[$side] = [$parent, 'declared'];
                }
            }
            if (! str_ends_with($parent, 'data_1')) {
                continue;
            }
            $prefix = substr($parent, 0, -strlen('data_1'));
            foreach (self::SIDE_TABLES as $suffix) {
                if (isset($present[$prefix.$suffix])) {
                    $sideOf[$prefix.$suffix] = [$parent, $suffix];
                }
            }
        }

        foreach ($assets as $asset) {
            $name = $asset['name'];
            $row = $asset['row'];
            $outcome = $row['outcome'] ?? 'unknown';
            $label = ($asset['kind'] === 'tables' ? 'Table ' : 'File area ').$name;

            if ($asset['kind'] === 'files' && in_array($name, self::PROTECTED_AREAS, true) && $asset['rows'] > 0) {
                if (isset($migratedAreas[$name])) {
                    $passed[] = ['check' => 'business_files', 'label' => $label, 'detail' => 'Migrated into Epesi 3 file storage and ledger-verified.'];
                    $eligible['files'][] = $name;
                    $left = $leftoverInput[$name] ?? null;
                    if (is_array($left) && (int) ($left['count'] ?? 0) > 0) {
                        $leftovers[$name] = ['count' => (int) $left['count'], 'bytes' => (int) ($left['bytes'] ?? 0), 'sample' => array_values(array_map('strval', (array) ($left['sample'] ?? [])))];
                        $warnings[] = ['check' => 'unreferenced_files', 'label' => $label, 'detail' => $leftovers[$name]['count'].' file(s), '.$leftovers[$name]['bytes'].' bytes, that no imported record references (orphans); cleanup removes them.'];
                    }
                } else {
                    $blocking[] = ['check' => 'business_files_unmigrated', 'label' => $label, 'detail' => 'Business files whose importer did not complete, or whose file ledger has a problem (a file an imported record needs could not be moved); they cannot be preserved-only or discarded.'];
                }

                continue;
            }

            if (isset($unownedInput[$asset['kind']][$name]) && ! isset($keep[$name]) && ! isset($discard[$name])) {
                $passed[] = ['check' => 'unowned', 'label' => $label, 'detail' => 'Nobody owns it: removed right after verification.'];
                $this->unowned[$asset['kind']][] = $name;

                continue;
            }

            $decided = $asset['kind'] === 'tables' && ! isset($keep[$name]) && ! isset($discard[$name]) && ! in_array($name, self::OBSOLETE_TABLES, true);
            if ($decided && isset($byTable[$name])) {
                $entry = $byTable[$name];
                if ($entry['status'] === 'certified') {
                    $passed[] = ['check' => 'table_certified', 'label' => $label, 'detail' => $this->countDetail($entry).': the table is released for cleanup.'];
                    $eligible['tables'][] = $name;
                } else {
                    $warnings[] = ['check' => 'table_kept_count_mismatch', 'label' => $label, 'detail' => $this->countDetail($entry).': the table is kept.'];
                    $preserved['tables'][] = $name;
                }

                continue;
            }
            if ($decided && in_array($name, self::WATCHDOG_TABLES, true)) {
                $passed[] = ['check' => 'watchdog_table', 'label' => $label, 'detail' => 'Legacy watchdog table; released with its group.'];
                $eligible['tables'][] = $name;
                $groups['watchdog'][] = $name;
                $sideTables['watchdog'][] = $name;

                continue;
            }
            if ($decided && isset($sideOf[$name])) {
                [$parent, $suffix] = $sideOf[$name];
                $parentOk = ($byTable[$parent]['status'] ?? null) === 'certified';
                if ($parentOk) {
                    $passed[] = ['check' => 'side_table_released', 'label' => $label, 'detail' => "Side table of {$parent}; it goes with its certified table."];
                    $eligible['tables'][] = $name;
                    $groups[$parent][] = $name;
                    $sideTables['released_with_parent'][] = $name;
                } else {
                    $preserved['tables'][] = $name;
                    $sideTables['kept_with_uncertified_parent'][] = $name;
                }

                continue;
            }
            if ($asset['kind'] === 'tables' && in_array($name, self::OBSOLETE_TABLES, true) && ! isset($keep[$name]) && ! isset($discard[$name])) {
                $passed[] = ['check' => 'obsolete_table', 'label' => $label, 'detail' => 'Not used by Epesi 3; dropped once verification has passed.'];
                $eligible['tables'][] = $name;
                $obsolete[] = $name;
            } elseif (isset($discard[$name])) {
                $warnings[] = ['check' => 'approved_discard', 'label' => $label, 'detail' => 'Explicitly approved for discard.'];
                $eligible[$asset['kind']][] = $name;
                $discarded[$asset['kind']][] = $name;
            } elseif ($outcome === 'supported') {
                if (isset($completed[(string) ($row['importer'] ?? '')])) {
                    $passed[] = ['check' => 'coverage', 'label' => $label, 'detail' => 'Imported by '.$row['importer']];
                    $eligible[$asset['kind']][] = $name;
                } else {
                    $blocking[] = ['check' => 'importer_incomplete', 'label' => $label, 'detail' => 'Its importer has not completed.'];
                }
            } elseif (isset($keep[$name]) || ($coreOnly && $outcome === 'known_but_not_migrated')) {
                $preserved[$asset['kind']][] = $name;
            } elseif ($asset['rows'] === 0 && $outcome === 'known_but_not_migrated') {
                // A known module's empty table holds nothing to lose; it stays until an importer claims it.
                $preserved[$asset['kind']][] = $name;
            } elseif ($keepUnclassified) {
                // What no importer claims and is not removed as unowned (Premium, Custom and unknown tables, the
                // data/ folders, the loose files of data/) is kept as it is: never a reason to stop.
                $preserved[$asset['kind']][] = $name;
            } else {
                $blocking[] = ['check' => 'unclassified', 'label' => $label, 'detail' => $outcome === 'unknown'
                    ? 'Unknown legacy data: preserve, support or explicitly approve its discard.'
                    : 'No importer migrates it: continue with Core only or explicitly preserve it.'];
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function list(array $input, string $key): array
    {
        $value = $input[$key] ?? [];
        if (! is_array($value)) {
            throw new InvalidArgumentException("Verification input '{$key}' must be a list.");
        }

        return array_values($value);
    }

    /** @param  array<string, mixed>  $row */
    private function label(array $row): string
    {
        $label = $row['label'] ?? null;
        if (! is_string($label) || $label === '') {
            throw new InvalidArgumentException('Every verification check needs a label.');
        }

        return $label;
    }

    /** @param  array<string, mixed>  $row */
    private function int(array $row, string $key): int
    {
        if (! isset($row[$key]) || ! is_numeric($row[$key])) {
            throw new InvalidArgumentException("Verification count '{$key}' must be numeric.");
        }

        return (int) $row[$key];
    }

    /**
     * Judge each declared table by its counts. Exact: source = imported + skipped, and no source id without a record.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function certify(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            $label = $this->label($entry);
            $source = $this->int($entry, 'source');
            $imported = $this->int($entry, 'imported');
            $skipped = $this->int($entry, 'skipped');
            $missing = $this->int($entry, 'missing_ids');
            $unmigrated = is_array($entry['unmigrated_columns'] ?? null) ? array_values(array_map('strval', $entry['unmigrated_columns'])) : [];
            $pending = is_array($entry['pending'] ?? null) ? array_values(array_map('strval', $entry['pending'])) : [];
            if (! is_string($entry['table'] ?? null) || $entry['table'] === '') {
                throw new InvalidArgumentException('Every certified table needs a name.');
            }
            $out[] = [
                'label' => $label,
                'importer' => (string) ($entry['importer'] ?? ''),
                'table' => $entry['table'],
                'source' => $source,
                'imported' => $imported,
                'skipped' => $skipped,
                'skip_label' => is_string($entry['skip_label'] ?? null) ? $entry['skip_label'] : null,
                'missing_ids' => $missing,
                'unmigrated_columns' => $unmigrated,
                'pending' => $pending,
                'sides' => is_array($entry['sides'] ?? null) ? array_values(array_map('strval', $entry['sides'])) : [],
                'status' => $source === $imported + $skipped && $missing === 0 && $unmigrated === [] && $pending === [] ? 'certified' : 'kept',
            ];
        }

        return $out;
    }

    /** "source 120 = 115 imported + 5 inactive" or, on a mismatch, "source 120 but 114 imported + 5 inactive". */
    private function countDetail(array $entry): string
    {
        $rest = $entry['imported'].' imported'.($entry['skipped'] > 0 ? ' + '.$entry['skipped'].' '.($entry['skip_label'] ?? 'skipped') : '');
        $text = $entry['source'] === $entry['imported'] + $entry['skipped'] && $entry['missing_ids'] === 0 ? "source {$entry['source']} = {$rest}" : "source {$entry['source']} but {$rest}";

        $text = $entry['missing_ids'] > 0 ? $text.", {$entry['missing_ids']} source id(s) have no record" : $text;
        $text = $entry['pending'] === [] ? $text : $text.'; '.implode('; ', $entry['pending']);

        return $entry['unmigrated_columns'] === [] ? $text : $text.'; column(s) '.implode(', ', $entry['unmigrated_columns']).' not carried over yet';
    }
}
