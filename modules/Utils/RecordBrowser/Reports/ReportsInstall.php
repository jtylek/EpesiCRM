<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Reports
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_ReportsInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
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
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Libs/TCPDF','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/RecordBrowser','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Arkadiusz Bisaga <abisaga@telaxus.com>',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
