<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>