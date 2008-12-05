<?php
/**
 * WizardTest class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Wizard extends Module {
	
	public function display_results($data) {
		print_r($data);
	}
	
	public function page1($f) {
		$f->addElement('header', null, 'Page 1');
		$f->addElement('text', 'page_1_input', 'page_1_input');
		$f->addRule('page_1_input', 'Required!', 'required');
	}
	
	public function jump_page0($d) {
		if($d['select_0']==1) return 'page1';
		return 2;
	}
	
	public function body() {
		print "Wizard Test<hr>";
		$wizard = & $this->init_module('Utils/Wizard');
		
		
		//get form
		$f = & $wizard->begin_page();
		$f->addElement('header', null, 'Welcome Page... ');
		$f->addElement('select', 'select_0', 'Jump to page', array(1=>'1', 2=>'2'));
		//method decides about jump to page
		$wizard->next_page(array($this,'jump_page0'));
		
		//call method that generates form named 'test2'
		$this->page1($wizard->begin_page('page1'));
		//always jump to page 3
		$wizard->next_page(3);
		
		
		$f = & $wizard->begin_page();
		$f->addElement('header', null, 'Page 2');
		$f->addElement('text', 'page_2_input', 'page_2_input');
		$f->addRule('page_2_input', 'Required!', 'required');
		//jump to next page
		$wizard->next_page();

		$f = & $wizard->begin_page();
		$f->addElement('header', null, 'Yeah! you came from page 1 or 2');
		$wizard->next_page();
		
		
		//call wizard with process function specified as third arg
		$this->display_module($wizard, array(array($this,'display_results')));
	
	
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Wizard/WizardInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Wizard/Wizard_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Wizard/WizardCommon_0.php');
	}
	
}
?>


