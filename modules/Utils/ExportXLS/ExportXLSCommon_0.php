<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage exportxls
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ExportXLSCommon extends ModuleCommon {
	static $row = 0;
	
	function BOF() {
		self::$row = 0;
		return pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);  
	}
	
	function EOF() {
		return pack("ss", 0x0A, 0x00);
	}
	
	function write_number($row, $col, $value) {
		return pack("sssss", 0x203, 14, $row, $col, 0x0).pack("d", $value);
	}
	
	function write_string($row, $col, $value ) {
		$l = strlen($value);
		return pack("ssssss", 0x204, 8 + $l, $row, $col, 0x0, $l).$value;
	}

	function write_row( $arr ) {
		$xls = '';
		$col = 0;
		foreach ($arr as $v) {
			if (is_numeric($v)) $xls .= self::write_number(self::$row, $col, $v);
			else $xls .= self::write_string(self::$row, $col, $v);
			$col++;
		}
		self::$row++;
		return $xls;
	}
}
?>
