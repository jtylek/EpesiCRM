<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('SET FOREIGN_KEY_CHECKS=0');

$collation = 'utf8_unicode_ci';
$database = Patch::checkpoint('database');
if (!$database->is_done()) {
    DB::Execute('ALTER DATABASE `' . DATABASE_NAME . '` CHARACTER SET utf8 COLLATE ' . $collation);
    $database->done();
}

$tables = Patch::checkpoint('tables');
if (!$tables->is_done()) {
    if (DB::is_mysql()) {
        $tabs = $tables->get('tabs', null);
        if ($tabs === null) {
            $tabs = array();
            foreach (DB::MetaTables() as $t) {
                $tabs[$t] = false;
            }
            $tables->set('tabs', $tabs);
        }
        foreach ($tabs as $t => $done) {
            if ($done) continue;
            Patch::require_time('5');
            DB::Execute('ALTER TABLE `' . $t . '` CHARACTER SET utf8 COLLATE ' . $collation);
            DB::Execute('ALTER TABLE `' . $t . '` CONVERT TO CHARACTER SET utf8 COLLATE ' . $collation);
            $tabs[$t] = true;
            $tables->set('tabs', $tabs);
        }
    }
    $tables->done();
}

DB::Execute('SET FOREIGN_KEY_CHECKS=1');
