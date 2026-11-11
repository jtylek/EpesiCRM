<?php

use Epesi\Modules\MigrationWizard\Database\ObsoleteLegacyTables;
use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMigrationVerifierTest extends TestCase
{
    private static function table(string $name, string $outcome, ?string $importer = null, int $rows = 5): array
    {
        return ['table' => $name, 'outcome' => $outcome, 'importer' => $importer, 'estimated_rows' => $rows];
    }

    private static function area(string $name, string $outcome, ?string $importer = null, int $count = 2): array
    {
        return ['area' => $name, 'outcome' => $outcome, 'importer' => $importer, 'count' => $count];
    }

    /** A fully clean run: every fact good, one supported table and nothing else. */
    private static function clean(array $override = []): array
    {
        return $override + [
            'counts' => [['label' => 'users', 'source' => 3, 'destination' => 3]],
            'legacy_ids' => [['label' => 'users', 'source_ids' => [1, 2, 3], 'destination_ids' => ['3', '2', '1']]],
            'ledger' => ['checked' => 2, 'problems' => []],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users')], 'files' => []],
            'completed_importers' => ['users'],
        ];
    }

    private static function verify(array $input): array
    {
        return (new MigrationVerifier)->verify($input);
    }

    public function test_a_clean_run_passes_and_releases_only_the_verified_table(): void
    {
        $report = self::verify(self::clean());
        self::assertSame('pass', $report['status']);
        self::assertTrue($report['cleanup_allowed']);
        self::assertSame('verified', $report['outcome']);
        self::assertSame(['user_login'], $report['cleanup_eligible_tables']);
        self::assertSame([], $report['blocking']);
        self::assertGreaterThanOrEqual(4, count($report['passed']));
    }

    public function test_count_mismatch_and_missing_legacy_ids_block(): void
    {
        $report = self::verify(self::clean([
            'counts' => [['label' => 'users', 'source' => 3, 'destination' => 2]],
            'legacy_ids' => [['label' => 'users', 'source_ids' => [1, 2, 3], 'destination_ids' => [1, 2, 99]]],
        ]));
        self::assertSame('block', $report['status']);
        self::assertFalse($report['cleanup_allowed']);
        self::assertSame('not_verified', $report['outcome']);
        $checks = array_column($report['blocking'], 'check');
        self::assertContains('count', $checks);
        self::assertContains('legacy_id_coverage', $checks);
        // The extra destination id only warns.
        self::assertSame('legacy_id_extra', $report['warnings'][0]['check']);
    }

    public function test_any_file_ledger_problem_blocks_and_an_unverified_ledger_blocks(): void
    {
        $report = self::verify(self::clean(['ledger' => ['checked' => 2, 'problems' => [['source' => 'a.jpg', 'problem' => 'destination_missing']]]]));
        self::assertFalse($report['cleanup_allowed']);
        self::assertSame('a.jpg', $report['blocking'][0]['label']);

        $input = self::clean();
        unset($input['ledger']);
        self::assertFalse(self::verify($input)['cleanup_allowed']);
    }

    public function test_data_nobody_owns_is_listed_for_removal_and_a_name_kept_on_purpose_stays(): void
    {
        $coverage = ['tables' => [self::table('user_login', 'supported', 'users'), self::table('old_module_data', 'unknown'), self::table('kept_by_name', 'unknown')], 'files' => [self::area('data/cache', 'unknown')]];
        $report = self::verify(self::clean([
            'coverage' => $coverage,
            'preserved' => ['kept_by_name'],
            'unowned' => ['tables' => ['old_module_data', 'kept_by_name'], 'files' => ['data/cache']],
        ]));

        self::assertTrue($report['cleanup_allowed']);
        self::assertSame(['old_module_data'], $report['unowned_tables']);
        self::assertSame(['data/cache'], $report['unowned_file_areas']);
        self::assertSame(['kept_by_name'], $report['preserved_tables']);
        self::assertNotContains('old_module_data', $report['cleanup_eligible_tables'], 'removed right after verification, not by the optional cleanup');
        self::assertNotContains('old_module_data', $report['preserved_tables']);
    }

    public function test_what_no_importer_claims_and_is_not_removed_is_kept_and_never_blocks(): void
    {
        $coverage = ['tables' => [self::table('user_login', 'supported', 'users'), self::table('premium_print_templates_data_1', 'unknown')], 'files' => [self::area('data/*', 'unknown'), self::area('data/Base_Lang', 'unknown')]];

        $report = self::verify(self::clean(['coverage' => $coverage, 'keep_unclassified' => true]));
        self::assertTrue($report['cleanup_allowed']);
        self::assertSame([], $report['blocking']);
        self::assertSame(['premium_print_templates_data_1'], $report['preserved_tables']);
        self::assertSame(['data/*', 'data/Base_Lang'], $report['preserved_file_areas']);
        self::assertSame([], $report['unowned_tables']);

        // Without that, unknown data still has to be decided first.
        $strict = self::verify(self::clean(['coverage' => $coverage]));
        self::assertFalse($strict['cleanup_allowed']);
        self::assertContains('unclassified', array_column($strict['blocking'], 'check'));
    }

    public function test_a_missing_legacy_table_warns_and_does_not_block(): void
    {
        $report = self::verify(self::clean(['missing_tables' => ['company_data_1']]));

        self::assertTrue($report['cleanup_allowed']);
        self::assertSame([], $report['blocking']);
        self::assertContains('table_missing', array_column($report['warnings'], 'check'));
    }

    public function test_files_the_legacy_never_had_warn_but_do_not_block(): void
    {
        $report = self::verify(self::clean(['ledger' => ['checked' => 3, 'problems' => [], 'absent' => ['CRM_Mail/attachments/17/aa', 'CRM_Mail/attachments/20/bb']]]));

        self::assertTrue($report['cleanup_allowed']);
        self::assertSame([], $report['blocking']);
        $absent = array_values(array_filter($report['warnings'], static fn (array $row): bool => $row['check'] === 'source_files_absent'));
        self::assertCount(1, $absent);
        self::assertStringStartsWith('2 file(s)', $absent[0]['detail']);
    }

    public function test_a_missing_coverage_report_blocks(): void
    {
        $input = self::clean();
        unset($input['coverage']);
        $report = self::verify($input);
        self::assertFalse($report['cleanup_allowed']);
        self::assertSame('coverage', $report['blocking'][0]['check']);
    }

    public function test_supported_table_needs_a_completed_importer(): void
    {
        $report = self::verify(self::clean(['completed_importers' => []]));
        self::assertFalse($report['cleanup_allowed']);
        self::assertSame([], $report['cleanup_eligible_tables']);
        self::assertSame('importer_incomplete', $report['blocking'][0]['check']);
    }

    public function test_unknown_data_blocks_until_preserved_or_discard_is_approved_exactly(): void
    {
        $coverage = ['tables' => [self::table('user_login', 'supported', 'users'), self::table('custom_invoice', 'unknown')], 'files' => [self::area('data/Custom_Invoice', 'unknown')]];

        $blocked = self::verify(self::clean(['coverage' => $coverage]));
        self::assertFalse($blocked['cleanup_allowed']);
        self::assertCount(2, $blocked['blocking']);

        $preserved = self::verify(self::clean(['coverage' => $coverage, 'preserved' => ['custom_invoice', 'data/Custom_Invoice']]));
        self::assertTrue($preserved['cleanup_allowed']);
        self::assertSame('warn', $preserved['status']);
        self::assertSame('core_complete_with_preserved_data', $preserved['outcome']);
        self::assertTrue($preserved['preserved_for_assisted_migration']);
        self::assertSame(['custom_invoice'], $preserved['preserved_tables']);
        self::assertSame(['user_login'], $preserved['cleanup_eligible_tables']);

        // A discard approval must name the exact table; a near-match authorises nothing.
        $wrong = self::verify(self::clean(['coverage' => $coverage, 'approved_discards' => ['custom_invoice_2', 'data/Custom_Invoice']]));
        self::assertFalse($wrong['cleanup_allowed']);

        $discarded = self::verify(self::clean(['coverage' => $coverage, 'approved_discards' => ['custom_invoice', 'data/Custom_Invoice']]));
        self::assertTrue($discarded['cleanup_allowed']);
        self::assertSame(['custom_invoice', 'user_login'], $discarded['cleanup_eligible_tables']);
        self::assertSame(['data/Custom_Invoice'], $discarded['cleanup_eligible_file_areas']);
        self::assertSame('warn', $discarded['status']);
    }

    public function test_core_only_preserves_known_unmigrated_data_but_never_unknown_data(): void
    {
        $coverage = ['tables' => [
            self::table('task_data_1', 'known_but_not_migrated', null, 9),
            self::table('custom_invoice', 'unknown'),
        ], 'files' => []];
        $report = self::verify(self::clean(['coverage' => $coverage, 'core_only' => true]));
        self::assertFalse($report['cleanup_allowed']);
        self::assertSame(['Table custom_invoice'], array_column($report['blocking'], 'label'));
        self::assertSame(['task_data_1'], $report['preserved_tables']);

        $without = self::verify(self::clean(['coverage' => ['tables' => [self::table('task_data_1', 'known_but_not_migrated', null, 9)], 'files' => []]]));
        self::assertFalse($without['cleanup_allowed']);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::verify(self::clean(['counts' => [['label' => '', 'source' => 1, 'destination' => 1]]]));
    }

    public function test_a_choice_that_names_nothing_is_a_typo_and_blocks(): void
    {
        $coverage = ['tables' => [self::table('custom_invoice', 'unknown')], 'files' => []];

        $preserve = self::verify(self::clean(['coverage' => $coverage, 'preserved' => ['custom_invoice', 'custom_invoicee']]));
        self::assertFalse($preserve['cleanup_allowed']);
        self::assertSame(['custom_invoicee'], array_column(array_filter($preserve['blocking'], static fn (array $b): bool => $b['check'] === 'unknown_choice'), 'label'));

        $discard = self::verify(self::clean(['coverage' => $coverage, 'preserved' => ['custom_invoice'], 'approved_discards' => ['nothing_here']]));
        self::assertFalse($discard['cleanup_allowed']);
        self::assertContains('unknown_choice', array_column($discard['blocking'], 'check'));
    }

    public function test_the_report_names_exactly_what_was_discarded_and_what_was_preserved(): void
    {
        $coverage = ['tables' => [self::table('keep_me', 'unknown'), self::table('drop_me', 'unknown')], 'files' => [self::area('data/Keep', 'unknown'), self::area('data/Drop', 'unknown')]];

        $report = self::verify(self::clean(['coverage' => $coverage, 'preserved' => ['keep_me', 'data/Keep'], 'approved_discards' => ['drop_me', 'data/Drop']]));

        self::assertTrue($report['cleanup_allowed']);
        self::assertSame(['drop_me'], $report['discarded_tables']);
        self::assertSame(['data/Drop'], $report['discarded_file_areas']);
        self::assertSame(['keep_me'], $report['preserved_tables']);
        self::assertSame(['data/Keep'], $report['preserved_file_areas']);
        self::assertSame(['drop_me'], $report['cleanup_eligible_tables']);
        self::assertSame(['data/Drop'], $report['cleanup_eligible_file_areas']);
    }

    public function test_nothing_is_discarded_unless_it_was_approved_by_name(): void
    {
        $coverage = ['tables' => [self::table('custom_invoice', 'unknown')], 'files' => []];
        $report = self::verify(self::clean(['coverage' => $coverage, 'preserved' => ['custom_invoice'], 'core_only' => true]));

        self::assertSame([], $report['discarded_tables']);
        self::assertSame([], $report['cleanup_eligible_file_areas']);
        self::assertNotContains('custom_invoice', $report['cleanup_eligible_tables']);
    }

    public function test_mail_attachment_files_are_never_unknown_preserved_only_or_discardable_before_their_ledger_verifies(): void
    {
        $coverage = ['tables' => [], 'files' => [self::area('data/CRM_Mail', 'unknown', null, 40)]];

        foreach ([['preserved' => ['data/CRM_Mail']], ['approved_discards' => ['data/CRM_Mail']], ['core_only' => true], []] as $choice) {
            $report = self::verify(self::clean(['coverage' => $coverage] + $choice));
            self::assertFalse($report['cleanup_allowed']);
            self::assertContains('business_files_unmigrated', array_column($report['blocking'], 'check'));
            self::assertSame([], $report['preserved_file_areas']);
            self::assertSame([], $report['cleanup_eligible_file_areas']);
        }

        $migrated = self::verify(self::clean(['coverage' => $coverage, 'migrated_file_areas' => ['data/CRM_Mail']]));
        self::assertTrue($migrated['cleanup_allowed']);
        self::assertSame(['data/CRM_Mail'], $migrated['cleanup_eligible_file_areas']);
        self::assertSame([], $migrated['discarded_file_areas']);
    }

    public function test_the_two_obsolete_tables_are_released_without_a_choice_and_never_block_or_count_as_preserved(): void
    {
        $report = self::verify(self::clean(['coverage' => ['tables' => [
            self::table('user_login', 'supported', 'users'),
            self::table('recordbrowser_search_index', 'unknown', null, 4000000),
            self::table('rc_cache_messages', 'known_but_not_migrated', null, 900),
        ], 'files' => []]]));

        self::assertSame('pass', $report['status']);
        self::assertTrue($report['cleanup_allowed']);
        self::assertSame('verified', $report['outcome']);
        self::assertSame(['rc_cache_messages', 'recordbrowser_search_index'], $report['obsolete_tables']);
        self::assertSame(['rc_cache_messages', 'recordbrowser_search_index', 'user_login'], $report['cleanup_eligible_tables']);
        self::assertSame([], $report['preserved_tables']);
        self::assertSame([], $report['discarded_tables']);
        self::assertSame([], $report['blocking']);
    }

    public function test_the_obsolete_list_is_exactly_the_approved_names_and_never_a_pattern_or_an_epesi_record_table(): void
    {
        self::assertSame([
            'recordbrowser_search_index', 'rc_cache', 'rc_cache_index', 'rc_cache_messages', 'rc_cache_shared', 'rc_cache_thread',
            'rc_session', 'rc_searches', 'rc_system', 'rc_dictionary', 'rc_users', 'rc_identities', 'rc_contacts',
            'rc_contactgroups', 'rc_contactgroupmembers',
        ], MigrationVerifier::OBSOLETE_TABLES);

        foreach (MigrationVerifier::OBSOLETE_TABLES as $table) {
            // Mail accounts, archived mail and its links are Epesi records, whatever their prefix looks like.
            self::assertSame(0, preg_match('/^rc_(accounts|mails|mail_threads|multiple_emails|related)(_|$)/', $table), "{$table} is an Epesi record table");
            self::assertSame(1, preg_match('/^[a-z0-9_]+$/', $table), "{$table} must be an exact table name, not a pattern");
        }
        self::assertSame(array_unique(MigrationVerifier::OBSOLETE_TABLES), MigrationVerifier::OBSOLETE_TABLES);
        // Every one is a table of the 1.9.1 schema fixture, and every Epesi record table of it is out.
        $fixture = (string) file_get_contents(dirname(__DIR__).'/Fixtures/legacy-1.9.1-schema.sql');
        foreach (MigrationVerifier::OBSOLETE_TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE `'.$table.'` (', $fixture, $table);
        }
        // The drop order covers exactly the same names.
        $order = ObsoleteLegacyTables::DROP_ORDER;
        self::assertEqualsCanonicalizing(MigrationVerifier::OBSOLETE_TABLES, $order);
        self::assertSame(count(MigrationVerifier::OBSOLETE_TABLES), count($order));
    }

    public function test_the_drop_order_has_every_foreign_key_child_before_its_parent_in_the_real_schema(): void
    {
        $fixture = (string) file_get_contents(dirname(__DIR__).'/Fixtures/legacy-1.9.1-schema.sql');
        $order = array_flip(ObsoleteLegacyTables::DROP_ORDER);
        preg_match_all('/CREATE TABLE `([a-z0-9_]+)` \((.*?)\n\) ENGINE/s', $fixture, $tables, PREG_SET_ORDER);
        $checked = 0;
        foreach ($tables as [, $name, $body]) {
            preg_match_all('/REFERENCES `([a-z0-9_]+)`/', $body, $parents);
            foreach ($parents[1] as $parent) {
                if (isset($order[$parent])) {
                    // Anything pointing at an obsolete table is itself obsolete, and goes first.
                    self::assertArrayHasKey($name, $order, "{$name} references {$parent} but is not in the obsolete list");
                    self::assertLessThan($order[$parent], $order[$name], "{$name} must be dropped before {$parent}");
                    $checked++;
                }
            }
        }
        self::assertGreaterThanOrEqual(10, $checked);
    }

    public function test_only_the_exact_obsolete_names_are_built_in_and_any_other_table_still_needs_a_choice(): void
    {
        $report = self::verify(self::clean(['coverage' => ['tables' => [
            self::table('user_login', 'supported', 'users'),
            self::table('rc_accounts_data_1', 'known_but_not_migrated'),
            self::table('rc_mails_data_1', 'unknown'),
            self::table('rc_cache_old', 'unknown'),
            self::table('rc_users2', 'unknown'),
            self::table('recordbrowser_search_index2', 'unknown'),
            self::table('rc_ids', 'unknown'),
        ], 'files' => []]]));

        self::assertFalse($report['cleanup_allowed']);
        self::assertSame([], $report['obsolete_tables']);
        $blocked = array_map(static fn (string $label): string => substr($label, strlen('Table ')), array_column($report['blocking'], 'label'));
        sort($blocked);
        self::assertSame(['rc_accounts_data_1', 'rc_cache_old', 'rc_ids', 'rc_mails_data_1', 'rc_users2', 'recordbrowser_search_index2'], $blocked);
    }

    public function test_an_explicit_preserve_of_an_obsolete_table_is_honoured_and_an_absent_one_is_ignored(): void
    {
        $report = self::verify(self::clean([
            'preserved' => ['rc_cache_messages'],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('rc_cache_messages', 'unknown')], 'files' => []],
        ]));
        self::assertSame(['rc_cache_messages'], $report['preserved_tables']);
        self::assertSame([], $report['obsolete_tables']);
        self::assertNotContains('rc_cache_messages', $report['cleanup_eligible_tables']);

        $absent = self::verify(self::clean());
        self::assertSame([], $absent['obsolete_tables']);
        self::assertSame(['user_login'], $absent['cleanup_eligible_tables']);
    }

    public function test_files_nobody_references_do_not_block_and_are_reported_completely(): void
    {
        $coverage = ['tables' => [], 'files' => [self::area('data/Utils_FileStorage', 'unknown', null, 90)]];
        $left = ['data/Utils_FileStorage' => ['count' => 12, 'bytes' => 3400, 'sample' => ['data/Utils_FileStorage/a/b/c']]];

        $report = self::verify(self::clean(['coverage' => $coverage, 'migrated_file_areas' => ['data/Utils_FileStorage'], 'file_leftovers' => $left]));

        self::assertTrue($report['cleanup_allowed']);
        self::assertSame([], $report['blocking']);
        self::assertSame('warn', $report['status']);
        self::assertSame($left, $report['leftover_files']);
        self::assertSame(['count' => 12, 'bytes' => 3400], $report['leftover_total']);
        self::assertContains('unreferenced_files', array_column($report['warnings'], 'check'));
        self::assertSame(['data/Utils_FileStorage'], $report['cleanup_eligible_file_areas']);
        self::assertSame([], $report['preserved_file_areas']);

        $none = self::verify(self::clean(['coverage' => $coverage, 'migrated_file_areas' => ['data/Utils_FileStorage']]));
        self::assertSame([], $none['leftover_files']);
        self::assertSame(['count' => 0, 'bytes' => 0], $none['leftover_total']);
    }

    public function test_leftovers_never_hide_a_file_problem_or_an_area_that_was_not_migrated(): void
    {
        $coverage = ['tables' => [], 'files' => [self::area('data/Utils_FileStorage', 'unknown', null, 90)]];
        $left = ['data/Utils_FileStorage' => ['count' => 1, 'bytes' => 1, 'sample' => []]];

        // The importer did not complete (the area is not in migrated_file_areas): still blocked.
        $notMigrated = self::verify(self::clean(['coverage' => $coverage, 'file_leftovers' => $left]));
        self::assertFalse($notMigrated['cleanup_allowed']);
        self::assertContains('business_files_unmigrated', array_column($notMigrated['blocking'], 'check'));
        self::assertSame([], $notMigrated['leftover_files']);

        // A file an imported record needed but could not be moved is a ledger problem: blocked.
        $problem = self::verify(self::clean([
            'coverage' => $coverage, 'migrated_file_areas' => ['data/Utils_FileStorage'], 'file_leftovers' => $left,
            'ledger' => ['checked' => 3, 'problems' => [['source' => 'Utils_FileStorage/a/b/c', 'problem' => 'missing']]],
        ]));
        self::assertFalse($problem['cleanup_allowed']);
        self::assertContains('file_ledger', array_column($problem['blocking'], 'check'));
    }

    private static function counted(string $table, int $source, int $imported, int $skipped = 0, ?string $skipLabel = null, int $missing = 0, string $label = 'Companies'): array
    {
        return ['label' => $label, 'importer' => 'companies', 'table' => $table, 'source' => $source, 'imported' => $imported, 'skipped' => $skipped, 'skip_label' => $skipLabel, 'missing_ids' => $missing];
    }

    private static function recordset(string $prefix, array $suffixes = ['access', 'access_clearance', 'access_fields', 'callback', 'field', 'edit_history', 'edit_history_data', 'favorite', 'recent']): array
    {
        return [self::table($prefix.'_data_1', 'unknown', null, 120), ...array_map(static fn (string $suffix): array => self::table($prefix.'_'.$suffix, 'unknown', null, 3), $suffixes)];
    }

    public function test_a_table_whose_counts_match_exactly_is_released(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 120)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));

        self::assertSame(['company_data_1', 'user_login'], $report['cleanup_eligible_tables']);
        self::assertSame('certified', $report['table_certification'][0]['status']);
        self::assertContains('table_certified', array_column($report['passed'], 'check'));
        self::assertTrue($report['cleanup_allowed']);
        self::assertSame([], $report['preserved_tables']);
    }

    public function test_a_table_with_a_column_the_importer_did_not_carry_is_kept_although_its_counts_match(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 120) + ['unmigrated_columns' => ['f_scan']]],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));

        self::assertSame(['user_login'], $report['cleanup_eligible_tables']);
        self::assertSame(['company_data_1'], $report['preserved_tables']);
        self::assertSame('kept', $report['table_certification'][0]['status']);
        self::assertSame(['f_scan'], $report['table_certification'][0]['unmigrated_columns']);
        $warning = array_values(array_filter($report['warnings'], static fn (array $w): bool => $w['check'] === 'table_kept_count_mismatch'))[0];
        self::assertStringContainsString('source 120 = 120 imported', $warning['detail']);
        self::assertStringContainsString('column(s) f_scan not carried over yet', $warning['detail']);
    }

    public function test_off_by_one_keeps_the_table_and_reports_both_counts_without_blocking(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 119, missing: 1)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));

        self::assertSame(['user_login'], $report['cleanup_eligible_tables']);
        self::assertSame(['company_data_1'], $report['preserved_tables']);
        self::assertSame('kept', $report['table_certification'][0]['status']);
        self::assertTrue($report['cleanup_allowed'], 'a mismatch blocks nothing');
        self::assertSame([], $report['blocking']);
        $warning = array_values(array_filter($report['warnings'], static fn (array $w): bool => $w['check'] === 'table_kept_count_mismatch'))[0];
        self::assertStringContainsString('source 120 but 119 imported', $warning['detail']);
        self::assertStringContainsString('the table is kept', $warning['detail']);
    }

    public function test_a_declared_skip_rule_is_accounted_for_and_reported_separately(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 115, 5, 'inactive')],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));

        self::assertContains('company_data_1', $report['cleanup_eligible_tables']);
        $row = $report['table_certification'][0];
        self::assertSame([120, 115, 5, 'inactive'], [$row['source'], $row['imported'], $row['skipped'], $row['skip_label']]);
        $detail = array_values(array_filter($report['passed'], static fn (array $p): bool => $p['check'] === 'table_certified'))[0]['detail'];
        self::assertStringContainsString('source 120 = 115 imported + 5 inactive', $detail);
    }

    public function test_an_undeclared_skip_is_a_mismatch_and_the_table_stays(): void
    {
        // 5 rows missing and no skip rule declared for them: there is no tolerance.
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 115)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));
        self::assertNotContains('company_data_1', $report['cleanup_eligible_tables']);
        self::assertSame(['company_data_1'], $report['preserved_tables']);

        // A skip rule that does not add up is a mismatch too.
        $wrong = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 115, 4, 'inactive')],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));
        self::assertNotContains('company_data_1', $wrong['cleanup_eligible_tables']);
    }

    public function test_equal_counts_with_a_source_id_that_has_no_record_do_not_certify(): void
    {
        // A merge (several source rows became one record) can leave the counts equal by accident of extra records.
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 120, missing: 2)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120)], 'files' => []],
        ]));
        self::assertNotContains('company_data_1', $report['cleanup_eligible_tables']);
        self::assertSame(['company_data_1'], $report['preserved_tables']);
    }

    public function test_a_declared_side_table_follows_its_parent_either_way(): void
    {
        $tables = [self::table('user_login', 'supported', 'users'), self::table('utils_attachment_data_1', 'known_but_not_migrated'), self::table('utils_filestorage', 'known_but_not_migrated')];
        $notes = self::counted('utils_attachment_data_1', 10, 10) + ['sides' => ['utils_filestorage']];

        $released = self::verify(self::clean(['table_certification' => [$notes], 'coverage' => ['tables' => $tables, 'files' => []]]));
        self::assertEqualsCanonicalizing(['user_login', 'utils_attachment_data_1', 'utils_filestorage'], $released['cleanup_eligible_tables']);
        self::assertSame(['utils_filestorage'], $released['table_groups']['utils_attachment_data_1']);

        // Equal counts, but a link in this table still has a future importer to go to: both stay and nothing is blocked.
        $kept = self::verify(self::clean(['table_certification' => [$notes + ['pending' => ['3 link(s) to premium_widgets records']]], 'coverage' => ['tables' => $tables, 'files' => []]]));
        self::assertSame('kept', $kept['table_certification'][0]['status']);
        self::assertEqualsCanonicalizing(['utils_attachment_data_1', 'utils_filestorage'], $kept['preserved_tables']);
        self::assertSame(['user_login'], $kept['cleanup_eligible_tables']);
        self::assertSame([], $kept['blocking']);
        self::assertStringContainsString('3 link(s) to premium_widgets records', json_encode($kept['warnings'], JSON_THROW_ON_ERROR));
    }

    public function test_every_side_table_goes_with_its_certified_parent(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 120)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), ...self::recordset('company')], 'files' => []],
        ]));

        $sides = ['company_access', 'company_access_clearance', 'company_access_fields', 'company_callback', 'company_field', 'company_edit_history', 'company_edit_history_data', 'company_favorite', 'company_recent'];
        self::assertEqualsCanonicalizing(['company_data_1', 'user_login', ...$sides], $report['cleanup_eligible_tables']);
        self::assertEqualsCanonicalizing($sides, $report['table_groups']['company_data_1']);
        self::assertEqualsCanonicalizing($sides, $report['side_tables']['released_with_parent']);
        self::assertSame([], $report['preserved_tables']);
        self::assertTrue($report['cleanup_allowed']);
    }

    public function test_the_watchdog_tables_are_released_as_one_exact_group_and_a_preserve_wins(): void
    {
        $tables = [self::table('user_login', 'supported', 'users'), ...array_map(static fn (string $t): array => self::table($t, 'unknown', null, 40), MigrationVerifier::WATCHDOG_TABLES), self::table('utils_watchdog_extra', 'unknown')];
        $report = self::verify(self::clean(['coverage' => ['tables' => $tables, 'files' => []], 'preserved' => ['utils_watchdog_extra']]));

        self::assertEqualsCanonicalizing(['user_login', ...MigrationVerifier::WATCHDOG_TABLES], $report['cleanup_eligible_tables']);
        self::assertSame(['utils_watchdog_extra'], $report['preserved_tables']);
        self::assertSame([], $report['blocking']);

        $kept = self::verify(self::clean(['coverage' => ['tables' => $tables, 'files' => []], 'preserved' => ['utils_watchdog_event', 'utils_watchdog_extra']]));
        self::assertNotContains('utils_watchdog_event', $kept['cleanup_eligible_tables']);
        self::assertContains('utils_watchdog_event', $kept['preserved_tables']);

        $fixture = (string) file_get_contents(dirname(__DIR__).'/Fixtures/legacy-1.9.1-schema.sql');
        foreach (MigrationVerifier::WATCHDOG_TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE `'.$table.'` (', $fixture);
        }
    }

    public function test_metadata_tables_never_go_without_their_certified_parent(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 119)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), ...self::recordset('company')], 'files' => []],
        ]));

        self::assertSame(['user_login'], $report['cleanup_eligible_tables']);
        self::assertSame([], $report['table_groups']);
        self::assertCount(9, $report['side_tables']['kept_with_uncertified_parent']);
        self::assertSame([], $report['side_tables']['released_with_parent']);
        self::assertContains('company_field', $report['preserved_tables']);
        self::assertTrue($report['cleanup_allowed']);
    }

    public function test_a_mismatch_in_one_table_does_not_stop_another_from_being_released(): void
    {
        $report = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 120, 119), self::counted('contact_data_1', 40, 40, label: 'Contacts')],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 120), self::table('contact_data_1', 'unknown', null, 40)], 'files' => []],
        ]));

        self::assertContains('contact_data_1', $report['cleanup_eligible_tables']);
        self::assertNotContains('company_data_1', $report['cleanup_eligible_tables']);
        self::assertTrue($report['cleanup_allowed']);
    }

    public function test_a_table_with_no_importer_stays_whatever_its_row_count(): void
    {
        foreach ([0, 5000] as $rows) {
            $input = self::clean(['coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('premium_x_data_1', 'unknown', null, $rows)], 'files' => []]]);
            $unchosen = self::verify($input);
            self::assertNotContains('premium_x_data_1', $unchosen['cleanup_eligible_tables']);
            self::assertFalse($unchosen['cleanup_allowed'], 'it still needs the operator to preserve it');

            $kept = self::verify($input + ['preserved' => ['premium_x_data_1']]);
            self::assertSame(['premium_x_data_1'], $kept['preserved_tables']);
            self::assertNotContains('premium_x_data_1', $kept['cleanup_eligible_tables']);
        }
    }

    public function test_an_empty_source_certifies_only_when_an_importer_declares_it(): void
    {
        $declared = self::verify(self::clean([
            'table_certification' => [self::counted('company_data_1', 0, 0)],
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'unknown', null, 0)], 'files' => []],
        ]));
        self::assertContains('company_data_1', $declared['cleanup_eligible_tables']);

        $undeclared = self::verify(self::clean([
            'coverage' => ['tables' => [self::table('user_login', 'supported', 'users'), self::table('company_data_1', 'known_but_not_migrated', null, 0)], 'files' => []],
            'preserved' => [],
        ]));
        self::assertNotContains('company_data_1', $undeclared['cleanup_eligible_tables']);
    }
}
