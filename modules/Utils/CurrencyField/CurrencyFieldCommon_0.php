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
	public function format($val, $currency=null) {
		if (!isset($currency) || !$currency) {
			$val = self::get_values($val);
			$currency = $val[1];
			$val = $val[0];
		}
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
		if ($pos_before) $ret = $currency.'&nbsp;'.$ret;
		else $ret = $ret.'&nbsp;'.$currency;
		return $ret;
	}
	
	public function get_values($p) {
		if (!is_array($p)) $p = explode('__', $p);
		if (!isset($p[1])) $p[1] = Base_User_SettingsCommon::get('Utils_CurrencyField', 'default_currency');
		return $p;
	}
	
	public function format_default($v, $c=null) {
		if ($c===null) {
			$c = explode('__',$v);
			if (!isset($c[1])) {
				$c = 1;
			} else {
				$v = $c[0];
				$c = $c[1];
			}
		}
		$v=str_replace('.', self::get_decimal_point(), round($v,self::get_precission($c)));
		return $v.'__'.$c;
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
		static $cache = null;
		if ($cache==null) $cache = DB::GetOne('SELECT decimal_sign FROM utils_currency WHERE id=%d', array(Base_User_SettingsCommon::get('Utils_CurrencyField','decimal_point')));
		return $cache;
	}
	
	public static function get_id_by_code($code) {
		return DB::GetOne('SELECT id FROM utils_currency WHERE code=%s', array($code));
	}
	
	public static function get_precission($arg) {
		static $cache = array();
		if (!isset($cache[$arg])) $cache[$arg] = DB::GetOne('SELECT decimals FROM utils_currency WHERE id=%d', array($arg));
		return $cache[$arg];
	}
	
	public function admin_caption() {
		return 'Currencies';
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['currency'] = array('modules/Utils/CurrencyField/currency.php','HTML_QuickForm_currency');

?>
