<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */

class HTML_QuickForm_currency extends HTML_QuickForm_input {
	private $currency = null;

	function __construct($elementName=null, $elementLabel=null, $filterCurrencies = array(), $attributes=null) {
		parent::__construct($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('text');
		$this->currency = Base_User_SettingsCommon::get('Utils_CurrencyField', 'default_currency');
		$this->filterCurrencies = is_array($filterCurrencies)?$filterCurrencies:array();
		$this->addClass('form-control');
	} //end constructor

	function getFrozenHtml() {
		$val = Utils_CurrencyFieldCommon::get_values(str_replace(Utils_CurrencyFieldCommon::get_decimal_point($this->currency), '.', $this->getValue()));
		return Utils_CurrencyFieldCommon::format($val[0], isset($this->currency)?$this->currency:1);
	}

	function toHtml() {
		$str = "";
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			if(!isset($id)) {
				$id = 'currency_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}

			$this->dec_digits = DB::GetOne('SELECT MAX(decimals) FROM utils_currency');

			$str .= '<div style="position: relative;">';

			$str .= '<div style="margin-right:65px;" class="currency_amount"><input ' . $this->_getAttrString($this->_attributes) . ' '.
					Utils_TooltipCommon::open_tag_attrs(__('Example value: %s',array('123'.Utils_CurrencyFieldCommon::get_decimal_point().implode('',range(4,3+$this->dec_digits)))), false ).
					' /></div>';

			$str .= '<div style="margin-right:5px; width:60px; position:absolute;top:0px;right:0px;"><select style="width:60px;" name="__'.str_replace(array('[',']'),'',$name).'__currency" id="__'.$id.'__currency" class="form-control">';

			if(is_array($this->filterCurrencies) && $this->filterCurrencies)
				$curs = DB::GetAll('SELECT id, symbol, active FROM utils_currency WHERE id IN ('.implode(',',array_map('intval',$this->filterCurrencies)).') ORDER BY code');
			else
				$curs = DB::GetAll('SELECT id, symbol, active FROM utils_currency ORDER BY code');
			foreach ($curs as $v) {
				if ($v['id']!=$this->currency && !$v['active']) continue;
				$str .= '<option value="'.$v['id'].'"';
				if ($v['id']==$this->currency) $str .= ' selected="1"';
				$str .= '>'.$v['symbol'].'</option>';
			}
			$str .= '</select></div>';

			$str .= '</div>';

			eval_js('jq(\'#'.$id.'\').keypress(Utils_CurrencyField.validate)');
			eval_js('jq(\'#'.$id.'\').blur(Utils_CurrencyField.validate_blur)');
		}
		return $str;
	} //end func toHtml

	function exportValue(&$submitValues, $assoc = false) {
		$val = parent::exportValue($submitValues, $assoc);
		if ($val === null) {
			return null;
		}
		if ($assoc) {
			if (!isset($val[$this->getName()])) {
				$key = explode('[', $this->getName());
				$key[1] = str_replace(']','',$key[1]);
				$val = $val[$key[0]][$key[1]];
			} else $val = $val[$this->getName()];
		}
		$tmp = explode('__', $val);
        if(count($tmp)!=2) return null; //invalid value - ignore...
        list($val, $currency) = $tmp;
		$cur = explode(Utils_CurrencyFieldCommon::get_decimal_point($currency), $val);
		if (!isset($cur[1])) $ret = $cur[0]; else {
			$this->dec_digits = DB::GetOne('SELECT decimals FROM utils_currency WHERE id=%d', array($currency));
			$cur[1] = str_pad($cur[1], $this->dec_digits, '0');
			$cur[1] = substr($cur[1], 0, $this->dec_digits);
			$ret = $cur[0] + (($cur[0]<0?-1:1)*$cur[1]/pow(10,$this->dec_digits));
			if (strpos(trim($cur[0]), '-') === 0 && $ret > 0) $ret = -$ret;
		}
		$ret .= '__'.$currency;
		if($assoc) {
			$val = array();
			if (isset($key)) {
				$val[$key[0]][$key[1]] = $ret;
			} else $val[$this->getName()] = $ret;
			return $val;
		} else {
			return $ret;
		}
	}

	function setValue($value) {
		$val = explode('__',$value);
		$this->updateAttributes(array('value'=>str_replace('.',Utils_CurrencyFieldCommon::get_decimal_point($val[1]),$val[0])));
		if (isset($val[1])) $this->currency = $val[1];
		// TODO: float or string? If float is to be accepted, then conversion is neccessary
	} // end func setValue

	function _findValue(& $value) {
		$val = parent::_findValue($value);
		if($val===null) return null;
		if(strpos($val,'__')!==false) return str_replace(',','.',$val);
		$name = $this->getName();
		$curr_field = '__'.str_replace(array('[',']'),'',$name).'__currency';
		if(!isset($value[$curr_field])) return null;
		return str_replace(',','.',$val).'__'.$value[$curr_field];
	}
} //end class HTML_QuickForm_currency
?>
