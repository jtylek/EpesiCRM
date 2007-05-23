<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-libs
 * @subpackage ScriptAculoUs
 */
class Libs_ScriptAculoUsInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function version() {
		return array('1.7.0');
	}
}

?>
