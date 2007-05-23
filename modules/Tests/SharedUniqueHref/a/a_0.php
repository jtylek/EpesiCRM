<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref_a extends Module {
	
	public function body($arg) {
		print('Submodule received: '.$this->get_unique_href_variable('test'));
	}
}
?>


