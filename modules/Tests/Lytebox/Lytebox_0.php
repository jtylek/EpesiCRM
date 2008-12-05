<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Lytebox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Lytebox extends Module{
	public function body(){
		print('This is an example Lytebox page.<hr>');
		print('<a href="images/loader.gif" rel="lyteshow">Lytebox image</a><br>');

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lytebox/LyteboxInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lytebox/Lytebox_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lytebox/LyteboxCommon_0.php');
		
	}
}

?>
