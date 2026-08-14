<?php

/**
 * §68 — migrate MySQL database/tables/columns from legacy 3-byte utf8 to utf8mb4.
 *
 * Root cause: include/database.php's DB::Connect() sent `SET NAMES "utf8"` on every connection,
 * and DB::CreateTable()'s MySQL default was `DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci`
 * (both now fixed to utf8mb4, see database.php / setup.php). MySQL's `utf8` is capped at 3 bytes
 * per character, so any 4-byte UTF-8 character (emoji, some CJK/historic scripts) sent over that
 * connection into a utf8 column is rejected or silently mangled — this is what showed up as
 * "note not saving" when it contained an emoji.
 *
 * Fixing the connection charset and table-creation default only stops the bleeding for *new*
 * connections/tables. Every table created before that fix — which on an existing install is most
 * of the schema — is still physically stored as utf8/utf8_unicode_ci and will keep rejecting
 * 4-byte characters until converted. This patch does that conversion.
 *
 * Lives in Base (not the owning module of any one table) because Base is the only module
 * guaranteed to be installed, so its patches/ dir is always scanned (PatchUtil::list_patches()
 * only scans patches/ for currently-installed modules — see include/patches.php). This patch
 * walks every table in the connected database via information_schema, not just Base's own
 * tables, precisely so it isn't limited to one module's schema.
 *
 * Idempotent: the table list is re-queried from information_schema.TABLES each run, filtered to
 * TABLE_COLLATION NOT LIKE 'utf8mb4%' — a table already converted (by this run or a prior partial
 * run) simply drops out of the list. No cursor/checkpoint state is needed for correctness, only
 * Patch::require_time() between tables so a slow/large install can spread the work across
 * multiple runpatches.php/cron invocations instead of timing out mid-run.
 *
 * ROW_FORMAT=DYNAMIC is force-set per table before CONVERT: with the older COMPACT/REDUNDANT
 * InnoDB row format, an indexed VARCHAR(255) column converted to utf8mb4 (255*4=1020 bytes) can
 * exceed the 767-byte index-prefix limit ("Specified key was too long") — DYNAMIC raises that to
 * 3072 bytes. (Verified moot on this dev DB — MariaDB 10.4 already defaults every InnoDB table to
 * ROW_FORMAT=Dynamic — but older MySQL installs this patch also has to serve may not.)
 *
 * A failure on one table (locked, in use, storage-engine quirk) is logged via error_log() and
 * skipped rather than aborting the whole migration — error_log() never re-throws, whereas
 * trigger_error() would be converted to a PatchException by the patch runner's error handler and
 * abort every table after it. Re-running the patch (next cron tick) retries whatever is left.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if (!DB::is_mysql()) {
    // PostgreSQL has no equivalent 3-byte/4-byte UTF-8 split at the column level.
    return;
}

$charset = 'utf8mb4';
$collation = 'utf8mb4_unicode_ci';

$alterDb = Patch::checkpoint('alter_database_charset');
if (!$alterDb->is_done()) {
    try {
        DB::Execute('ALTER DATABASE `' . DATABASE_NAME . '` CHARACTER SET ' . $charset . ' COLLATE ' . $collation);
    } catch (Exception $e) {
        error_log('EPESI §68 utf8mb4 migration: ALTER DATABASE failed: ' . $e->getMessage());
    }
    $alterDb->done();
}

$tables = DB::GetCol(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_COLLATION NOT LIKE 'utf8mb4%%' ORDER BY TABLE_NAME",
    array(DATABASE_NAME)
);

foreach ($tables as $table) {
    Patch::require_time(3);

    $t = '`' . $table . '`';
    try {
        try {
            DB::Execute('ALTER TABLE ' . $t . ' ROW_FORMAT=DYNAMIC');
        } catch (Exception $e) {
            // Best-effort only (e.g. non-InnoDB engine) - the CONVERT below is what matters.
        }
        DB::Execute('ALTER TABLE ' . $t . ' CHARACTER SET ' . $charset . ' COLLATE ' . $collation);
        DB::Execute('ALTER TABLE ' . $t . ' CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $collation);
    } catch (Exception $e) {
        error_log('EPESI §68 utf8mb4 migration: table `' . $table . '` skipped: ' . $e->getMessage());
    }
}
