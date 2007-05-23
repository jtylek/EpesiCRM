<?php
/**
 * FPDFInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for DirtyRead module.
 * @package tcms-libs
 * @subpackage fpdf
 */
class Libs_FPDFInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}

	public static function version() {
		return array('1.5.3');
	}
}

?>
