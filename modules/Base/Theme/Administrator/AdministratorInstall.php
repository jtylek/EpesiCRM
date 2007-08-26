<?php
/**
 * Theme_AdministratorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}

	public static function version() {
		return array("1.0");
	}
	public static function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/FileUpload','version'=>0),
			array('name'=>'Utils/FileDownload','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/StatusBar','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
		
	}
}

?>
