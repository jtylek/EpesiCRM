<?php
/**
 * CatFile class.
 * 
 * Displays file content
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Displays file content
 * 
 * @package epesi-utils
 * @subpackage catfile
 */
class Utils_CatFile extends Module {
	public function body($arg) {
		print('<div align="left">');
		if (file_exists($arg)) {
			highlight_string(file_get_contents($arg));
		} else {
			echo "File $arg does not exist.";
		}
		print('</div>');
	}
}
?>
