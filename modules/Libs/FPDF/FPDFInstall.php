<?php
/**
 * FPDFInstall class.
 * 
 * This module uses FPDF library released as freeware
 * version 1.53
 * author Olivier PLATHEY
 * Modifications by Paul Bukowski (pbukowski@telaxus.com). Do not send bug reports to author.
 * link http://www.fpdf.org homesite
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage fpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_FPDFInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}

	public function version() {
		return array('1.5.3');
	}
	public function requires($v) {
		return array();
	}
}

?>
