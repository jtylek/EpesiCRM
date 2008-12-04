<?php
/**
 * Navigation component: back, refresh, forward.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage navigation
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_NavigationInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Base/Navigation');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Navigation');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(array('name'=>'Base/Theme','version'=>0));
	}
}

?>

