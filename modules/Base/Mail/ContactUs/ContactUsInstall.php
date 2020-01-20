<?php
/**
 * Mail_ContactUsInstall class.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(self::module_name());
	    return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(self::module_name());
	    return true;
	}

	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_MenuInstall::module_name(),'version'=>0),
		);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function version() {
		return array('1.0');
	}
}

?>
