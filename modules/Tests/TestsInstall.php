<?php
/**
 * TestsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-tests
 * @subpackage tests-installer
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class TestsInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a>, <a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'SPL', 'Description'=>'Module examples pack');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public static function version() {
		return array('0.9.9');
	}
}

?>
