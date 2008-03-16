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
			$ex_date = Base_RegionalSettingsCommon::time2reg(null,false,true,false);
			$date_format = Base_RegionalSettingsCommon::date_format();
			$str .= $this->_getTabs() . '<table style="border:0;padding:0;" cellpadding="0" cellspacing="0"><tr>'.
				'<td><input ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils/PopupCalendar','Example date: %s',array($ex_date)), false ).' /></td>'.
				'<td>'.	Utils_PopupCalendarCommon::show(md5($id),
					'new Ajax.Request(\'modules/Utils/PopupCalendar/up.php\','.
					'{method:\'post\', parameters:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__},'.
					'onSuccess:function(t){$(\''.Epesi::escapeJS($id,false).'\').value=t.responseText;}})',
					false,null,null,
					'popup.clonePosition(\''.$id.'\',{setWidth:false,setHeight:false,offsetTop:$(\''.$id.'\').getHeight()})').'</td></tr></table>';


			load_js('modules/Utils/PopupCalendar/datepicker.js');
			eval_js('Event.observe(\''.$id.'\',\'keypress\',Utils_PopupCalendarDatePicker.validate.bindAsEventListener(Utils_PopupCalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
			eval_js('Event.observe(\''.$id.'\',\'blur\',Utils_PopupCalendarDatePicker.validate_blur.bindAsEventListener(Utils_PopupCalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
		}
		return $str;
	} //end func toHtml

	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } 
		if ($value!='') $cleanValue = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
		else $cleanValue = '';
        return $this->_prepareValue($cleanValue, $assoc);
	}

	function setValue($value) {
		if ($value) $this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value,false,true,false)));
		else $this->updateAttributes(array('value'=>''));
	} // end func setValue


} //end class HTML_QuickForm_datepicker
?>
