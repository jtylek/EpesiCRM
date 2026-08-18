<?php
/**
 * Chart.js - https://www.chartjs.org
 * Copyright (c) 2014-2024 Chart.js Contributors
 * Released under the MIT License.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage ChartJS
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_ChartJSInstall extends ModuleInstall {

	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Canvas-based charts (Chart.js)',
			'Author'=>'epesi.help@gmail.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
