<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('Base/ActionBar');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/ActionBar');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0),
			array('name'=>'Base/Menu/QuickAccess','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
}
?>