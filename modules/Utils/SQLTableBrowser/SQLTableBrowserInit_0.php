<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_SQLTableBrowserInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>