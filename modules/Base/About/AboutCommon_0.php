<?php
/**
 * About Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutCommon extends ModuleCommon {
	public static function menu() {
		return array('Help'=>array('__submenu__'=>1,'__weight__'=>1000,'About'=>array('__weight__'=>100,'__function__'=>'info')));
	}	
}

?>
