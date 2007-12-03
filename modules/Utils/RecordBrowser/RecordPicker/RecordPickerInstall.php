<?php
/**
 * 
 * @author admin@admin.com
 * @copyright admin@admin.com
 * @license SPL
 * @version 0.1
 * @package utils-recordbrowser--recordpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_RecordPickerInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0)
		); 
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)',
			'License'=>'TL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>