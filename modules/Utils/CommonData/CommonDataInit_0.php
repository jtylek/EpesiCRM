<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/Wizard','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>