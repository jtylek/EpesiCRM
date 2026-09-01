<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TooltipCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Tooltip'=>array()));
	}
}
?>