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

class Data_TaxRatesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return 'Tax Rates';
	}

	public static function get_tax_details() {
		static $cache = null;
		if ($cache===null) {
			$cache = array();
			$r = Utils_RecordBrowserCommon::get_records('data_tax_rates');
			foreach ($r as $v) $cache[$v['id']] = array('name'=>$v['name'],'percentage'=>$v['percentage']);
		}
		return $cache;
	}
	
	public static function get_tax_name($id) {
		if (!is_numeric($id)) return '';
		$cache = self::get_tax_details();
		return $cache[$id]['name'];
	}

	public static function get_tax_rate($id) {
		if (!is_numeric($id)) return 0;
		$cache = self::get_tax_details();
		return $cache[$id]['percentage'];
	}

	public static function get_tax_rates() {
		static $cache = null;
		if ($cache===null) {
			$cache = array();
			$rates = self::get_tax_details();
			foreach ($rates as $k=>$v)
				$cache[$k] = $v['name']; 
		}
		return $cache;
	}
}
?>