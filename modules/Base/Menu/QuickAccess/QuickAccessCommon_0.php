<?php
/**
 * QuickAccess class.
 * 
 * This class provides functionality for QuickAccess class. 
 * 
 * @author Arkadiusz Bisaga <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessCommon {
	public static function user_settings() {
		if(Base_AclCommon::i_am_user()) return array('Quick access'=>'callbody');
		return array();
	} 

	public static function quick_access_menu() {
		if (!Base_AclCommon::i_am_user()) return array();
		$ret = DB::Execute('SELECT * FROM quick_access WHERE user_login_id = %d ORDER BY label',Base_UserCommon::get_my_user_id());
		$qa_menu = array('__submenu__'=>1);
		while ($row = $ret->FetchRow()){
			$menu_entry = null;
			parse_str($row['link'],$menu_entry);
			$qa_menu[$row['label']] = $menu_entry; 
		} 
		if ($qa_menu == array('__submenu__'=>1)) return array();
		return array('Quick Access'=>$qa_menu);
	}
}

?>
