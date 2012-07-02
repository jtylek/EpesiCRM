<?php

PatchUtil::db_add_column('user_login','admin','I');

function get_acl_user_id($user_id) {
	$sql = 'SELECT id FROM aro WHERE section_value='. DB::qstr('Users') .' AND value='. DB::qstr($user_id);
	return DB::GetOne($sql);
}

function is_user_in_group($uid,$group) {		
	// $groups_arr = Acl::$gacl->get_object_groups($uid);
	$object_id = $uid;
	$object_type = 'aro';
	$group_table = 'aro_groups';
	$map_table = 'groups_aro_map';
	$query = 'SELECT gm.group_id FROM '.$map_table.' gm ';
	$query .= 'WHERE gm.'. $object_type .'_id='. $object_id;
	$rs = DB::Execute($query);
	$groups_arr = array();
	while ($row = $rs->FetchRow()) {
		$groups_arr[] = $row[0];
	}
	// END
	if(!$groups_arr) return false;
	$groups = array();
	foreach($groups_arr as $id) {
		//$arr = Acl::$gacl->get_group_data($id);
		$group_id = $id;
		$group_type = 'aro';
		$table = 'aro_groups';
		$query  = 'SELECT id, parent_id, value, name, lft, rgt FROM '. $table .' WHERE id='. $group_id;
		$arr = DB::GetRow($query);
		// END
		if($arr[3]==$group) return true;
	}
	return false;
}

$ret = DB::Execute('SELECT * FROM user_login');
while ($row = $ret->FetchRow()) {
	$aid = get_acl_user_id($row['id']);
	$admin = is_user_in_group($aid, 'Administrator');
	$sadmin = is_user_in_group($aid, 'Super administrator');
	
	$aa = 0;
	if ($admin) $aa = 1;
	if ($sadmin) $aa = 2;

	DB::Execute('UPDATE user_login SET admin=%d WHERE id=%d', array($aa, $row['id']));
}

?>
