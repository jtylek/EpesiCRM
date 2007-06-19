<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Callbacks extends Module {

	public function body($arg) {
		print('<a '.$this->create_callback_href(array($this,'instead')).'>Instead</a> :: ');
		print('<a '.$this->create_callback_href(array($this,'before')).'>Before</a> :: ');
		print('<a '.$this->create_callback_href(array($this,'src')).'>Source of this example</a>');
	}
	
	public function instead() {
		if($this->is_back()) return false;
		
		print('instead<hr>');
		
		print('<a '.$this->create_callback_href(array($this,'instead2')).'>Instead2</a> :: ');
		print('<a '.$this->create_back_href().'>Back</a>');
		
		return true;
	}

	public function instead2() {
		if($this->is_back()) return false;
		
		print('instead2<hr>');
		
		print('<a '.$this->create_back_href().'>Back</a>');
		
		return true;
	}
	
	public function before() {
		print('before<hr>');
		return false;
	}
	
	public function src() {
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Callbacks/CallbacksInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Callbacks/CallbacksInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Callbacks/Callbacks_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Callbacks/CallbacksCommon_0.php');
	}

}

?>