<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
    if(DB::is_mysql())
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.update.sql');
    else
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.update.sql');
    foreach(explode(';',$f) as $q) {
        $q = trim($q);
        if(!$q) continue;
        @DB::Execute($q);
    }
?>
