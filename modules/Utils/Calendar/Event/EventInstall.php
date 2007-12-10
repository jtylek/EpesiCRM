<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array();
	}
	
	public function version() {
		return array('1.0.0');
	}
}

?>
?>
