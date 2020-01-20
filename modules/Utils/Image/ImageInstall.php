<?php
/**
 * Utils_ImageInstall class.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-utils
 * @subpackage image
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ImageInstall extends ModuleInstall {
	public function install() {
		$this->create_data_dir();
		Base_ThemeCommon::install_default_theme(Utils_ImageInstall::module_name());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(Utils_ImageInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('0.8.9');
	}
	public function requires($v) {
		if(!function_exists('imagecreatefromjpeg')) return array(array('name'=>'php5-gd','version'=>0));
		return array();
	}
}

?>
