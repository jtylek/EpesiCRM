<?php

namespace App\Services\LegacyImport;

use Illuminate\Database\Connection;

/**
 * What a legacy table still holds for an importer that does not exist yet (CountedTables `pending`). A link to a
 * recordset nothing imports survives only in the legacy table that names it, so that table stays while the recordset's
 * own table is there for a future importer to read. A link to a recordset whose table is gone can never be restored
 * from this database and keeps nothing.
 */
final class LegacyPendingLinks
{
    /**
     * @param  array<string, int>  $linksByRecordset  legacy recordset name => links naming it
     * @return list<string>
     */
    public static function describe(Connection $legacy, LegacyRecordRefs $refs, array $linksByRecordset): array
    {
        $pending = [];
        ksort($linksByRecordset, SORT_STRING);
        foreach ($linksByRecordset as $recordset => $links) {
            if (! $refs->ports((string) $recordset) && preg_match('/^\w+$/D', (string) $recordset) === 1 && $legacy->getSchemaBuilder()->hasTable($recordset.'_data_1')) {
                $pending[] = "{$links} link(s) to {$recordset} records, which no importer brings over yet";
            }
        }

        return $pending;
    }

    /**
     * Recordsets that hold records and have a field of type "file": their values are legacy file-storage ids, which
     * only the utils_filestorage tables can turn into files.
     *
     * @param  list<string>  $except  recordsets whose files were migrated
     * @return list<string>
     */
    public static function fileFields(Connection $legacy, array $except = []): array
    {
        $schema = $legacy->getSchemaBuilder();
        $prefix = (string) $legacy->getTablePrefix();
        $pending = [];
        foreach ($schema->getTables() as $table) {
            $name = (string) $table['name'];
            if (! str_starts_with($name, $prefix) || ! str_ends_with($name, '_field')) {
                continue;
            }
            $recordset = substr($name, strlen($prefix), -strlen('_field'));
            if (in_array($recordset, $except, true) || ! $schema->hasTable($recordset.'_data_1') || ! $schema->hasColumn($recordset.'_field', 'type')) {
                continue;
            }
            if ($legacy->table($recordset.'_field')->where('type', 'file')->exists() && $legacy->table($recordset.'_data_1')->exists()) {
                $pending[] = "{$recordset} has file fields that name legacy file-storage ids";
            }
        }
        sort($pending, SORT_STRING);

        return $pending;
    }
}
