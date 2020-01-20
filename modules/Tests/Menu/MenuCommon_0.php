<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_MenuCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Menu'=>array()));
	}

	public static function quick_menu() {
		return array('Quick menu test'=>array('action'=>'ble'));
	}
}
?>