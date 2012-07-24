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
			array('name' => _M('Name'), 	'type'=>'text', 'required'=>true, 'param'=>16, 'extra'=>false, 'visible'=>true),
			array('name' => _M('Description'), 	'type'=>'long text', 'required'=>false, 'extra'=>false),
			array('name' => _M('Percentage'), 	'type'=>'float', 'required'=>true, 'extra'=>false, 'visible'=>true)
		);

		Utils_RecordBrowserCommon::install_new_recordset('data_tax_rates', $fields);

		Utils_RecordBrowserCommon::new_record('data_tax_rates', array('name'=>'Non-taxable', 'percentage'=>0));
		
		Utils_RecordBrowserCommon::set_caption('data_tax_rates', _M('Tax Rates'));
		Utils_RecordBrowserCommon::set_icon('data_tax_rates', Base_ThemeCommon::get_template_filename('Data/TaxRates', 'icon.png'));

		Utils_RecordBrowserCommon::add_access('data_tax_rates', 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('data_tax_rates', 'add', array('ACCESS:employee','ACCESS:manager'));
		Utils_RecordBrowserCommon::add_access('data_tax_rates', 'edit', array('ACCESS:employee','ACCESS:manager'));
		Utils_RecordBrowserCommon::add_access('data_tax_rates', 'delete', array('ACCESS:employee','ACCESS:manager'));

		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::uninstall_recordset('data_tax_rates');
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

	public function simple_setup() {
        return false;
	}
}

?>
