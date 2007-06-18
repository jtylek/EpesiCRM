<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-data
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_USAStates extends Module {
	public function admin() {
		$this->pack_module('Utils/CommonData','USA States','admin_array');
	}
}

?>