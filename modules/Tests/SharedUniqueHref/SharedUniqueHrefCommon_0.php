<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHrefCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Shared Unique Href'=>array()));
	}
	
	public static function cron(){
		print("Cron test");
	}
}

?>
