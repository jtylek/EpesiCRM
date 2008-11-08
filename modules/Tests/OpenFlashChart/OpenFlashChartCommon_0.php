<?php
/**
 * Testing flash charts
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package tests-openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_OpenFlashChartCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'OpenFlashChart'=>array()));
	}


}

?>