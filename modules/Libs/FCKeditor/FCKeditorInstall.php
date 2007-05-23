<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_FCKeditorInstall extends ModuleInstall {

	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function version() {
		return array('2.4.2');
	}
	
}

?>