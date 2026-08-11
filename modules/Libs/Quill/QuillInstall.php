<?php
/**
 * Quill Editor - https://quilljs.com
 * Copyright (c) 2017-2024, Slab. Copyright (c) 2014, Jason Chen. Copyright (c) 2013, salesforce.com
 * Released under the BSD 3-Clause License.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage Quill
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_QuillInstall extends ModuleInstall {

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
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0));
	}
	public static function simple_setup() {
		return false;
	}
}

?>
