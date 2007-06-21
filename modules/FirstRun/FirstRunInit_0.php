<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FirstRunInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Utils/Wizard','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>