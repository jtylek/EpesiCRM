<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage RecordBrowser-RecordPicker
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
		return array(); 
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'<a href="mailto:j@epe.si">Arkadiusz Bisaga</a> (<a href="https://epe.si">Janusz Tylek</a>)',
			'License'=>'TL');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>