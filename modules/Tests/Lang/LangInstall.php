<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LangInstall extends ModuleInstall{
	public function install(){
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}

	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(	array('name'=>'Utils/CatFile','version'=>0),
						array('name'=>'Base/Lang','version'=>0));
	}
} 
?>
