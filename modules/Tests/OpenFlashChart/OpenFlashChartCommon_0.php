<?php
/**
 * Testing flash charts
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_OpenFlashChartCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'OpenFlashChart'=>array()));
	}


}

?>