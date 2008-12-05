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

class Tools_FontSizeCommon extends ModuleCommon {
	public static function menu() {
		return array('My settings'=>array('__submenu__'=>1,'Font size'=>array('__submenu__'=>1,'Font big'=>array('__icon__'=>'big.png','__url__'=>'javascript:Tools_FontSize.change(3)'),'Font normal'=>array('__icon__'=>'normal.png','__url__'=>'javascript:Tools_FontSize.change(0)'),'Font small'=>array('__url__'=>'javascript:Tools_FontSize.change(-3)','__icon__'=>'small.png'))));
	}
}

load_js('modules/Tools/FontSize/fs.js');

?>
