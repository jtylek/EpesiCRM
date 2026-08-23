<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('utils_commondata_tree', 'position', 'I4');

reset_sub_arrays('/');

function reset_sub_arrays($name) {
	Utils_CommonDataCommon::reset_array_positions($name);
	$arr = Utils_CommonDataCommon::get_array($name, false, false, true);
	
	foreach ($arr as $k=>$v) {
		if (empty($k)) continue;
		reset_sub_arrays($name . '/' .$k);
	}
}


