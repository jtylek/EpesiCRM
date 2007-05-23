<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_PeopleInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Utils/SQLTableBrowser','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>