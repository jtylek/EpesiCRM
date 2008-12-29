<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shared-unique-href
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Callbacks_a extends Module {
	
	public function body() {
		if($this->is_back()) //required in main display method, because it goes to parent only here
			return;
		print('This is module A<br>');
		print('<a '.$this->create_callback_href(array($this,'x1')).'>Other module (a->x1)</a> :: ');
		print('<a '.$this->create_back_href().'>Back</a>');
	}
	
	public function x1() {
		if($this->is_back()) return false;
		print('This is module A, callback x1<br>');
		print('<a '.$this->create_back_href().'>Back</a> :: ');
		print('<a '.$this->create_back_href(2).'>Back twice</a> :: ');
		print('<a '.$this->create_back_href(3).'>Back root</a>');
		return true;
	}

}
?>


