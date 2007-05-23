<?php
/**
 * CatFileInstall class.
 * 
 * This class provides initialization data for CatFile module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for CatFile module.
 * @package tcms-tests
 * @subpackage fpdf
 */
class Tests_FPDFInstall extends ModuleInstall {
	public static function install() {
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
}

?>
