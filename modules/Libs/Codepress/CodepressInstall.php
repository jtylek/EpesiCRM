<?php
/**
 * Codepress editor
 * This module uses CodePress editor released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Codepress editor',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>