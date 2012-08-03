<?php
/**
 * MenuInstall class.
 * 
 * This class provides initialization data for Menu module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MenuInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Base/Menu');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Menu');
		return true;
	}
	
	public function version () {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
//			array('name'=>'Base/Menu/QuickAccess','version'=>0),  
			array('name'=>'Base/Lang','version'=>0), 
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/Menu','version'=>0)
		);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
