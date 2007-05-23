<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_MenuCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Menu'=>array()));
	}
}
?>