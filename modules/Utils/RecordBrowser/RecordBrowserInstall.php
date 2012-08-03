<?php
/**
 * RecordBrowser install class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Utils/RecordBrowser');
		DB::CreateTable('recordbrowser_table_properties',
						'tab C(64) KEY,'.
						'quickjump C(64) DEFAULT \'\','.
						'tpl C(255) DEFAULT \'\','.
						'favorites I1 DEFAULT 0,'.
						'recent I2 DEFAULT 0,'.
						'full_history I1 DEFAULT 1,'.
						'caption C(32) DEFAULT \'\','.
						'icon C(255) DEFAULT \'\','.
						'access_callback C(128) DEFAULT \'\','.
						'description_callback C(128) DEFAULT \'\'',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_datatype',
						'type C(32) KEY,'.
						'module C(64),'.
						'func C(128)',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_addon',
					'tab C(64),'.
					'module C(128),'.
					'func C(128),'.
					'pos I,'.
					'enabled I1,'.
					'label C(128)',
					array('constraints'=>', PRIMARY KEY(tab, module, func)'));
		DB::CreateTable('recordbrowser_browse_mode_definitions',
					'tab C(64),'.
					'module C(128),'.
					'func C(128)',
					array('constraints'=>', PRIMARY KEY(tab, module, func)'));
		DB::CreateTable('recordbrowser_processing_methods',
					'tab C(64),'.
					'func C(255)',
					array('constraints'=>', PRIMARY KEY(tab, func)'));
        DB::CreateTable('recordbrowser_clipboard_pattern', 'tab C(64) KEY, pattern X, enabled I4');
		return true;
	}
	
	public function uninstall() {
        DB::DropTable('recordbrowser_clipboard_pattern');
		DB::DropTable('recordbrowser_browse_mode_definitions');
		DB::DropTable('recordbrowser_addon');
		DB::DropTable('recordbrowser_table_properties');
		DB::DropTable('recordbrowser_datatype');
		Base_ThemeCommon::uninstall_default_theme('Utils/RecordBrowser');
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/CommonData', 'version'=>0), 
			array('name'=>'Utils/CurrencyField', 'version'=>0), 
			array('name'=>'Utils/Shortcut', 'version'=>0), 
			array('name'=>'Utils/BBCode', 'version'=>0), 
			array('name'=>'Utils/Tooltip', 'version'=>0), 
			array('name'=>'Utils/RecordBrowser/RecordPickerFS', 'version'=>0),
			array('name'=>'Utils/RecordBrowser/RecordPicker', 'version'=>0),
			array('name'=>'Utils/GenericBrowser', 'version'=>0), 
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Utils/Watchdog', 'version'=>0), 
			array('name'=>'Base/User/Login', 'version'=>0), 
			array('name'=>'Base/User', 'version'=>0)
		);
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module to browse and modify records.');
	}
	
	public function simple_setup() {
		return __('EPESI Core');
	}
	
	public function version() {
		return array('2.0');
	}
	
}

?>
