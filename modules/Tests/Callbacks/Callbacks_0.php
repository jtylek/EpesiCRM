<?php
/**
 * Example event module
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage callbacks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Callbacks extends Module {

	public function body() {
		TestsCommon::heading(__('Callbacks'));
		print '<div class="d-flex flex-wrap gap-2 mb-2">';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'instead')).'>Instead</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'before')).'>Before</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'src')).'>Source of this example</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'form1')).'>Form test</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'incr'),0).'>Incr test</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'a1')).'>Other module (this->a1)</a>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'a1_stack')).'>Other module on stack (this->a1)</a>';
		print '</div>';
		$x = microtime(true);
		for($i=0; $i<1000; $i++)
			$this->create_callback_href(array($this,'a1_stack'),array($i));
		print '<p class="small text-body-secondary">Generation time of 1000 callbacks: '.(microtime(true)-$x).'</p>';
	}

	public function incr($inc) {
		print '<p>'.$inc.'</p>';
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'incr'),$inc+1).'>Incr test</a> ';
		print '<a class="btn btn-outline-secondary btn-sm" '.$this->create_callback_href(array($this,'this_stack')).'>This on stack (this)</a>';
		return true;
	}

	public function a1() {
		if($this->is_back()) return false;
		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'a2')).'>Other module (this->a2 with pack a)</a> ';
		print '<a class="btn btn-outline-secondary btn-sm" '.$this->create_back_href().'>Back</a>';
		return true;
	}
	
	public function a1_stack() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Tests/Callbacks/a','body');
		return true;
	}
	
	public function this_stack() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Tests/Callbacks','body');
		return true;
	}
	
	public function a2() {
		$this->pack_module('Tests/Callbacks/a');
		if($this->is_back()) return false;
		return true;
	}
	
	public function form1() {
		$form = $this->init_module(Libs_QuickForm::module_name(),null,'f1');
		$form->addElement('header',null,'Form 1');
		$form->addElement('submit',null,'OK');
	
		if($form->validate()) {
			print '<p class="text-success">Form 1 validated</p>';
			return false;
		} else {
			print '<p><a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'form2')).'>Go to form2</a></p>';
			$form->display();
		}
		return true;
	}

	public function form2() {
		if($this->is_back()) return false;
		$form = $this->init_module(Libs_QuickForm::module_name(),null,'f2');
		$form->addElement('header',null,'Form 2');
		$form->addElement('textarea','text','Form 2');
		$form->addElement('submit',null,'OK');
		$form->addElement('button',null,'Cancel',$this->create_back_href());
		
		if($form->validate()) {
			print '<p class="text-success">Form 2 validated</p>';
			print '<p>'.htmlspecialchars($form->exportValue('text')).'</p>';
		//	return true;
		}
		$form->display();
		return true;
	}

	public function instead() {
		if($this->is_back()) return false;

		print '<p>instead</p>';

		print '<a class="btn btn-outline-primary btn-sm" '.$this->create_callback_href(array($this,'instead2')).'>Instead2</a> ';
		print '<a class="btn btn-outline-secondary btn-sm" '.$this->create_back_href().'>Back</a>';

		return true;
	}

	public function instead2() {
		if($this->is_back()) return false;

		print '<p>instead2</p>';

		print '<a class="btn btn-outline-secondary btn-sm" '.$this->create_back_href().'>Back</a> ';
		print '<a class="btn btn-outline-secondary btn-sm" '.$this->create_back_href(2).'>Back twice</a>';

		return true;
	}

	public function before() {
		print '<p>before</p>';
		return false;
	}

	public function src() {
		TestsCommon::source_card($this, 'modules/Tests/Callbacks/', array(
			'Install' => 'CallbacksInstall.php',
			'Main' => 'Callbacks_0.php',
			'Common' => 'CallbacksCommon_0.php',
		));
	}

}

?>