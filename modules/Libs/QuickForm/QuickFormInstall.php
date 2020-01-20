<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_QuickFormInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Libs_QuickFormInstall::module_name());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Libs_QuickFormInstall::module_name());
		return true;
	}

	public function version() {
		return array('3.2.10');
	}
	public function requires($v) {
		return array(
		array('name'=>Base_LangInstall::module_name(),'version'=>0),
		array('name'=>Base_ThemeInstall::module_name(),'version'=>0));
	}
    public static function simple_setup() {
        return false;
    }
}

?>
