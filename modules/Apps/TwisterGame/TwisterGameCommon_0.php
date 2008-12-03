<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage twistergame
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_TwisterGameCommon extends ModuleCommon {
    
    public static function menu() {
		return array('Games'=>array('__submenu__'=>1,'Twister'=>array()));
	}
}

?>