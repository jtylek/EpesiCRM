<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/User/Login','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>