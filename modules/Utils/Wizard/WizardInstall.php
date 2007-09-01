<?php
/**
 * WizardInstall class.
 * 
 * This class provides initialization data for Wizard module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WizardInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0)
		);
	}
}

?>
