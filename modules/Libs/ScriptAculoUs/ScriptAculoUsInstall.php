<?php
/**
 * This module uses scriptaculous.js released under MIT license
 * Copyright (c) 2005, 2006 Thomas Fuchs (http://script.aculo.us, http://mir.aculo.us)
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage ScriptAculoUs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_ScriptAculoUsInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.8.0');
	}
	public function requires($v) {
		return array();
	}
}

?>
