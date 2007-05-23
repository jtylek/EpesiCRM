<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_USAStates extends Module {
	public function admin() {
		$this->pack_module('Utils/CommonData','USA States','admin_array');
	}
}

?>