<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_GenericBrowserCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Generic Browser'=>array()));
	}
}

?>
