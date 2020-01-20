<?php
/**
 * @author Kuba Sławiński
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-tests
 * @subpackage colorpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ColorpickerCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Colorpicker'=>array()));
	}
}

?>