<?php
require_once("HTML/QuickForm/input.php");

/**
 * HTML class for a text field with calendar
 * 
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_input {
                
	function HTML_QuickForm_datepicker($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('text');
		$this->updateAttributes(array('readonly'=>1));
	} //end constructor
        
	function toHtml() {
		$str = "";
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			if(!isset($id)) {
				$id = 'datepicker_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}
			$str .= $this->_getTabs() . '<input ' . $this->_getAttrString($this->_attributes) . ' />'.
				Utils_CalendarCommon::show($name,
					'new Ajax.Request(\'modules/Utils/Calendar/up.php\','.
					'{method:\'post\', parameters:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__},'.
					'onSuccess:function(t){$(\''.Epesi::escapeJS($id,false).'\').value=t.responseText;}})');
		}
		return $str;
	} //end func toHtml

	function exportValue(&$submitValues, $assoc = false) {                                                                          
		$val = parent::exportValue($submitValues,$assoc);
		if($assoc) {
			if($val[$this->getName()]) $val[$this->getName()] = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($val[$this->getName()]));
		} else {
			if($val) $val=strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($val));
		}
		return $val;
	}

	function setValue($value) {
		if(!is_numeric($value) && is_string($value))
			$value = strtotime($value);
		$this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value,false)));
	} // end func setValue

	
} //end class HTML_QuickForm_datepicker
?>