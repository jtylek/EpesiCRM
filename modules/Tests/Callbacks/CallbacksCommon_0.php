<?php
/**
 * Example event module
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-tests
 * @subpackage callbacks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Callbacks page'=>array()));
	}
}

?>