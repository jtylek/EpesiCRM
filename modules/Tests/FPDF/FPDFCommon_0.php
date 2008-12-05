<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage fpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_FPDFCommon extends ModuleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'FPDF page'=>array()));
	}
}
?>
