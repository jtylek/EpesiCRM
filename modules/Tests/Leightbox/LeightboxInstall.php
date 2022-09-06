<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LeightboxInstall extends ModuleInstall{
	public function install(){
		return true;
	}

	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(array('name'=>Utils_CatFileInstall::module_name(),'version'=>0),
			array('name'=>Utils_PopupCalendarInstall::module_name(),'version'=>0),
			array('name'=>Utils_RecordBrowser_RecordPickerInstall::module_name(),'version'=>0),
			array('name'=>Libs_LeightboxInstall::module_name(),'version'=>0));
	}
} 
?>
