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
		
		$x = $f->addElement('datepicker','xxxy','Date picker');
		$f->setDefaults(array('xxxy'=>time()));
//		print($x->getValue().'<br>');

		$f->addElement('commondata_group','xxx2','commondata_group', 'Countries',array('depth'=>2,'separator'=>'<br>','empty_option'=>true));

		$f->addElement('commondata','cd_country','commondata Country', 'Countries', array('empty_option'=>true));
		$f->addElement('commondata','cd_state','commondata State', array('Countries','cd_country'), array('empty_option'=>true));
		$f->addElement('commondata','cd_city','commondata City', array('Countries','cd_country','cd_state'));
		$f->addElement('commondata','cd_street','commondata street', array('Countries','cd_country','cd_state','cd_city'));
		$f->setDefaults(array('cd_country'=>'US','cd_state'=>'AL'));
//		$f->addRule('cd_city','required','required');
//		print($x->getValue().'<br>');

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
