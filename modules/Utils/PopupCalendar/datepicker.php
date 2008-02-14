<?php
require_once("HTML/QuickForm/input.php");

require_once 'HTML/QuickForm/date.php';
/**
 * HTML class for a text field with calendar
 *
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_input {
	private $_time;
	private $_time_element;

	function HTML_QuickForm_datepicker($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->_time = $this->getAttribute('time');
		$this->setType('text');
//		$this->updateAttributes(array('readonly'=>1));
	} //end constructor

	function toHtml() {
		$str = '';
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			if(!isset($id)) {
				$id = 'datepicker_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}
			$value = $this->getAttribute('value');
			if ($value!='') $this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value, false)));
			
			if (isset($this->_time)) {
				$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';
				$lang_code = Base_LangCommon::get_lang_code();
	            $this->_time_element = new HTML_QuickForm_date('__'.$name.'__time', '__'.$name.'__time', array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
	            if ($value!='') $this->_time_element->setValue(date('H:i:s', strtotime($value)));
			}
						
			$ex_date = Base_RegionalSettingsCommon::time2reg(null,false);
			$date_format = Base_RegionalSettingsCommon::date_format();
			$str .= $this->_getTabs() . '<table style="border:0;padding:0;" cellpadding="0" cellspacing="0"><tr>'.
				'<td><input ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils/PopupCalendar','Example date: %s',array($ex_date)), false ).' /></td>';
            if ($this->_time) $str .= $this->_time_element->toHtml();
			$str .= '<td>'.	Utils_PopupCalendarCommon::show($name,
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
		$val = parent::exportValue($submitValues,$assoc);
		$name = $this->getAttribute('name');
		if ($this->_time) {
			$time = $submitValues['__'.$name.'__time'];
			if (isset($time['a'])) {
				$result = 60*($time['i']+60*($time['h']));
				if ($time['a']=='pm') $result += 43200;
				if ($time['h']==12) {
					if ($time['a']=='pm') $result -= 43200; else $result -= 43200;
				}
			} else $result = 60*($time['i']+60*($time['H']));
			$result = date(' H:i:s', $result - 60*60);
			$format = ' %H:%M:%S';
		} else {
			$format = $result = '';
		}
		if($assoc) {
			if($val[$this->getName()]) $val[$this->getName()] = strftime('%Y-%m-%d'.$format,Base_RegionalSettingsCommon::reg2time($val[$this->getName()].$result));
		} else {
			if($val) $val = strftime('%Y-%m-%d'.$format,Base_RegionalSettingsCommon::reg2time($val.$result));
		}
		return $val;
	}

    function getFrozenHtml()
    {
        $value = $this->getValue();
		if ($this->_time) $value .= ' ';
        return ('' != $value? htmlspecialchars(Base_RegionalSettingsCommon::time2reg($value, $this->_time)): '&nbsp;') .
               $this->_getPersistantData();
    } //end func getFrozenHtml

	function setValue($value) {
//		if ($value) $this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value, false)));
//		else $this->updateAttributes(array('value'=>''));
		$this->updateAttributes(array('value'=>$value));
	} // end func setValue


} //end class HTML_QuickForm_datepicker
?>
