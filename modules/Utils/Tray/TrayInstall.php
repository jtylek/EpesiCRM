<?php
/*
* @author Georgi Hristov <ghristov@gmx.de>
* @copyright Copyright &copy; 2019, Georgi Hristov
* @license MIT
* @version 2.0
* @package epesi-tray
*
*/
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TrayInstall extends ModuleInstall {
	const version = '2.0';

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());		
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array(self::version);
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0)
		);
	}

	public static function info() {
		return array(
				'Description' => "Displays overview pending items in recordsets",
				'Author' => '<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
				'License' => 'MIT'
		);
	}
	
	public function simple_setup() {
		return false;
	}
	
}

?>