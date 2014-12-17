<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
    if(DB::is_mysql())
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.update3.sql');
    else
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.update3.sql');
    foreach(explode(';',$f) as $q) {
        $q = trim($q);
        if(!$q) continue;
        @DB::Execute($q);
    }
?>
