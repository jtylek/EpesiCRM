<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_FuncInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function provides($v) {
		return array();
	}
	public function requires($v) {
		return array();
	}
}

?>
