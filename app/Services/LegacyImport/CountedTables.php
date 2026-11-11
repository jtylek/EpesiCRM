<?php

namespace App\Services\LegacyImport;

/**
 * What an importer says about the legacy tables it brings over, so the Migration Wizard can
 * certify a table by counting: the records in the table must equal the records the importer
 * created from it (by `legacy_id`), counted in the database at verification time, never from
 * a counter the importer reported.
 *
 * Declared here, by the importer, in one place:
 *  - `table`: the legacy table, under its original name;
 *  - `model`: the model that gets one record per legacy row, with a `legacy_id` column;
 *  - `skip`: null when the importer takes every row, or the exact rule for the rows it
 *    deliberately does not take: `['column' => 'active', 'value' => 0, 'label' => 'inactive']`
 *    (rows where `column` equals `value`). The skipped rows are counted separately and reported
 *    ("source 120 = 115 imported + 5 inactive"); there is no tolerance.
 *
 * A table whose rows do not map one to one onto records with a `legacy_id` (attachments named by
 * their mail, threads rebuilt from the messages, accounts merged by address) declares instead:
 *  - `count`: fn (Connection $legacy, array $absentFiles): array{source: int, imported: int,
 *    skipped: int, missing_ids: int}, measured row by row in the database now. `$absentFiles` holds the
 *    file ledger's sources that the legacy installation itself never had (path => true);
 *  - `label` and `skip_label`: what the report calls the table and its skipped rows.
 *
 * Optional for every table:
 *  - `optional`: the table may not exist on an installation, and that is not worth a warning;
 *  - `sides`: tables outside the `<tab>_*` family that go with this one and stay with it;
 *  - `pending`: fn (Connection $legacy): list<string>, data that exists only in this table and
 *    still has somewhere to go (a link to a recordset whose legacy table is there, waiting for its
 *    importer). While any is left the table is kept, whatever the counts say.
 *
 * A table no importer declares has not been compared with anything.
 */
interface CountedTables
{
    /** @return list<array<string, mixed>> */
    public function countedTables(): array;
}
