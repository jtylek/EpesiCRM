<?php

PatchUtil::db_add_column('user_login','admin','I');

$ret = DB::Execute('SELECT * FROM user_login');
while ($row = $ret->FetchRow()) {
	$aid = Base_AclCommon::get_acl_user_id($row['id']);
	$admin = Base_AclCommon::is_user_in_group($aid, 'Administrator');
	$sadmin = Base_AclCommon::is_user_in_group($aid, 'Super administrator');
	
	$aa = 0;
	if ($admin) $aa = 1;
	if ($sadmin) $aa = 2;

	DB::Execute('UPDATE user_login SET admin=%d WHERE id=%d', array($aa, $row['id']));
}

?>
