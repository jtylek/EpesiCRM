<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 * @subpackage ScriptAculoUs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_ScriptAculoUsInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.7.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
