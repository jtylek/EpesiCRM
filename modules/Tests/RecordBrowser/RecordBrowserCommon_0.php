<?php
/**
 * @author  Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2013, Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage record-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Tests_RecordBrowserCommon extends ModuleCommon {
	public static function display_calculated($r,$nolink = false){
		return '!';
	}
	
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'RecordBrowser'=>array()));
	}
}

?>
