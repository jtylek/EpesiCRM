<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Tooltip extends Module {
	
	public function body() {
		print "Tooltip Test ver 1.5<hr>";
		print(Utils_TooltipCommon::create('point mouse here', 'tip'));
		//------------------------------ print out src
		print('<hr><b>Main</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/Tooltip/Tooltip_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/Tooltip/TooltipCommon_0.php');
		print('<hr><b>Install</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/Tooltip/TooltipInstall.php');
	}
}
?>



