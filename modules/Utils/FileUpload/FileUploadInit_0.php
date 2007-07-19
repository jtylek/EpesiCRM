<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileUploadInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>