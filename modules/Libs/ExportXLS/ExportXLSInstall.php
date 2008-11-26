<?php
/**
 * FPDFInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_ExportXLSInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}

	public function version() {
		return array('0.9');
	}
	public function requires($v) {
		return array();
	}
}

?>
