<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
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