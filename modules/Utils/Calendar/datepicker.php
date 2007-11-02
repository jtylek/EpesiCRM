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
//		$this->updateAttributes(array('readonly'=>1));
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
			$date_format = Base_RegionalSettingsCommon::date_format();
			$str .= $this->_getTabs() . '<input ' . $this->_getAttrString($this->_attributes) . ' '.Utils_Tooltip::open_tag_attrs($date_format, false ).' />'.
				Utils_CalendarCommon::show($name,
					'new Ajax.Request(\'modules/Utils/Calendar/up.php\','.
					'{method:\'post\', parameters:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__},'.
					'onSuccess:function(t){$(\''.Epesi::escapeJS($id,false).'\').value=t.responseText;}})',
					false,'expression( ($(\''.$id.'\').getStyle(\'top\') )+\'px\')','expression( ($(\''.$id.'\').getStyle(\'left\') )+\'px\')');
				
			load_js('modules/Utils/Calendar/datepicker.js');
			eval_js('Event.observe(\''.$id.'\',\'keypress\',Utils_CalendarDatePicker.validate.bindAsEventListener(Utils_CalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
			eval_js('Event.observe(\''.$id.'\',\'blur\',Utils_CalendarDatePicker.validate_blur.bindAsEventListener(Utils_CalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
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
		$this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value,false)));
	} // end func setValue

	
} //end class HTML_QuickForm_datepicker
?>