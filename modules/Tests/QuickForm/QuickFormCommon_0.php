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
		return array($arg=>'Works! Word: '.$arg);
	}

	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'QuickForm page'=>array()));
	}

	public static function autoselect_search($arg=null, $id=null) {
		$arr = array(5=>'Foo 1', 8=>'Test 2', 1=>1, 2=>2, 3=>3);
		if (isset($arr[$arg])) return array($arg=>$arr[$arg]);
		$s = array_search($arg,$arr);
		if ($s) return array($s=>$arr[$s]);
		return $arr;
	}

	public static function automulti_search($arg) {
		return array(5=>'Foo 1', 8=>'Test 2', 1=>1, 2=>2, 3=>3);
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
