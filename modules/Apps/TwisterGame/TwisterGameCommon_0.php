<?php
/**
 * 
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license EPL
 * @version 0.1
 * @package apps-twistergame
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_TwisterGameCommon extends ModuleCommon {
    
    public static function menu() {
		return array('Games'=>array('__submenu__'=>1,'Twister'=>array()));
	}
}

?>