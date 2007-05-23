<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_FCKeditorInit_0 extends ModuleInit {

	public static function requires() {
		return array(array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>