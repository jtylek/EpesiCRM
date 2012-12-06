<?php
    if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli')
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.update.sql');
    else
        $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.update.sql');
    foreach(explode(';',$f) as $q) {
        $q = trim($q);
        if(!$q) continue;
        @DB::Execute($q);
    }
?>
