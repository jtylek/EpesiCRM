<?php
/**
 * This module uses CKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * CKeditor - The text editor for Internet - http://www.Ckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage Ckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_CKEditorInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('3.5.2');
	}
	
	public function requires($v) {
		return array(array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
    public static function simple_setup() {
        return false;
    }
}

?>