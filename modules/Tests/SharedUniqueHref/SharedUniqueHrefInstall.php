<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage shared-unique-href
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHrefInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	public static function requires_0() {
		return array(array('name'=>'Tests_SharedUniqueHref_a','version'=>0));
	}
}

?>
