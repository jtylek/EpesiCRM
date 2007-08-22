<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserInstall extends ModuleInstall {

	public static function install() {
		$ret = true;
		$ret &= DB::CreateTable('generic_browser',"name C(40) NOTNULL, column_id I NOTNULL, column_pos I NOTNULL, display I1 DEFAULT 1", array('constraints' => ', PRIMARY KEY (name,column_id)'));
		if(!$ret){
			print('Unable to create table generic_browser.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Utils/GenericBrowser');
		return $ret;
	}
	
	public static function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('generic_browser');
		Base_ThemeCommon::uninstall_default_theme('Utils/GenericBrowser');
		return true;
	}

	public static function version() {
		return array('0.9.9');
	}	
	public static function requires($v) {
		return array(
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0), 
			array('name'=>'Base/MaintenanceMode','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}
}

?>