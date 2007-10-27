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
		
		$f->addElement('commondata_group','xxx2','commondata_group', 'Countries',array('depth'=>2,'separator'=>'<br>','empty_option'=>true));

		$f->addElement('commondata','cd_country','commondata Country', 'Countries', array('empty_option'=>true));
		$f->addElement('commondata','cd_state','commondata State', array('Countries','cd_country'));
		$f->addElement('commondata','cd_city','commondata City', array('Countries','cd_country','cd_state'));
		$f->addElement('commondata','cd_street','commondata street', array('Countries','cd_country','cd_state','cd_city'));
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
