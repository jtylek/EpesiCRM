<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-firstrun
 * @subpackage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FirstRunInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('FirstRun');
		Base_ThemeCommon::create_cache();
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('FirstRun');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/Wizard','version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	public static function simple_setup() {
		return false;
	}
}

?>