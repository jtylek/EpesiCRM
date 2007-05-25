<?php
/**
 * WizardTest class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides functions for presenting data in a table (suports sorting 
 * by different columns and splitting results -- showing 10 rows per page).
 * @package tcms-utils
 * @subpackage generic-browse
 */
class Tests_QFPlayground extends Module {
	private $lang;
	
	public function body($arg) {
		$this->lang = $this->pack_module('Base/Lang');
		
		print "QF Playground<hr>";
		$form = & $this->init_module('Libs/QuickForm');
		$form->addElement('header', null, $this->lang->t('Date test'));
		$ls1 = $form->addElement('multiselect', 'ls1', 'LS1', array(10=>'A',1=>'B',2=>'C',3=>'D'), array('size'=>8,'style'=>'width:100px;'));
//		$ls1->freeze();
		// this is very important comment so I can test some crap
		$form->setDefaults(array('ls1'=>1));
		$ele = $form->addElement('datepicker', 'dp1', 'DP1', array('format'=>'%d/%m/%y'));
		$ele = $form->addElement('datepicker', 'dp2', 'DP2', array('format'=>'%d/%m..%Y'));
		$ele = $form->addElement('datepicker', 'dp3', 'DP3', array('format'=>'%y.%m.%d'));
		$ele = $form->addElement('datepicker', 'dp4', 'DP4', array('format'=>'%y/%m/%d and one more %%y: %y'));
		eval_js($ele->getElementJs());
		
		$form->addElement('header', null, $this->lang->t('Login test'));
		$form->addElement('text', 'username', $this->lang->t('Username'),array('id'=>'username'));
			$form->addRule('username', $this->lang->t('Field required'), 'required');
			
		$form->addElement('password', 'password', $this->lang->t('Password'));
		$form->addRule('password', $this->lang->t('Field required'), 'required');
		$form->addElement('submit', 'submit_button', $this->lang->ht('Login'), array('class'=>'submit'));
		if($form->validate()) {
			$eVs = $form->exportValues();
			print('<hr>');
			print('<hr>');
			print('<hr>');
			print_r($eVs);
		}
		$form->display();
	}
	
	public static function menu() {
		return array('QF Playground'=>array());
	}
	
}
?>


