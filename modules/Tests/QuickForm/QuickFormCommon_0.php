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
	
	public static function automulti_search($arg) {
		return array(5=>'Ble 1', 8=>'Test 2', 1=>1, 2=>2, 3=>3);
	}

	public static function automulti_format($id) {
		if ($id==5) return 'Message';
		if ($id==8) return 'Uh-oh';
		if ($id==1) return 'Uh-oh1';
		if ($id==2) return 'Uh-oh2';
		if ($id==3) return 'Uh-oh3';
	}
}

?>
