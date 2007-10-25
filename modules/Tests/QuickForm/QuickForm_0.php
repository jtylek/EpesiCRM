<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-tests
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_QuickForm extends Module{
	public function body(){
		$f = $this->init_module('Libs/QuickForm');
		$f->addElement('datepicker','xxx','Date picker');
		$f->addElement('commondata','xxx2','Commondata test', 'Countries',2);
		$f->addElement('submit',null,'ok');
		if($f->validate()) {
			print_r($f->exportValues());
		}
		$f->display();
		
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/QuickForm/QuickFormInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/QuickForm/QuickForm_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/QuickForm/QuickFormCommon_0.php');
		
	}
}

?>
