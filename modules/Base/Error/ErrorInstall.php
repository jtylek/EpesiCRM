<?php
/**
 * Provides error to mail handling.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage error
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ErrorInstall extends ModuleInstall {
	public static function install() {
		file_put_contents(ModuleManager::get_data_dir('Base/Error').'index.html','');
		Variable::set('error_mail','');
		return true;
	}
	
	public static function uninstall() {
		Variable::delete('error_mail');
		return true;
	}
	
	public static function version() {
		return array('1.0.0');
	}

	public static function requires($v) {
		return array(
			array('name'=>'Base/Mail', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Libs/QuickForm', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0));
	}
	
}	

?>
