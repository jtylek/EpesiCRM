<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-data
 * @subpackage usa-states
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_USAStates extends Module {
	public function admin() {
		$this->pack_module('Utils/CommonData','USA States','admin_array');
	}
}

?>