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

//		$f->addElement('commondata_group','xxx2','commondata_group', 'Countries',array('depth'=>2,'separator'=>'<br>','empty_option'=>true));

		$f->addElement('commondata','cd_country','commondata Country', 'Countries', array('empty_option'=>true),array('id'=>'dddd1'));
		$f->addElement('commondata','cd_state','commondata State', array('Countries','cd_country'), array('empty_option'=>true));
		$f->addElement('commondata','cd_city','commondata City', array('Countries','cd_country','cd_state'),array('id'=>'dddd3'));
		$f->addElement('commondata','cd_street','commondata street', array('Countries','cd_country','cd_state','cd_city'));
		$f->setDefaults(array('cd_country'=>'US'));
//		$f->addRule('cd_city','required','required');
//		print($x->getValue().'<br>');
//		$f->freeze();

		$c1 = $f->createElement('checkbox','c1','c1_l','c1_t');
		$c2 = $f->createElement('checkbox','c2','c2_l','c2_t');
		$c3 = $f->createElement('checkbox','c3','c3_l','c3_t');
		$f->addGroup(array($c1,$c2,$c3),'g','g_l');
		$f->add_array(array(array('type'=>'group','elems'=>array(array('type'=>'checkbox','label'=>'c1_l','name'=>'c1','values'=>'c1_t','default'=>0),array('type'=>'checkbox','label'=>'c2_l','name'=>'c2','values'=>'c2_t','default'=>0)), 'label'=>'radio')));

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
