<?php
/**
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license SPL
 * @version 0.1
 * @package utils-recordbrowser-reports
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
		return array("0.1");
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
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
