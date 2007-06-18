<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHrefInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Tests_SharedUniqueHref_a','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
