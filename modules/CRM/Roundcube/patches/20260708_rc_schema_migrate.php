<?php
/**
 * §58 — bridge the Roundcube DB schema on UPGRADE.
 *
 * Epesi's Roundcube schema-migration patches stop at ~2016 (20160816_update_121 → rc_system
 * roundcube-version '2015111100'), but the §30 RC 1.2.1→1.7.1 upgrade bundled RC whose schema is
 * '2025092300' (the 35 files in RC/SQL/mysql/). No patch bridged the gap, so on a real 7.4→8.2
 * upgrade the rc_* schema stayed old and the new RC code queried columns that don't exist
 * (e.g. rc_session.expires_at) → mail broken. This patch applies the stock RC migration files
 * (which are UNPREFIXED) with Epesi's 'rc_' table prefix added, from the DB's current schema
 * version up to '2025092300', then records the new version.
 *
 * FRESH installs are unaffected: §54's mysql.initial.sql already creates the 2025092300 schema,
 * so rc_system reads '2025092300' here and the patch is a no-op. Idempotent (guarded by the
 * stored version). Exact-backtick prefixing is safe: verified across all 35 files that no
 * column / index / constraint name equals a bare RC table name.
 *
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Only relevant when Roundcube is installed on this instance.
if (!in_array('rc_system', DB::MetaTables())) {
    return;
}

$target  = '2025092300';
$current = DB::GetOne("SELECT value FROM rc_system WHERE name='roundcube-version'");
if (!$current) {
    // Unknown → floor to the last Epesi-patched era so we replay everything the old
    // (silently-@-swallowed) patches never actually applied on the rc_-prefixed tables.
    $current = '2015030800';
}

// Already at (or past) the bundled schema — e.g. a fresh §54 install. Nothing to do.
if ($current >= $target) {
    return;
}

// The 18 Roundcube-owned tables (same set as RoundcubeInstall::drop_all_rc_tables() and
// mysql.initial.sql). Epesi stores them with the 'rc_' prefix; the stock migration files don't.
$rc_tables = array(
    'session', 'users', 'cache', 'cache_shared', 'cache_index', 'cache_thread', 'cache_messages',
    'collected_addresses', 'contacts', 'contactgroups', 'contactgroupmembers', 'identities',
    'responses', 'dictionary', 'searches', 'filestore', 'uploads', 'system',
);

$files = glob('modules/CRM/Roundcube/RC/SQL/mysql/*.sql');
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
    // (`session_id`, `cache_index`, `sess_id`) since `_` is a word char — verified across all 35
    // files that the only non-backtick occurrence of any of these names is that one table ref.
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
            // Surface real failures (unlike the old @-swallow), but keep going so an already-
            // applied statement on a partially-migrated schema doesn't abort the whole run.
            trigger_error('§58 RC schema migrate [' . $ver . ']: ' . $e->getMessage(), E_USER_WARNING);
        }
    }
}

DB::Execute("UPDATE rc_system SET value=%s WHERE name=%s", array($target, 'roundcube-version'));
