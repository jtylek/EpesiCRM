<?php
/**
 * SearchInstall class.
 * 
 * This class provides initialization data for Search module.
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SearchInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme(Base_SearchInstall::module_name());
		Base_AclCommon::add_permission(_M('Search'),array('ACCESS:employee'));
		return true;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('Search');
		Base_ThemeCommon::uninstall_default_theme(Base_SearchInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('0.9.1');
	}
	public function requires($v) {
		return array(
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_BoxInstall::module_name(),'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
