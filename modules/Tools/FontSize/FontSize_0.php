<?php
/**
 * Tools_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tools
 * @subpackage fontsize
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Tools_FontSize extends Module {
	public function body() {
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('increase', 'onClick="Tools_FontSize.change(3)"');
		$theme->assign('normal', 'onClick="Tools_FontSize.change(0)"');
		$theme->assign('decrease', 'onClick="Tools_FontSize.change(-3)"');
		$theme->display();
	}
}
?>
