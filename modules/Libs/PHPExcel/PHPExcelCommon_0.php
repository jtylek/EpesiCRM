<?php
/**
 * Excel import/export library
 * @author shacky@poczta.fm
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-Libs
 * @subpackage PHPExcel
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_PHPExcelCommon extends ModuleCommon {
	public static function load($file) {
		return PHPExcel_IOFactory::load($file);
	}
}

?>