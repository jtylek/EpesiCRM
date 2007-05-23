<?php
/**
 * Lang_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage lang-administrator
 */

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public static function version() {
		return 0;
	}
	
	public static function install() {
		return Variable::set('allow_lang_change','1');
	}
	
	public static function uninstall() {
		return Variable::delete('allow_lang_change');
	}
}

?>
