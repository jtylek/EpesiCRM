<?php

/**
 * Finish the utf8mb4 conversion for columns that 20260814_utf8mb4_migration.php (§68) could
 * not convert because they sit on either side of a live foreign key.
 *
 * Root cause: that patch runs, per table, `ALTER TABLE <t> CHARACTER SET utf8mb4 ...` (sets the
 * table's default charset only - always succeeds) followed by
 * `ALTER TABLE <t> CONVERT TO CHARACTER SET utf8mb4 ...` (rewrites every column - this is the
 * part MySQL refuses, error 1832/1833, when the column is part of a FOREIGN KEY and the two
 * sides would otherwise go out of charset sync). Both statements go through DB::Execute()
 * (include/database.php), whose raiseErrorFn only logs a failed query - it does not throw - so
 * the try/catch around them in that patch never fires; the CONVERT is silently skipped and
 * execution just moves on to the next table. Worse, because the first ALTER (default charset
 * only) already succeeded, information_schema.TABLES.TABLE_COLLATION reads utf8mb4 for the
 * table from that point on, so that patch's own re-run filter
 * (`WHERE TABLE_COLLATION NOT LIKE 'utf8mb4%'`) can never select this table again - the gap is
 * permanent until something checks actual column-level charsets instead.
 *
 * Found running the original patch against a real production database dump: `session`.`name` is
 * referenced by both `history`.`session_name` (history_ibfk_1) and
 * `session_client`.`session_name` (session_client_ibfk_1). Never surfaced against whatever
 * database that patch was originally exercised on - it takes real historical
 * history/session/session_client rows plus the FK actually being in place to trigger.
 *
 * Fix: find every column still on a non-utf8mb4 charset directly via information_schema.COLUMNS
 * (not TABLE_COLLATION). A table with no FK involvement at all gets a plain CONVERT attempt
 * immediately (covers any other, non-FK straggler). A table that participates in a live FK in
 * either direction - its own FK column, or another table's FK referencing a column here - skips
 * straight to the FK-aware path instead of attempting a CONVERT known in advance to fail:
 * empirically (found running this against a real production dump twice, once fresh and once
 * already-migrated) MySQL refuses to CONVERT a column touched by a FOREIGN KEY unconditionally,
 * even when both sides are still on the same (old) charset and would stay in sync - not only
 * when they'd end up mismatched - so there's no case where attempting it first is worth the
 * doomed query and its log noise. For whatever ends up in the FK-aware path, collect every FK
 * touching those tables in either direction, drop them all, retry the CONVERT for every affected
 * table, then recreate each FK with its original definition - only after all the affected tables
 * have converted, so a constraint is never recreated while its two sides are still on mismatched
 * charsets.
 *
 * Idempotent: columns and FKs are both re-queried from information_schema on every run (no
 * checkpoint state needed, same reasoning as §68) - a constraint already dropped by a prior
 * partial run is simply not found again, and a column already converted just drops out of the
 * candidate list.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if (!DB::is_mysql()) {
    // §68 itself is MySQL-only (no 3-byte/4-byte utf8 split on PostgreSQL); same applies here.
    return;
}

$charset = 'utf8mb4';
$collation = 'utf8mb4_unicode_ci';

function epesi_20260908_unconverted_tables($charset) {
    return DB::GetCol(
        "SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=%s AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> %s
         ORDER BY TABLE_NAME",
        array(DATABASE_NAME, $charset)
    );
}

function epesi_20260908_convert_table($table, $charset, $collation) {
    $t = '`' . $table . '`';
    // Best-effort, same as §68: only matters on pre-10.4 MariaDB / older MySQL row formats.
    DB::Execute('ALTER TABLE ' . $t . ' ROW_FORMAT=DYNAMIC');
    DB::Execute('ALTER TABLE ' . $t . ' CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $collation);
}

function epesi_20260908_has_fk($table) {
    return (bool) DB::GetOne(
        "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
         WHERE CONSTRAINT_SCHEMA=%s AND (TABLE_NAME=%s OR REFERENCED_TABLE_NAME=%s)
           AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1",
        array(DATABASE_NAME, $table, $table)
    );
}

$tables = epesi_20260908_unconverted_tables($charset);

// A table with no FK involvement at all gets tried directly - covers any non-FK straggler.
// A table with FK involvement in either direction skips straight to $remaining below: attempting
// it first is a known-doomed query (see docblock) that only adds log noise.
$remaining = array();
foreach ($tables as $table) {
    Patch::require_time(3);
    if (epesi_20260908_has_fk($table)) {
        $remaining[] = $table;
        continue;
    }
    epesi_20260908_convert_table($table, $charset, $collation);
}

// Re-check from information_schema rather than trusting Execute()'s return value or an
// exception - see docblock: failed queries here don't throw, so this is the only reliable signal.
// Also covers the (unlikely but possible) non-FK straggler that failed for some other reason.
$remaining = array_unique(array_merge($remaining, epesi_20260908_unconverted_tables($charset)));
if (!$remaining) {
    return;
}

// Collect every FK touching a still-blocked table, in either direction, deduplicated by
// constraint name + owning table (a constraint name is only unique per-table in MySQL).
$fks = array();
foreach ($remaining as $table) {
    $rows = DB::GetAll(
        "SELECT k.CONSTRAINT_NAME AS constraint_name, k.TABLE_NAME AS table_name,
                k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_NAME AS referenced_table_name,
                k.REFERENCED_COLUMN_NAME AS referenced_column_name,
                r.UPDATE_RULE AS update_rule, r.DELETE_RULE AS delete_rule
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
          AND r.TABLE_NAME = k.TABLE_NAME
         WHERE k.CONSTRAINT_SCHEMA=%s AND (k.TABLE_NAME=%s OR k.REFERENCED_TABLE_NAME=%s)",
        array(DATABASE_NAME, $table, $table)
    );
    foreach ($rows as $fk) {
        $fks[$fk['table_name'] . '.' . $fk['constraint_name']] = $fk;
    }
}

foreach ($fks as $key => $fk) {
    $exists = DB::GetOne(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s AND CONSTRAINT_TYPE='FOREIGN KEY'",
        array(DATABASE_NAME, $fk['table_name'], $fk['constraint_name'])
    );
    if (!$exists) {
        // Already dropped by an earlier, partial run of this same patch.
        continue;
    }
    DB::Execute('ALTER TABLE `' . $fk['table_name'] . '` DROP FOREIGN KEY `' . $fk['constraint_name'] . '`');
}

foreach ($remaining as $table) {
    Patch::require_time(3);
    epesi_20260908_convert_table($table, $charset, $collation);
}

// Recreate every FK we found, whether or not we ended up dropping it this run - if a prior
// partial run already dropped one and got interrupted before recreating it, this still repairs
// it (ADD CONSTRAINT with an existing name would simply fail harmlessly via the same non-throwing
// DB::Execute() if it somehow already exists).
foreach ($fks as $fk) {
    DB::Execute(
        'ALTER TABLE `' . $fk['table_name'] . '` ADD CONSTRAINT `' . $fk['constraint_name'] . '`'
        . ' FOREIGN KEY (`' . $fk['column_name'] . '`) REFERENCES `' . $fk['referenced_table_name'] . '` (`' . $fk['referenced_column_name'] . '`)'
        . ' ON UPDATE ' . $fk['update_rule'] . ' ON DELETE ' . $fk['delete_rule']
    );
}
