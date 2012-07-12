<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-data
 * @subpackage countries
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_Countries extends Module {
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$this->pack_module('Utils/CommonData','Countries','admin_array');
	}
}

?>