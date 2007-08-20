<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage ModuleManager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleManagerInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function requires($v) {
		return array(
		array('name'=>'Libs/QuickForm','version'=>0));
	}
}

?>
