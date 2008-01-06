<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyFieldCommon extends ModuleCommon {
	private static $dec_delimiter = '.';
	private static $thou_delimiter = ',';
	private static $dec_digits = 2;
	private static $currency = '$';

	public function format($val) {
		if (!$val) $val = '0';
		if (!strrchr($val,self::$dec_delimiter)) $val .= self::$dec_delimiter; 
		$cur = explode(self::$dec_delimiter, $val);
		if (!isset($cur[1])) $cur[1] = ''; 
		$cur[1] = str_pad($cur[1], self::$dec_digits, '0');
		$val = $cur[0].'.'.$cur[1];
		return number_format($val, self::$dec_digits, self::$dec_delimiter, self::$thou_delimiter).'&nbsp;'.self::$currency;
	}

}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['currency'] = array('modules/Utils/CurrencyField/currency.php','HTML_QuickForm_currency');

?>
