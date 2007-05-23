<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_FontSizeCommon extends Module {

	public static function tool_menu() {
		if(Base_AclCommon::i_am_user()) return array('Font size'=>array());
		return array();
	}
}
?>
