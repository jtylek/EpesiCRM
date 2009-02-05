<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyFieldCommon extends ModuleCommon {
	public function format($val, $currency=1, $symbol=true) {
		if (!isset($currency) || !$currency) $currency = 1;
		$params = DB::GetRow('SELECT * FROM utils_currency WHERE id=%d', array($currency));
		// TODO: cache here
		$dec_delimiter = $params['decimal_sign'];
		$thou_delimiter = $params['thousand_sign'];
		$dec_digits = $params['decimals'];
		$currency = $params['symbol'];
		$pos_before = $params['pos_before'];
		
		if (!$val) $val = '0';
		if (!strrchr($val,$dec_delimiter)) $val .= $dec_delimiter; 
		$cur = explode($dec_delimiter, $val);
		if (!isset($cur[1])) $cur[1] = ''; 
		$cur[1] = str_pad($cur[1], $dec_digits, '0');
		$val = $cur[0].'.'.$cur[1];
		$ret = number_format($val, $dec_digits, $dec_delimiter, $thou_delimiter);
		if ($symbol) {
			if ($pos_before) $ret = $currency.'&nbsp;'.$ret;
			else $ret = $ret.'&nbsp;'.$currency;
		}
		return $ret;
	}
	
	public function get_values($p) {
		$p = explode('__', $p);
		if (!isset($p[1])) $p[1] = 1;
		return $p;
	}

	public function user_settings() {
		$currency_options = DB::GetAssoc('SELECT id, code FROM utils_currency WHERE active=1');
		$decimal_point_options = DB::GetAssoc('SELECT id, decimal_sign FROM utils_currency WHERE active=1');
		return array('Regional settings'=>array(
				array('name'=>'default_currency','label'=>'Default currency','type'=>'select','values'=>$currency_options,'default'=>1),
				array('name'=>'decimal_point','label'=>'Currency decimal point','type'=>'select','values'=>$decimal_point_options,'default'=>1)
					));
	}
	
	public static function get_decimal_point() {
		return DB::GetOne('SELECT decimal_sign FROM utils_currency WHERE id=%d', array(Base_User_SettingsCommon::get('Utils_CurrencyField','decimal_point')));
	}
	
	public static function get_precission($arg) {
		return DB::GetOne('SELECT decimals FROM utils_currency WHERE id=%d', array($arg));
	}
	
	public function admin_caption() {
		return 'Currencies';
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['currency'] = array('modules/Utils/CurrencyField/currency.php','HTML_QuickForm_currency');

?>
