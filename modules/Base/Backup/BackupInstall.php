<?php
/**
 * This class provides initialization data for Backup module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage backup
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BackupInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('Base/Backup');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Backup');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array(
				array('name'=>'Libs/QuickForm','version'=>0), 
				array('name'=>'Base/Lang', 'version'=>0),
				array('name'=>'Base/Admin', 'version'=>0),
				array('name'=>'Base/Acl', 'version'=>0),
				array('name'=>'Utils/GenericBrowser', 'version'=>0));
	}
	
}
?>
