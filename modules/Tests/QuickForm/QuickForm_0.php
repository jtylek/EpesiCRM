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

class Tests_QuickForm extends Module{
	
	public function body(){
		$f = $this->init_module('Libs/QuickForm');

		$f->addElement('automulti','justble','Bleing', array($this->get_type().'Common', 'automulti_search'), array('ble'), array($this->get_type().'Common', 'automulti_format'));
		$f->setDefaults(array('justble'=>array(2,3)));
		$f->addElement('multiselect','justble2','Bleing2', array());
		$f->addElement('text','frozen','Frozen test');
		$f->addRule('frozen','required','required');
		$x = $f->addElement('timestamp','xxxyss','Date picker');
		print('get(here is what was submited): '.$x->getValue().'<br>');
		print('export: '.$f->exportValue('xxxyss').'<br>');
		$f->applyFilter('xxxyss',array($this,'ble_filter'));
		$f->registerRule('ble_rule', 'callback', 'ble_rule',$this);
		$f->addRule('xxxyss','ble rule not passed','ble_rule');
		$f->addRule('xxxyss','required rule not passed','required');
		$f->addElement('text','ble','Test');
		$f->addElement('autocomplete','auto_test','Autocomplete', array($this->get_type().'Common', 'autocomplete'));

		$f->addElement('currency','cur','Currency');
//		$f->setDefaults(array('xxxyss'=>time()));
//		$f->freeze(array('xxxyss'));
//		$f->setDefaults(array('cur'=>'1252341.22'));

//		$f->addElement('commondata_group','xxx2','commondata_group', 'Countries',array('depth'=>2,'separator'=>'<br>','empty_option'=>true));

		$f->addElement('commondata','cd_country','commondata Country', 'Countries', array('empty_option'=>true),array('id'=>'dddd1'));
		$f->addElement('commondata','cd_state','commondata State', array('Countries','cd_country'), array('empty_option'=>true));
		$f->addElement('commondata','cd_city','commondata City', array('Countries','cd_country','cd_state'),array('id'=>'dddd3'));
		$f->addElement('commondata','cd_street','commondata street', array('Countries','cd_country','cd_state','cd_city'));
		$f->setDefaults(array('cd_country'=>'US'));
//		$f->addRule('cd_city','required','required');
//		print($x->getValue().'<br>');
//		$f->freeze();
		$f->addElement('select','sel1','sel1', array('x'=>'x','y'=>'y'),array('id'=>'sel1'));
		$f->addElement('select','sel2','sel2', array(),array('id'=>'sel2'));
		$f->addElement('select','sel3','sel3', array(),array('id'=>'sel3'));

		$f->setDefaults(array('sel2'=>'y'));
		print('freezing<hr>');
		$f->freeze(array('frozen'));
		Utils_ChainedSelectCommon::create('sel2',array('sel1'),'modules/Tests/QuickForm/update_sel.php',null,$f->exportValue('sel2'));
		Utils_ChainedSelectCommon::create('sel3',array('sel1','sel2'),'modules/Tests/QuickForm/update_sel.php',array('test'=>'finito '),$f->exportValue('sel3'));


		$select1[0] = 'Pop';
		$select1[1] = 'Classical';
		$select1[2] = 'Funeral doom';
		$f->addElement('select','sel11','sel11', $select1,array('id'=>'sel11'));
		$f->addElement('select','sel22','sel22', array(),array('id'=>'sel22'));
		$f->addElement('select','sel33','sel33', array(),array('id'=>'sel33'));
		Utils_ChainedSelectCommon::create('sel22',array('sel11'),'modules/Tests/QuickForm/update_sel2.php',null,$f->exportValue('sel22'));
		Utils_ChainedSelectCommon::create('sel33',array('sel11','sel22'),'modules/Tests/QuickForm/update_sel2.php',null,$f->exportValue('sel33'));
		

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
	
	public function ble_filter($x) {
		print('filter: '.print_r($x,true).'<br>');
		return $x;
	}
	
	public function ble_rule($x) {
		print('rule: '.print_r($x,true).'<br>');
		return true;
	}
}

?>
