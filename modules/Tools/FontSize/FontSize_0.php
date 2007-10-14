<?php
/**
 * Tools_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package tools-fontsize
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Tools_FontSize extends Module {
	
	/**
	 * For internal use only.
	 */
	public function body() {
		load_js($this->get_module_dir().'fs.js');
		$theme =  & $this->pack_module('Base/Theme');
		// onMouseOver="Base_FontSize_overIncrease()" onMouseOut="Base_FontSize_outIncrease()"
		// onMouseOver="Base_FontSize_overDecrease()" onMouseOut="Base_FontSize_outDecrease()"
		$theme->assign('increaseOnClick', 'href=javascript:Tools_FontSize_changeFontSize(10)');
		$theme->assign('decreaseOnClick', 'href=javascript:Tools_FontSize_changeFontSize(-10)');
		$theme->display();
	}
}
?>
