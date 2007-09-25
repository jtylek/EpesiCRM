<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @package epesi-base-extra
 * @subpackage actionbar
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('Base/ActionBar');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/ActionBar');
		return true;
	}
	
	public function version() {
		return array("0.9.9");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
}
?>