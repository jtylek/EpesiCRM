<?php
/**
 * Epesi developer editor
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-develop
 * @subpackage moduleeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_ModuleEditorInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/Codepress','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/TabbedBrowser','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Epesi developer editor',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public function simple_setup() {
        return false;
	}
}

?>