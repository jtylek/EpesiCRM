<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TooltipCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Tooltip'=>array()));
	}
}
?>