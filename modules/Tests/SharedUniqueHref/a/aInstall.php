<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref_aInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function version() {
		return array('2.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
