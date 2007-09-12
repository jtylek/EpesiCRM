<?php
/**
 * CatFile class.
 * 
 * Displays file content
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-utils
 * @subpackage catfile
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CatFile extends Module {
	/**
	 * Displays PHP code from selected file with syntax highlighting.
	 * 
	 * @param string filename
	 */
	public function body($arg) {
		print('<div align="left">');
		if (file_exists($arg)) {
			highlight_file($arg);
		} else {
			echo "File $arg does not exist.";
		}
		print('</div>');
	}
}
?>
