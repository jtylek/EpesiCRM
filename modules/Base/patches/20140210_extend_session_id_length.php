<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
$mysql = DB::is_mysql();

if ($mysql) {
    // remove foregin keys
    foreach (array('history', 'session_client') as $tab) {
        $a = DB::GetRow("SHOW CREATE TABLE $tab");
        if (preg_match('/CONSTRAINT (.+) FOREIGN KEY .*session_name/', $a[1], $m)) {
            DB::Execute("ALTER TABLE $tab DROP FOREIGN KEY " . $m[1]);
        }
    }
}

PatchUtil::db_alter_column('session', 'name', 'C(128) NOTNULL');
PatchUtil::db_alter_column('session_client', 'session_name', 'C(128) NOTNULL');
PatchUtil::db_alter_column('history', 'session_name', 'C(128) NOTNULL');

if ($mysql) {
    DB::Execute('ALTER TABLE history ADD FOREIGN KEY (session_name) REFERENCES session(name)');
    DB::Execute('ALTER TABLE session_client ADD FOREIGN KEY (session_name) REFERENCES session(name)');
}
