<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage activityreport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActivityReportInstall extends ModuleInstall {

	public function install() {
		Base_AclCommon::add_permission(_M('View Activity Report'),array('ACCESS:employee','ACCESS:manager'));
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('View Activity Report');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'User Activity Report');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_Theme::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickForm::module_name(),'version'=>0),
			array('name'=>Utils_RecordBrowser::module_name(),'version'=>0));
	}	
}

?>