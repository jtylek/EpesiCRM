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
	private $dec_delimiter = '.';
	private $thou_delimiter = ',';
	private $dec_digits = 2;
	private $currency = '$';

	function HTML_QuickForm_currency($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('text');
	} //end constructor

	function getFrozenHtml() {
		return Utils_CurrencyFieldCommon::format($this->getValue());
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
			
			$str .= $this->_getTabs() . '<table style="border:0;padding:0;" cellpadding="0" cellspacing="0"><tr>'.
			'<td style="border:0;">'.$this->currency.'&nbsp;</td><td style="border:0;"><input ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils/CurrencyField','Example value: %s',array('123'.$this->dec_delimiter.implode('',range(4,3+$this->dec_digits)))), false ).' /></td></tr></table>';


			load_js('modules/Utils/CurrencyField/currency.js');
			$curr_format = '-?([0-9]*)\\'.$this->dec_delimiter.'?[0-9]{0,'.$this->dec_digits.'}';
			eval_js('Event.observe(\''.$id.'\',\'keypress\',Utils_CurrencyField.validate.bindAsEventListener(Utils_CurrencyField,\''.Epesi::escapeJS($curr_format,false).'\'))');
			eval_js('Event.observe(\''.$id.'\',\'blur\',Utils_CurrencyField.validate_blur.bindAsEventListener(Utils_CurrencyField,\''.Epesi::escapeJS($curr_format,false).'\'))');
		}
		return $str;
	} //end func toHtml

	function exportValue(&$submitValues, $assoc = false) {
		$val = parent::exportValue($submitValues, $assoc);
		$cur = explode($this->dec_delimiter, $assoc?$val[$this->getName()]:$val);
		if (!isset($cur[1])) $ret = $cur[0]; else {
			$cur[1] = str_pad($cur[1], $this->dec_digits, '0');
			$ret = $cur[0]+$cur[1]/pow(10,$this->dec_digits);
		}
		if($assoc) {
			$val[$this->getName()] = $ret;
			return $val;
		} else {
			return $ret;
		}
	}

	function setValue($value) {
		$this->updateAttributes(array('value'=>$value));
		// TODO: float or string? If float is to be accepted, then conversion is neccessary
	} // end func setValue


} //end class HTML_QuickForm_currency
?>
