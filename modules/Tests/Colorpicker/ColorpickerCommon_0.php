<?php
/**
 * 
 * @author Kuba Sławiński
 * @copyright Kuba Sławiński
 * @license EPL
 * @version 0.1
 * @package tests-colorpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ColorpickerCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Colorpicker'=>array()));
	}
}

?>