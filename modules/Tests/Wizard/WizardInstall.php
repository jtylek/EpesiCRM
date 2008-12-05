<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_WizardInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Wizard','version'=>0)
		);
	}
}

?>
