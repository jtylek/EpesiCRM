<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_PeopleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'SQLTableBrowser'=>array('__submenu__'=>1,'People'=>array())));	
	}
}

?>