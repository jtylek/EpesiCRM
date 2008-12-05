<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage TabbedBrowser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TabbedBrowserCommon extends ModuleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Tabbed Browser'=>array()));
	}
}

?>
