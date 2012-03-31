<?php

if (ModuleManager::is_installed('CRM_Roundcube') >= 0) {
    foreach(DB::GetCol('SELECT id FROM user_login') as $id) {
	$identity = DB::GetOne("SELECT ".DB::Concat('f_first_name',DB::qstr(' '),'f_last_name')." FROM contact_data_1 WHERE f_login =%d",array($id));
	foreach(DB::GetCol("SELECT f_email FROM rc_accounts_data_1 WHERE f_epesi_user=%d", array($id)) as $f_email)
	    DB::Execute('UPDATE rc_identities SET name=%s WHERE email=%s',array($identity,$f_email));
	    
	    
    }
}
?>
