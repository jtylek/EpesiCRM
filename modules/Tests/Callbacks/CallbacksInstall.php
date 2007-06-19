<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksInstall extends ModuleInstall {

	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function version() {
		return array("1.0.0");
	}
	
}

?>