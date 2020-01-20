<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Reports
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_ReportsInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/RecordBrowser/Reports');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/RecordBrowser/Reports');
		return true;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Libs_TCPDFInstall::module_name(),'version'=>0),
			array('name'=>Utils_TabbedBrowserInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Utils_RecordBrowserInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
			array('name'=>Libs_OpenFlashChartInstall::module_name(),'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Arkadiusz Bisaga, Janusz Tylek',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
