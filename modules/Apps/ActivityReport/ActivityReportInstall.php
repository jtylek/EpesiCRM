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
		$this->add_aco('access','Employee');
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'User Activity Report');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'CRM/Acl','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/RecordBrowser','version'=>0));
	}	
}

?>