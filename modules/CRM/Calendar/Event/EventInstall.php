<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {
	public function install() {
		DB::CreateTable('calendar_event',
			'module_name C(100) KEY,' .
			'title C(50) NOT NULL,' .
			'description C(250)'
		);
		
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('calendar_event');
		return true;
	}
	public function requires($v) {
		return array();
	}
}

?>
