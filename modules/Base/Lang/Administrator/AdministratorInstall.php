<?php
/**
 * Lang_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage lang-administrator
 */

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public static function version() {
		return array('1.0.0');
	}
	
	public static function install() {
		return Variable::set('allow_lang_change',true);
	}
	
	public static function uninstall() {
		return Variable::delete('allow_lang_change');
	}
}

?>
