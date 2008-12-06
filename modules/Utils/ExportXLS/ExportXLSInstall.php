<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage exportxls
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ExportXLSInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}

	public function version() {
		return array('1.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
