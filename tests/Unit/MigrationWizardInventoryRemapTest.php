<?php

use Epesi\Modules\MigrationWizard\Detection\LegacyInventory;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use PHPUnit\Framework\TestCase;

final class MigrationWizardInventoryRemapTest extends TestCase
{
    public function test_renamed_legacy_tables_are_reported_under_their_original_names_and_nothing_else(): void
    {
        $map = ['user_login' => 'legacy_user_login', 'modules' => 'legacy_modules'];
        $byCurrent = [
            'legacy_user_login' => ['id', 'login'],
            'legacy_modules' => ['name', 'version'],
            'users' => ['id', 'name', 'email'],          // an Epesi 3 table
            'modules' => ['id', 'module_id'],            // Epesi 3's own `modules`
            'other_app' => ['id'],                       // a table that was there before
        ];

        $remapped = LegacyInventory::remap($byCurrent, $map);

        self::assertSame(['user_login' => ['id', 'login'], 'modules' => ['name', 'version']], $remapped);
    }

    public function test_the_fingerprint_of_the_remapped_tables_equals_the_one_before_the_rename(): void
    {
        $before = ['modules' => ['name', 'version'], 'user_login' => ['id', 'login']];
        $after = ['legacy_modules' => ['name', 'version'], 'legacy_user_login' => ['id', 'login'], 'modules' => ['id'], 'users' => ['id']];
        $map = ['modules' => 'legacy_modules', 'user_login' => 'legacy_user_login'];

        $fingerprint = new SchemaFingerprint;

        self::assertSame($fingerprint->hash($before), $fingerprint->hash(LegacyInventory::remap($after, $map)));
    }

    public function test_an_empty_map_changes_nothing(): void
    {
        $tables = ['a' => ['id'], 'b' => ['id']];

        self::assertSame($tables, LegacyInventory::remap($tables, []));
    }
}
