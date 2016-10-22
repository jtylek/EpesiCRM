<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage leightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_LeightboxInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme(Libs_LeightboxInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Libs_LeightboxInstall::module_name());
		return true;
	}
	public function version() {
		return array("2.03.3");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0));
	}
    public static function simple_setup() {
        return false;
    }
}

?>