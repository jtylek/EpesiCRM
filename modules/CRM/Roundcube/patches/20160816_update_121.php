<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');
if(DB::is_mysql())
    $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.update6.sql');
else
    $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.update5.sql');
foreach(explode(';',$f) as $q) {
    $q = trim($q);
    if(!$q) continue;
    @DB::Execute($q);
}

DB::Execute('UPDATE rc_system SET value=%s WHERE name=%s', array('2015111100', 'roundcube-version'));