<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BookmarkBrowserInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Utils/BookmarkBrowser','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>