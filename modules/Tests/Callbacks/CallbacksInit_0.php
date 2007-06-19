<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksInit_0 extends ModuleInit {

	public static function requires() {
		return array(array('name'=>'Utils/CatFile','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>