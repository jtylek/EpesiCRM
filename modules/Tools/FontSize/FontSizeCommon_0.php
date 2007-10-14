<?php
/**
 * Tools_FontSize class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package tools-fontsize
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_FontSizeCommon extends ModuleCommon {
	public static function menu() {
		return array('Tools'=>array('__submenu__'=>1,'Font size'=>array()));
	}
}

load_js('modules/Tools/FontSize/fs.js');

?>
