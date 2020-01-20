<?php
/**
 * CatFileInstall class.
 * 
 * This class provides initialization data for CatFile module.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
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
