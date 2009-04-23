<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */
require_once("HTML/QuickForm/input.php");

class HTML_QuickForm_currency extends HTML_QuickForm_input {
	private $currency = 1;

	function HTML_QuickForm_currency($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('text');
		$this->currency = Base_User_SettingsCommon::get('Utils_CurrencyField', 'default_currency');
	} //end constructor

	function getFrozenHtml() {
		$val = Utils_CurrencyFieldCommon::get_values($this->getValue());
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
			
			$this->dec_digits = DB::GetOne('SELECT decimals FROM utils_currency WHERE id=%d', array(Base_User_SettingsCommon::get('Utils_CurrencyField', 'decimal_point')));

			$str .= $this->_getTabs() .
					'<span><input style="width:60%;" ' . $this->_getAttrString($this->_attributes) . ' '.
					Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils/CurrencyField','Example value: %s',array('123'.Utils_CurrencyFieldCommon::get_decimal_point().implode('',range(4,3+$this->dec_digits)))), false ).
					' /></span>'.
					'<span style="width:40px;"><select style="width:40px;" name="__'.str_replace(array('[',']'),'',$name).'__currency" id="__'.$id.'__currency">';

			$curs = DB::GetAssoc('SELECT id, symbol FROM utils_currency ORDER BY code');
			foreach ($curs as $k=>$v) {
				$str .= '<option value="'.$k.'"';
				if ($k==$this->currency) $str .= ' selected="1"';
				$str .= '>'.$v.'</option>';
			}
			$str .= '</select></span>';


			load_js('modules/Utils/CurrencyField/currency.js');
			$curr_format = '-?([0-9]*)\\'.Utils_CurrencyFieldCommon::get_decimal_point().'?[0-9]{0,'.$this->dec_digits.'}';
			eval_js('Event.observe(\''.$id.'\',\'keypress\',Utils_CurrencyField.validate.bindAsEventListener(Utils_CurrencyField,\''.Epesi::escapeJS($curr_format,false).'\'))');
			eval_js('Event.observe(\''.$id.'\',\'blur\',Utils_CurrencyField.validate_blur.bindAsEventListener(Utils_CurrencyField,\''.Epesi::escapeJS($curr_format,false).'\'))');
		}
		return $str;
	} //end func toHtml

	function exportValue(&$submitValues, $assoc = false) {
		$val = parent::exportValue($submitValues, $assoc);
		$currency = $submitValues['__'.str_replace(array('[',']'),'',$this->getName()).'__currency'];
		if ($assoc) {
			if (!isset($val[$this->getName()])) {
				$key = explode('[', $this->getName());
				$key[1] = str_replace(']','',$key[1]);
				$val = $val[$key[0]][$key[1]];
			} else $val = $val[$this->getName()];
		}
		$cur = explode(Utils_CurrencyFieldCommon::get_decimal_point(), $val);
		if (!isset($cur[1])) $ret = $cur[0]; else {
			$this->dec_digits = DB::GetOne('SELECT decimals FROM utils_currency WHERE id=%d', array($currency));
			$cur[1] = str_pad($cur[1], $this->dec_digits, '0');
			$ret = $cur[0]+$cur[1]/pow(10,$this->dec_digits);
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
		$this->updateAttributes(array('value'=>str_replace('.',Utils_CurrencyFieldCommon::get_decimal_point(),$val[0])));
		if (isset($val[1])) $this->currency = $val[1];
		// TODO: float or string? If float is to be accepted, then conversion is neccessary
	} // end func setValue


} //end class HTML_QuickForm_currency
?>
