<?php
/**
 * Mail_ContactUsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUsInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
	    return true;
	}
	
	public function uninstall() {
	    return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Mail', 'version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/StatusBar', 'version'=>0),
			array('name'=>'Base/User/Login', 'version'=>0));
	}
}

?>
