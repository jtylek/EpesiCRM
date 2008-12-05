<?php
/**
 * DirtyReadInstall class.
 * 
 * This class provides initialization data for DirtyRead module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage DirtyRead
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_DirtyReadInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		return true;
	}

	public function version() {
		return array('0.9.6');
	}
	public function requires($v) {
		return array(array('name'=>'Base/Lang','version'=>0));
	}
}

?>
