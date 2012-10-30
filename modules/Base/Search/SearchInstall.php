<?php
/**
 * SearchInstall class.
 * 
 * This class provides initialization data for Search module.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SearchInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Base/Search');
		Base_AclCommon::add_permission(_M('Search'),array('ACCESS:employee'));
		return true;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('Search');
		Base_ThemeCommon::uninstall_default_theme('Base/Search');
		return true;
	}
	
	public function version() {
		return array('0.9.1');
	}
	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Box','version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
