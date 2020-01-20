<?php
/**
 * About Epesi
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutCommon extends ModuleCommon {
	public static function menu() {
		return array(_M('Support')=>array('__submenu__'=>1,'__weight__'=>1000,_M('About')=>array('__weight__'=>100,'__function__'=>'info')));
	}	
}

?>
