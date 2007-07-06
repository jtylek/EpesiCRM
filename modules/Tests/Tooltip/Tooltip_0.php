<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Tooltip extends Module {
	
	public function body($arg) {
		print "Tooltip Test<hr>";
		$this->pack_module('Utils/Tooltip', array('point mouse here', 'tip'));
		//------------------------------ print out src
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Tooltip/Tooltip_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Tooltip/TooltipCommon_0.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Tooltip/TooltipInit_0.php');
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Tooltip/TooltipInstall.php');
	}
}
?>



