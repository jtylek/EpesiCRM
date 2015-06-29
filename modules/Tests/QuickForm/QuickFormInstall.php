<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_QuickFormInstall extends ModuleInstall{
	public function install(){
		return true;
	}

	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(array('name'=>Utils_CatFile::module_name(),'version'=>0),
			array('name'=>Utils_PopupCalendarInstall::module_name(),'version'=>0),
			array('name'=>Utils_ChainedSelectInstall::module_name(),'version'=>0),
			array('name'=>Data_Countries::module_name(),'version'=>0),
			array('name'=>Libs_QuickForm::module_name(),'version'=>0));
	}
} 
?>
