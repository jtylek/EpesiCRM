<?php
/**
 * About Epesi
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutCommon extends ModuleCommon {
	public static function menu() {
		return array('Help'=>array('__submenu__'=>1,'__weight__'=>1000,'About'=>array('__weight__'=>100,'__function__'=>'info')));
	}	
}

?>
