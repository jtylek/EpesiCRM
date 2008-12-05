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
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/PopupCalendar','version'=>0),
			array('name'=>'Utils/ChainedSelect','version'=>0),
			array('name'=>'Data/Countries','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
} 
?>
