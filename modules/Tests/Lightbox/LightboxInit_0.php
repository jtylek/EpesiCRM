<?php

class Tests_LightboxInit_0 extends ModuleInit{
	public static function requires() {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0),
			array('name'=>'Libs/Lightbox','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
} 
?>
