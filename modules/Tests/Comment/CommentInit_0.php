<?php

class Tests_CommentInit_0 extends ModuleInit{
	public static function requires() {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/Comment','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
} 
?>
