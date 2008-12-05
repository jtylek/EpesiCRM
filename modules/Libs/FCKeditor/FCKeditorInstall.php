<?php
/**
 * This module uses FCKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * FCKeditor - The text editor for Internet - http://www.fckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage fckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_FCKeditorInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('2.4.2');
	}
	
	public function requires($v) {
		return array(array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
}

?>