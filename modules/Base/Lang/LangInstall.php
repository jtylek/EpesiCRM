<?php
/**
 * LangInstall class.
 * 
 * This class provides initialization data for Lang module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Lang module.
 * @package tcms-base-extra
 * @subpackage lang
 */
class Base_LangInstall extends ModuleInstall {
	public static function install() {
		return Variable::set('default_lang','en');
	}
	
	public static function uninstall() {
		return Variable::delete('default_lang');
	}
}
?>
