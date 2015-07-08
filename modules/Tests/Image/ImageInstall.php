<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage image
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ImageInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(array('name'=>Utils_CatFileInstall::module_name(),'version'=>0),
			array('name'=>Utils_ImageInstall::module_name(),'version'=>0)
		);
	}
}

?>
