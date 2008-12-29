<?php
/**
 * Codepress editor
 * This module uses CodePress editor released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-libs
 * @subpackage codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_CodepressInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.9.6");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Codepress editor',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>