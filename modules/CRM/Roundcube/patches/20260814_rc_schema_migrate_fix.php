<?php
/**
 * §69 — fix §58's RC schema-migration patch: wrong glob path silently no-opped it.
 *
 * modules/CRM/Roundcube/patches/20260708_rc_schema_migrate.php globbed
 * 'modules/CRM/Roundcube/RC/SQL/mysql/*.sql', but that directory has never existed — the
 * Roundcube vendor tree (and its SQL migration files) actually lives at
 * 'modules/Libs/RoundCube/RC/SQL/', same as CRM_RoundcubeInstall::install() already reads for
 * a fresh install. glob() on the nonexistent path returned an empty array, so the migration
 * loop ran zero files on every instance that applied that patch — but the patch still
 * unconditionally bumped rc_system.roundcube-version to the target '2025092300' at the end, so
 * it silently recorded success without doing anything. rc_session (and the other rc_* tables)
 * stayed on the pre-2025 schema, and Mail kept throwing "Unknown column 'expires_at' in 'field
 * list'" (§58's original symptom) even after the "fix" shipped and ran (confirmed SUCCESS in
 * patches.log on this dev DB, yet rc_session.expires_at was still missing).
 *
 * Because patches are identified by filepath, editing 20260708_rc_schema_migrate.php would be a
 * silent no-op on any instance that already ran it — this ships as a new patch instead. It also
 * can't trust the stored roundcube-version marker (the broken patch already bumped it to target
 * everywhere it ran), so it re-derives "did the schema actually reach target" from the concrete
 * symptom column instead of the marker.
 *
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Only relevant when Roundcube is installed on this instance.
if (!in_array('rc_system', DB::MetaTables())) {
    return;
}

$target = '2025092300';

// Ground truth, not the (possibly falsely-bumped) rc_system marker: 2025092300 is the migration
// that adds rc_session.expires_at, so its presence means the schema genuinely reached target.
$session_cols = DB::MetaColumns('rc_session');
if ($session_cols && isset($session_cols['EXPIRES_AT'])) {
    return;
}

$current = DB::GetOne("SELECT value FROM rc_system WHERE name='roundcube-version'");
if (!$current || $current >= $target) {
    // Unknown, or bumped to target by the broken §58 patch without actually migrating anything
    // — floor to the last era Epesi's old (pre-§58) patches targeted, same floor §58 used.
    $current = '2015030800';
}

// Same 18 Roundcube-owned tables §58 used (matches RoundcubeInstall::drop_all_rc_tables() /
// mysql.initial.sql). Epesi stores them with the 'rc_' prefix; the stock migration files don't.
$rc_tables = array(
    'session', 'users', 'cache', 'cache_shared', 'cache_index', 'cache_thread', 'cache_messages',
    'collected_addresses', 'contacts', 'contactgroups', 'contactgroupmembers', 'identities',
    'responses', 'dictionary', 'searches', 'filestore', 'uploads', 'system',
);

// Corrected path (Libs/RoundCube, not CRM/Roundcube) + pick the driver-matching subdirectory —
// §58 hardcoded 'mysql' regardless of driver, which would also have broken Postgres instances.
$sql_dir = DB::is_mysql() ? 'mysql' : 'postgres';
$files = glob('modules/Libs/RoundCube/RC/SQL/' . $sql_dir . '/*.sql');
sort($files); // ascending by the numeric YYYYMMDDNN basename

foreach ($files as $file) {
    $ver = basename($file, '.sql');
    if (!ctype_digit($ver) || $ver <= $current || $ver > $target) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        continue;
    }

    // Add the rc_ prefix to every Roundcube table reference. A word-boundary regex handles BOTH
    // `table` (backticks are non-word chars → boundaries) AND the one unquoted reference in the
    // set (`UPDATE session …` in 2025092300.sql). \b never matches inside a longer identifier
    // (`session_id`, `cache_index`, `sess_id`) since `_` is a word char.
    foreach ($rc_tables as $t) {
        $sql = preg_replace('/\b' . preg_quote($t, '/') . '\b/', 'rc_' . $t, $sql);
    }

    foreach (explode(';', $sql) as $q) {
        $q = trim($q);
        if ($q === '') {
            continue;
        }
        try {
            DB::Execute($q);
        } catch (Exception $e) {
            // Log and keep going so an already-applied statement on a partially-migrated schema
            // doesn't abort the whole run. Use error_log (never re-throws) rather than
            // trigger_error — Epesi converts warnings to ErrorException during patch runs, which
            // PatchUtil::apply_new() would catch and abort on.
            error_log('EPESI §69 RC schema migrate [' . $ver . '] skipped a statement: ' . $e->getMessage());
        }
    }
}

DB::Execute("UPDATE rc_system SET value=%s WHERE name=%s", array($target, 'roundcube-version'));
