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

class Data_TaxRatesInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());

		$fields = array(
			array('name'=>'Name', 	'type'=>'text', 'required'=>true, 'param'=>16, 'extra'=>false, 'visible'=>true),
			array('name'=>'Description', 	'type'=>'long text', 'required'=>false, 'extra'=>false),
			array('name'=>'Percentage', 	'type'=>'float', 'required'=>true, 'extra'=>false, 'visible'=>true)
		);

		Utils_RecordBrowserCommon::install_new_recordset('data_tax_rates', $fields);
		
		Utils_RecordBrowserCommon::set_caption('data_tax_rates', 'Tax Rates');
		Utils_RecordBrowserCommon::set_access_callback('data_tax_rates', array('Data_TaxRatesCommon', 'access_tax_rates'));
		Utils_RecordBrowserCommon::set_icon('data_tax_rates', Base_ThemeCommon::get_template_filename('Data/TaxRates', 'icon.png'));

		$this->add_aco('browse tax rates',array('Employee Manager'));
		$this->add_aco('view tax rate',array('Employee Manager'));
		$this->add_aco('edit tax rate',array('Employee Manager'));
		$this->add_aco('delete tax rate',array('Employee Manager'));
				
		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::uninstall_recordset('premium_warehouse_tax_rate');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array('1.0');
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/RecordBrowser','version'=>0));
	}
}

?>
