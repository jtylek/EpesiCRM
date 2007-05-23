<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommentInit_0 extends ModuleInit{
	public static function requires() {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
} 
?>
