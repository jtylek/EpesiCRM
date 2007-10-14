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
	public function body() {
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('increaseOnClick', 'onClick="Tools_FontSize.change(3)"');
		$theme->assign('decreaseOnClick', 'onClick="Tools_FontSize.change(-3)"');
		$theme->display();
	}
}
?>
