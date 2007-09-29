<?php
/**
 * CRMHRInstall class.
 * 
 * This class provides initialization data for CRMHR module.
 * 
 * @author Kuba SĹawiĹski <ruud@o2.pl>, Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-extra
 */
defined("_VALID_ACCESS") || die();

/**
 * This class provides initialization data for Test module.
 * @package tcms-extra
 * @subpackage test
 */
class Base_RecordBrowserInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Base/RecordBrowser');
		DB::CreateTable('recordbrowser_quickjump',
						'tab C(64) KEY,'.
						'col C(64)',
						array('constraints'=>''));
		DB::CreateTable('recordbrowser_tpl',
						'tab C(64) KEY,'.
						'filename C(256)',
						array('constraints'=>''));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('recordbrowser_quickjump');
		DB::DropTable('recordbrowser_tpl');
		Base_ThemeCommon::uninstall_default_theme('Base/RecordBrowser');
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/Tooltip', 'version'=>0), 
			array('name'=>'Utils/BookmarkBrowser', 'version'=>0), 
			array('name'=>'Utils/GenericBrowser', 'version'=>0), 
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Base/User/Login', 'version'=>0), 
			array('name'=>'Base/User', 'version'=>0)
		);
	}
	
	public function provides($v) {
		return array();
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Sławiński</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module to browse and modify records.');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('0.9');
	}	
}

?>
