<?php
/**
 * Flash Charts
 *
 * This module uses Open Flash Chart, displays data as a chart in flash.
 * Copyright (C) 2007 John Glazebrook
 * distributed under the terms of the GNU General Public License version 2 or later
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-libs
 * @subpackage openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_OpenFlashChartInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'Flash Charts',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>