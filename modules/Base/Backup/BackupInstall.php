<?php
/**
 * This class provides initialization data for Backup module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage backup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BackupInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Base/Backup');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Backup');
		return true;
	}
	
	public static function version() {
		return array('1.0.0');
	}

	public static function requires_0() {
		return array(
				array('name'=>'Libs/QuickForm','version'=>0), 
				array('name'=>'Base/Lang', 'version'=>0),
				array('name'=>'Base/Admin', 'version'=>0),
				array('name'=>'Base/Acl', 'version'=>0),
				array('name'=>'Utils/GenericBrowser', 'version'=>0));
	}
	
}
?>
