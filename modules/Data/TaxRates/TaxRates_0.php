<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-data
 * @subpackage tax-rates
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_TaxRates extends Module {
	private $rb;
	
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$this->rb = $this->init_module('Utils/RecordBrowser','data_tax_rates','data_tax_rates_module');
		$this->display_module($this->rb);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>