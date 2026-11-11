<?php

namespace Tests\Unit;

use App\Services\LegacyImport\LegacyStockColumns;
use Epesi\Modules\MigrationWizard\Detection\ApprovedLegacySchemas;
use Epesi\Modules\MigrationWizard\Detection\SchemaFingerprint;
use PHPUnit\Framework\TestCase;

/** The stock column lists are the 1.9.1 fixture's, and agree with the wizard's approved table hashes. */
class LegacyStockColumnsTest extends TestCase
{
    public function test_every_list_is_the_fixtures_column_list(): void
    {
        $sql = (string) file_get_contents(__DIR__.'/../Fixtures/legacy-1.9.1-schema.sql');

        foreach (LegacyStockColumns::COLUMNS as $table => $columns) {
            $this->assertSame(1, preg_match('/CREATE TABLE `'.$table.'` \((.*?)\) ENGINE/s', $sql, $match), $table);
            preg_match_all('/^\s*`([^`]+)`/m', $match[1], $found);
            $this->assertSame($found[1], $columns, $table);
        }
    }

    public function test_every_list_hashes_to_the_approved_stock_hash(): void
    {
        foreach (ApprovedLegacySchemas::EPESI_191_STOCK as $tables) {
            foreach ($tables as $table => $hash) {
                $this->assertSame($hash, SchemaFingerprint::tableHash(LegacyStockColumns::COLUMNS[$table]), $table);
            }
        }
    }

    public function test_the_recordset_tables_are_known_and_need_an_id_and_creation_time(): void
    {
        foreach (array_keys(LegacyStockColumns::RECORDSET_TABLES) as $table) {
            $this->assertArrayHasKey($table, LegacyStockColumns::COLUMNS);
        }
        $this->assertSame(['id', 'created_on'], LegacyStockColumns::REQUIRED);
    }
}
