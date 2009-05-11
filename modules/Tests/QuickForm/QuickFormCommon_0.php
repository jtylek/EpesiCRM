<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_QuickFormCommon extends ModuleCommon {
	public static function autocomplete($arg) {
		return '<ul><li>Works! Word: '.$arg.'</ul></li>';
	}
	
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'QuickForm page'=>array()));
	}
}

?>
